<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Checkout\Checkout;
use App\Checkout\CheckoutLocator;
use App\Checkout\Instance;
use App\Config\ProjectConfig;
use App\Config\RouterConfig;
use App\Docker\Container;
use App\Enums\CheckoutState;
use App\Env\EnvFile;
use App\Env\EnvProjector;
use App\Exceptions\ConfigException;
use App\Router\Router;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Reads the state of every project and the router into a snapshot.
 *
 * Containers come from one `docker ps` for the whole machine. Git status and
 * certificate trust change slowly and cost a process per checkout, so they are
 * read less often than the refresh rate and kept in between.
 */
final class SnapshotCollector
{
    private const int GIT_SECONDS = 10;

    private const int TRUST_SECONDS = 60;

    /** @var array<string, array{at: int, status: GitStatus}> */
    private array $git = [];

    /** @var array{at: int, trusted: bool}|null */
    private ?array $trust = null;

    /** @var array<string, array{members: list<string>, routes: list<string>}> What each router can reach, read once per snapshot. */
    private array $reach = [];

    /**
     * Create a new snapshot collector instance.
     *
     * @param  RouterConfig  $router  The shared router's settings.
     * @param  CheckoutLocator  $locator  The checkout locator.
     * @param  EnvProjector  $projector  The env projector, which knows the keys rift manages.
     */
    public function __construct(
        private readonly RouterConfig $router,
        private readonly CheckoutLocator $locator,
        private readonly EnvProjector $projector,
    ) {}

    /**
     * Read the router and every project into a snapshot.
     *
     * @param  list<string>  $roots  The main checkout root of every project.
     *
     * @return Snapshot The snapshot.
     */
    public function collect(array $roots): Snapshot
    {
        // A fresh router each time, because Docker caches which containers run
        // and only forgets when it starts or stops one itself.
        $router = Router::for($this->router);
        $running = $router->isRunning();
        $table = $running ? $this->table($router->admin->routes()) : null;
        $routes = $table === null ? null : array_column($table, 'id');
        $members = $router->docker->networkMembers($this->router->network());
        $containers = $router->docker->containers();
        $this->reach = [$this->router->name => ['members' => $members, 'routes' => $routes ?? []]];

        $projects = [];

        foreach (array_unique($roots) as $root) {
            $projects[] = $this->project($root, $containers);
        }

        usort($projects, fn (ProjectStatus $a, ProjectStatus $b) => [$a->name(), $a->root] <=> [$b->name(), $b->root]);

        return new Snapshot(
            new RouterStatus($this->router, $running, $routes, $members, $this->trusted($router), $table ?? []),
            $projects,
        );
    }

    /**
     * Forget the cached git status and trust, so the next snapshot reads them again.
     *
     * @return void
     */
    public function forget(): void
    {
        $this->git = [];
        $this->trust = null;
    }

    /**
     * Read each registered route's id, hosts and upstream from the admin API's routes.
     *
     * @param  list<array<array-key, mixed>>|null  $routes  The routes, or null when the admin API is not answering.
     *
     * @return list<array{id: string, hosts: list<string>, upstream: string}>|null The routes that carry an id, or null.
     */
    private function table(?array $routes): ?array
    {
        if ($routes === null) {
            return null;
        }

        $table = [];

        foreach ($routes as $route) {
            $id = $route['@id'] ?? null;
            $hosts = data_get($route, 'match.0.host');
            $dial = data_get($route, 'handle.0.routes.0.handle.0.upstreams.0.dial');

            if (! is_string($id) || $id === '') {
                continue;
            }

            $table[] = [
                'id' => $id,
                'hosts' => array_values(array_filter(is_array($hosts) ? $hosts : [], is_string(...))),
                'upstream' => is_string($dial) ? $dial : '',
            ];
        }

        return $table;
    }

    /**
     * Read one project and its checkouts.
     *
     * @param  string  $root  The main checkout root.
     * @param  list<Container>  $containers  Every Compose container on the machine.
     *
     * @return ProjectStatus The project.
     */
    private function project(string $root, array $containers): ProjectStatus
    {
        try {
            $config = ProjectConfig::load($root);
        } catch (ConfigException $e) {
            return new ProjectStatus($root, null, error: $e->getMessage());
        }

        // A worktree removed while it is being read fails its git call; only this project is lost, until the next read.
        try {
            return $this->checkouts($root, $config, $containers);
        } catch (RuntimeException $e) {
            return new ProjectStatus($root, $config, error: "Rift could not read it: {$e->getMessage()}");
        }
    }

    /**
     * Read the checkouts of a project whose rift.yaml has loaded.
     *
     * @param  string  $root  The main checkout root.
     * @param  ProjectConfig  $config  The project's rift.yaml.
     * @param  list<Container>  $containers  Every Compose container on the machine.
     *
     * @return ProjectStatus The project.
     */
    private function checkouts(string $root, ProjectConfig $config, array $containers): ProjectStatus
    {
        $found = $this->locator->all($root);

        if ($found === []) {
            return new ProjectStatus($root, $config, error: 'Git cannot read this checkout.');
        }

        $mainBranch = $found[0]['branch'];
        $checkouts = [];

        foreach ($found as ['checkout' => $checkout, 'branch' => $branch]) {
            $instance = Instance::make($checkout, $this->configFor($checkout, $config), $this->router);
            $own = array_values(array_filter(
                $containers,
                fn (Container $container) => $container->project === $instance->composeName(),
            ));
            $app = array_find($own, fn (Container $container) => $container->name === $instance->container());
            $reach = $this->reach($instance->router);

            $checkouts[] = new CheckoutStatus(
                instance: $instance,
                branch: $branch,
                state: CheckoutState::of(
                    $app,
                    onNetwork: in_array($instance->container(), $reach['members'], true),
                    routed: in_array($instance->routeId(), $reach['routes'], true),
                ),
                containers: $own,
                git: $this->git($checkout, $branch, $mainBranch),
                env: $this->env($instance),
            );
        }

        return new ProjectStatus($root, $config, $checkouts);
    }

    /**
     * Get the containers on a router's network and its route ids, reading a router a project pins only once.
     *
     * @param  RouterConfig  $config  The router a checkout is served by.
     *
     * @return array{members: list<string>, routes: list<string>} What the router can reach.
     */
    private function reach(RouterConfig $config): array
    {
        if (! isset($this->reach[$config->name])) {
            $router = Router::for($config);

            $this->reach[$config->name] = [
                'members' => $router->docker->networkMembers($config->network()),
                'routes' => ($router->isRunning() ? $router->admin->routeIds() : null) ?? [],
            ];
        }

        return $this->reach[$config->name];
    }

    /**
     * Get the rift.yaml a checkout runs with: its own, or the main checkout's when it has none it can read.
     *
     * @param  Checkout  $checkout  The checkout.
     * @param  ProjectConfig  $main  The main checkout's rift.yaml.
     *
     * @return ProjectConfig The config.
     */
    private function configFor(Checkout $checkout, ProjectConfig $main): ProjectConfig
    {
        if ($checkout->isPrimary() || ! ProjectConfig::existsIn($checkout->root)) {
            return $main;
        }

        try {
            return ProjectConfig::load($checkout->root);
        } catch (ConfigException) {
            return $main;
        }
    }

    /**
     * Read the .env values rift manages for a checkout.
     *
     * @param  Instance  $instance  The checkout.
     *
     * @return array<string, string|null> The values, keyed in the order rift writes them.
     */
    private function env(Instance $instance): array
    {
        $file = EnvFile::at($instance->envPath());
        $values = [];

        foreach ([...$this->projector->requiredKeys($instance), ...array_keys($instance->config->env)] as $key) {
            $values[$key] = $file->get($key);
        }

        return $values;
    }

    /**
     * Read a checkout's git status, reusing a recent reading.
     *
     * @param  Checkout  $checkout  The checkout.
     * @param  string|null  $branch  Its branch, or null when detached.
     * @param  string|null  $mainBranch  The main checkout's branch.
     *
     * @return GitStatus The git status.
     */
    private function git(Checkout $checkout, ?string $branch, ?string $mainBranch): GitStatus
    {
        $now = now()->getTimestamp();
        $cached = $this->git[$checkout->root] ?? null;

        if ($cached !== null && $now - $cached['at'] < self::GIT_SECONDS) {
            return $cached['status'];
        }

        $changes = [];
        $changedAt = null;
        $result = Process::path($checkout->root)->run(['git', 'status', '--porcelain']);

        foreach ($result->successful() ? explode("\n", rtrim($result->output())) : [] as $line) {
            if (strlen($line) < 4) {
                continue;
            }

            // A rename reads "R  old -> new", and git quotes paths with unusual characters.
            $path = trim(str_contains($line, ' -> ') ? substr($line, (int) strrpos($line, ' -> ') + 4) : substr($line, 3), '"');
            $changes[] = $path;
            $modified = is_file($checkout->root . '/' . $path) ? filemtime($checkout->root . '/' . $path) : false;

            if ($modified !== false) {
                $changedAt = max($changedAt ?? 0, $modified);
            }
        }

        [$behind, $ahead] = $this->divergence($checkout, $branch, $mainBranch);

        $status = new GitStatus($ahead, $behind, $changes, $changedAt);
        $this->git[$checkout->root] = ['at' => $now, 'status' => $status];

        return $status;
    }

    /**
     * Count the commits a worktree's branch and the main branch each have that the other lacks.
     *
     * @param  Checkout  $checkout  The checkout.
     * @param  string|null  $branch  Its branch, or null when detached.
     * @param  string|null  $mainBranch  The main checkout's branch.
     *
     * @return array{int, int} The commits behind and ahead.
     */
    private function divergence(Checkout $checkout, ?string $branch, ?string $mainBranch): array
    {
        if ($checkout->isPrimary() || $mainBranch === null || $branch === null || $branch === $mainBranch) {
            return [0, 0];
        }

        $result = Process::path($checkout->root)->run(['git', 'rev-list', '--left-right', '--count', "{$mainBranch}...HEAD"]);

        if (! $result->successful() || preg_match('/^(\d+)\s+(\d+)/', trim($result->output()), $counts) !== 1) {
            return [0, 0];
        }

        return [(int) $counts[1], (int) $counts[2]];
    }

    /**
     * Determine whether this machine trusts the router's CA, reusing a recent answer.
     *
     * @param  Router  $router  The router.
     *
     * @return bool Whether the CA is trusted.
     */
    private function trusted(Router $router): bool
    {
        $now = now()->getTimestamp();

        if ($this->trust === null || $now - $this->trust['at'] >= self::TRUST_SECONDS) {
            $this->trust = ['at' => $now, 'trusted' => $router->ca->isTrusted()];
        }

        return $this->trust['trusted'];
    }
}
