<?php

declare(strict_types=1);

use App\Checkout\Checkout;
use App\Checkout\Instance;
use App\Config\ProjectConfig;
use App\Config\RouterConfig;
use App\Dashboard\CheckoutStatus;
use App\Dashboard\GitStatus;
use App\Dashboard\ProjectStatus;
use App\Dashboard\RouterStatus;
use App\Dashboard\Snapshot;
use App\Docker\Container;
use App\Enums\CheckoutState;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\FakeProcessDescription;
use Illuminate\Support\Facades\Process;

/**
 * The Unix time dashboard fixtures are taken at.
 */
const DASHBOARD_NOW = 1_800_000_000;

/**
 * Create a snapshot of a machine running two projects, with a checkout in every state.
 *
 * `myapp` has a serving main checkout, a serving `search` worktree with work in
 * progress, an unreachable `pdf-export`, a failed `orders` and a stopped
 * `fix-login`. `shop` has one serving checkout.
 *
 * @param  bool  $routerRunning  Whether the router is running.
 *
 * @return Snapshot The snapshot.
 */
function dashboardSnapshot(bool $routerRunning = true): Snapshot
{
    $config = ProjectConfig::fromArray([
        'name' => 'myapp',
        'domain' => 'myapp.test',
        'app' => ['port' => 80],
        'hosts' => ['api'],
        'ports' => [['key' => 'VITE_PORT', 'default' => 5173]],
    ]);

    $myapp = new ProjectStatus('/Sites/myapp', $config, [
        dashboardCheckout($config, null, 'main', CheckoutState::Serving, env: ['VITE_PORT' => '5173']),
        dashboardCheckout($config, 'search', 'feat/search', CheckoutState::Serving, new GitStatus(3, 1, ['a.php', 'b.php'], DASHBOARD_NOW - 8), ['VITE_PORT' => '5174']),
        dashboardCheckout($config, 'pdf-export', 'feat/pdf-export', CheckoutState::Unreachable),
        dashboardCheckout($config, 'orders', 'refactor/orders', CheckoutState::Failed, exitCode: 137),
        dashboardCheckout($config, 'fix-login', null, CheckoutState::Stopped, new GitStatus(changedAt: DASHBOARD_NOW - 7200)),
    ]);

    $shopConfig = ProjectConfig::fromArray(['name' => 'shop', 'domain' => 'shop.test', 'app' => ['port' => 3000]]);
    $shop = new ProjectStatus('/Sites/shop', $shopConfig, [
        dashboardCheckout($shopConfig, null, 'main', CheckoutState::Serving),
    ]);

    return new Snapshot(
        new RouterStatus(new RouterConfig(), $routerRunning, $routerRunning ? ['myapp', 'myapp-search', 'shop'] : null, ['myapp-app-1'], trusted: true),
        [$myapp, $shop],
    );
}

/**
 * Create the snapshot the JSON contract fixture in dash/testdata/snapshot.json is made from.
 *
 * It adds to dashboardSnapshot() what that one leaves empty: routes, one of
 * them unreachable, a Sail project with hooks, and .env values from each
 * source, one of them missing.
 *
 * @return Snapshot The snapshot.
 */
function contractSnapshot(): Snapshot
{
    $fixture = dashboardSnapshot();
    $config = ProjectConfig::fromArray([
        'name' => 'shop',
        'domain' => 'shop.test',
        'preset' => 'sail',
        'app' => ['port' => 80],
        'env' => ['APP_URL' => 'https://{domain}'],
        'ports' => [['key' => 'FORWARD_DB_PORT', 'default' => 3306]],
        'hooks' => [
            'after_up' => [['run' => 'php artisan migrate', 'label' => 'Migrating']],
            'before_down' => [['run' => 'npm run stop', 'on' => 'host']],
        ],
    ]);

    $shop = new ProjectStatus('/Sites/shop', $config, [
        dashboardCheckout($config, null, 'main', CheckoutState::Serving, env: [
            'COMPOSE_PROJECT_NAME' => 'shop',
            'FORWARD_DB_PORT' => '3307',
            'APP_URL' => null,
        ]),
    ]);

    return new Snapshot(
        new RouterStatus(new RouterConfig(), true, ['myapp', 'myapp-pdf-export', 'shop'], ['myapp-app-1', 'shop-laravel.test-1'], trusted: true, table: [
            ['id' => 'myapp', 'hosts' => ['myapp.test', 'api.myapp.test'], 'upstream' => 'myapp-app-1:80'],
            ['id' => 'myapp-pdf-export', 'hosts' => ['pdf-export.myapp.test'], 'upstream' => 'myapp-pdf-export-app-1:80'],
            ['id' => 'shop', 'hosts' => ['shop.test'], 'upstream' => 'shop-laravel.test-1:80'],
        ]),
        [$fixture->projects[0], $shop, new ProjectStatus('/Sites/broken', null, error: '/Sites/broken/rift.yaml: `name` is required.')],
    );
}

/**
 * Create one checkout of a dashboard fixture, with an app and a database container.
 *
 * @param  ProjectConfig  $config  The project's config.
 * @param  string|null  $slug  The worktree slug, or null for the main checkout.
 * @param  string|null  $branch  The branch, or null when detached.
 * @param  CheckoutState  $state  The checkout's state.
 * @param  GitStatus|null  $git  The git status.
 * @param  array<string, string|null>  $env  The managed .env values.
 * @param  int  $exitCode  The app container's exit code, when it is not running.
 *
 * @return CheckoutStatus The checkout.
 */
function dashboardCheckout(
    ProjectConfig $config,
    ?string $slug,
    ?string $branch,
    CheckoutState $state,
    ?GitStatus $git = null,
    array $env = [],
    int $exitCode = 0,
): CheckoutStatus {
    $main = '/Sites/' . $config->name;
    $instance = Instance::make(
        new Checkout($slug === null ? $main : "{$main}-{$slug}", $main . '/.git', $main, $slug),
        $config,
        new RouterConfig(),
    );

    $containers = $state === CheckoutState::Stopped ? [] : [
        new Container(
            name: $instance->container(),
            project: $instance->composeName(),
            service: $config->appService,
            state: $state->isUp() ? 'running' : 'exited',
            status: $state->isUp() ? 'Up 2 hours' : "Exited ({$exitCode}) 4 minutes ago",
            image: $config->name . '-app',
        ),
        new Container($instance->composeName() . '-mysql-1', $instance->composeName(), 'mysql', 'running', 'Up 2 hours (healthy)', 'mysql:8.4'),
    ];

    return new CheckoutStatus($instance, $branch, $state, $containers, $git ?? new GitStatus(), $env);
}

/**
 * Fake a machine running the main checkout of `myapp` and a failed `search` worktree.
 *
 * @param  string  $root  The main checkout root.
 * @param  string  $tree  The worktree root.
 * @param  array<string, string|ProcessResult|FakeProcessDescription|Closure>  $overrides  Results keyed by command pattern, matched first.
 *
 * @return void
 */
function collectorHost(string $root, string $tree, array $overrides = []): void
{
    $container = fn (string $name, string $project, string $service, string $state, string $status): string => (string) json_encode([
        'Names' => $name,
        'Labels' => "com.docker.compose.project={$project},com.docker.compose.service={$service}",
        'State' => $state,
        'Status' => $status,
        'Image' => 'nginx',
    ]);

    $defaults = [
        '*no-trunc*' => implode("\n", [
            $container('myapp-app-1', 'myapp', 'app', 'running', 'Up 2 hours'),
            $container('myapp-db-1', 'myapp', 'db', 'running', 'Up 2 hours (healthy)'),
            $container('myapp-search-app-1', 'myapp-search', 'app', 'exited', 'Exited (137) 1 minute ago'),
        ]) . "\n",
        '*docker*ps*' => "rift-router\nmyapp-app-1\n",
        '*network*inspect*' => "myapp-app-1\n",
        '*--git-common-dir*' => $root . "/.git\n",
        '*worktree*list*' => "worktree {$root}\nbranch refs/heads/main\n\nworktree {$tree}\nbranch refs/heads/feat/search\n",
        '*status*--porcelain*' => " M a.php\n?? gone.txt\nR  old.php -> b.php\n?? \"odd name.txt\"\n\n",
        '*rev-list*' => "1\t3\n",
        '*' => '',
    ];

    // The first matching pattern answers, so overrides go first and replace their default.
    Process::fake([...$overrides, ...array_diff_key($defaults, $overrides)]);
}
