<?php

declare(strict_types=1);

namespace App\Commands;

use App\Checkout\CheckoutLocator;
use App\Checkout\Instance;
use App\Config\ProjectConfig;
use App\Env\EnvProjector;
use App\Exceptions\ConfigException;
use App\Router\Router;
use App\Support\Ui;

use function Laravel\Prompts\confirm;

/**
 * Diagnoses the machine, project and router, and offers to fix what it can.
 */
final class DoctorCommand extends RiftCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'doctor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose this machine\'s rift setup';

    private int $problems = 0;

    /**
     * Execute the console command.
     *
     * @param  Router  $router  The shared router.
     * @param  CheckoutLocator  $locator  The checkout locator.
     * @param  EnvProjector  $projector  The env projector.
     *
     * @return int The exit code.
     */
    public function handle(Router $router, CheckoutLocator $locator, EnvProjector $projector): int
    {
        Ui::rule('doctor', $router->config->name);

        Ui::heading('machine');

        $dockerRunning = $router->docker->available();

        $this->check(
            'Docker is running',
            $dockerRunning,
            'Start Docker Desktop, OrbStack or Colima, then try again.'
        );

        if (! $dockerRunning) {
            return $this->finish();
        }

        Ui::heading('project');
        $this->checkProject($locator, $projector);

        Ui::heading('router');
        $this->checkRouter($router);
        $this->checkOrphans($router);

        return $this->finish();
    }

    /**
     * Check the current checkout's config, .env and compose wiring.
     *
     * @param  CheckoutLocator  $locator  The checkout locator.
     * @param  EnvProjector  $projector  The env projector.
     *
     * @return void
     */
    private function checkProject(CheckoutLocator $locator, EnvProjector $projector): void
    {
        try {
            $checkout = $locator->locate();
        } catch (ConfigException) {
            $this->skip('Not inside a git checkout — skipping project checks');

            return;
        }

        // Not being a rift project is not a fault; a broken rift.yaml is.
        if (! ProjectConfig::existsIn($checkout->root)) {
            $this->skip('No ' . ProjectConfig::FILENAME . ' here — skipping project checks');

            return;
        }

        try {
            ProjectConfig::load($checkout->root);
        } catch (ConfigException $e) {
            $this->check('Project config is valid', false, $e->getMessage());

            return;
        }

        $this->check('Project config is valid', true);

        $instance = $this->instance();
        $missing = $projector->missingKeys($instance);

        $this->check(
            '.env carries everything compose interpolates',
            $missing === [],
            'Missing: ' . implode(', ', $missing) . '. Run `rift setup`.',
        );

        $this->checkComposeWiring($instance);

        $this->skip(sprintf(
            '%s serves %s',
            $instance->composeName(),
            implode(', ', $instance->urls()),
        ));
    }

    /**
     * Check that the running app container joined the router network.
     *
     * @param  Instance  $instance  The current checkout.
     *
     * @return void
     */
    private function checkComposeWiring(Instance $instance): void
    {
        $router = $this->router();
        $container = $instance->container();

        if (! $router->docker->containerRunning($container)) {
            $this->skip("{$container} is not running — cannot check its network");

            return;
        }

        $this->check(
            'App container is on the router network',
            in_array($container, $router->docker->networkMembers($router->config->network()), true),
            'Add `networks: [default, router]` to ' . $instance->config->appService
            . ' and an external `router` network named "${ROUTER_NAME}" to your compose file.',
        );
    }

    /**
     * Check that the router, its admin API and its CA are healthy.
     *
     * @param  Router  $router  The shared router.
     *
     * @return void
     */
    private function checkRouter(Router $router): void
    {
        if (! $router->isRunning()) {
            $blocker = $router->blocker();

            $this->check(
                "{$router->config->name} is running",
                false,
                $blocker !== null
                    ? "{$blocker} is holding ports 80 and 443. Stop it, then run `rift router:start`."
                    : 'Run `rift router:start`.',
            );

            return;
        }

        $this->check("{$router->config->name} is running", true);

        $routes = $router->admin->routeIds();

        $this->check(
            'Router admin API is answering',
            $routes !== null,
            'The container is up but :2019 is not answering. Try `rift router:restart`.',
        );

        if ($routes === null) {
            return;
        }

        $this->skip(sprintf(
            '%d route%s registered',
            count($routes),
            count($routes) === 1 ? '' : 's',
        ));

        $ca = $router->ca;

        if (! $ca->extract()) {
            $this->check('CA certificate is trusted', false, 'Could not read the CA. Try `rift router:restart`.');

            return;
        }

        $this->check('CA certificate is trusted', $ca->isTrusted(), 'Run `rift trust`.');
    }

    /**
     * Check for other Caddy routers competing for ports 80 and 443, offering to remove them.
     *
     * @param  Router  $router  The shared router.
     *
     * @return void
     */
    private function checkOrphans(Router $router): void
    {
        $orphans = array_values(array_filter(
            $router->docker->containersFromImage($router->config->image),
            fn (string $name) => $name !== $router->config->container(),
        ));

        if ($orphans === []) {
            $this->check('No other routers running the router image', true);

            return;
        }

        $this->check(
            'No other routers running the router image',
            false,
            'Found: ' . implode(', ', $orphans) . '. They will compete with the shared router for ports 80 and 443.',
        );

        if (! confirm('Remove them?', default: false)) {
            return;
        }

        foreach ($orphans as $orphan) {
            $removed = $router->docker->removeContainer($orphan);

            Ui::hint($removed ? "removed {$orphan}" : "could not remove {$orphan}");
        }
    }

    /**
     * Render a passing or failing check, counting failures.
     *
     * @param  string  $label  What was checked.
     * @param  bool  $ok  Whether the check passed.
     * @param  string  $fix  The advice shown when it failed.
     *
     * @return void
     */
    private function check(string $label, bool $ok, string $fix = ''): void
    {
        if ($ok) {
            Ui::item('✓', 'green', $label);

            return;
        }

        $this->problems++;
        Ui::item('✗', 'red', $label);

        if ($fix !== '') {
            Ui::hint($fix);
        }
    }

    /**
     * Render a check that was skipped or is informational.
     *
     * @param  string  $label  The line to show.
     *
     * @return void
     */
    private function skip(string $label): void
    {
        Ui::item('·', 'gray', $label);
    }

    /**
     * Render the summary and return the exit code.
     *
     * @return int The exit code.
     */
    private function finish(): int
    {
        Ui::blank();

        Ui::endRule($this->problems === 0
            ? 'everything checks out'
            : sprintf('%d problem%s found', $this->problems, $this->problems === 1 ? '' : 's'));

        Ui::blank();

        return $this->problems === 0 ? self::SUCCESS : self::FAILURE;
    }
}
