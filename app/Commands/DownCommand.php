<?php

declare(strict_types=1);

namespace App\Commands;

use App\Enums\HookStage;
use App\Support\Outcome;

/**
 * Stops the current checkout, leaving every sibling serving.
 */
final class DownCommand extends RiftCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'down';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Stop this checkout and remove its route';

    /**
     * Execute the console command.
     *
     * @return int The exit code.
     */
    public function handle(): int
    {
        if (! $this->requireDocker()) {
            return self::FAILURE;
        }

        $instance = $this->instance();
        $router = $this->router();
        $runner = $this->runner();

        $this->header($instance->config->name);

        $this->runHooks($runner, $instance, HookStage::BeforeDown);

        $this->step('Stopping containers', function () use ($runner, $instance) {
            if (! $runner->isBootstrapped($instance)) {
                return Outcome::skipped('nothing to stop');
            }

            return Outcome::succeeded($runner->down($instance));
        });

        $this->step('Deregistering route', fn () => Outcome::changed(
            $router->deregister($instance),
            skipped: 'nothing registered',
        ));

        $this->step('Stopping router', fn () => $this->stopRouterIfIdle());

        $this->runHooks($runner, $instance, HookStage::AfterDown);

        if ($this->hasFailed()) {
            return self::FAILURE;
        }

        $this->success('stopped');

        return self::SUCCESS;
    }

    /**
     * Stop a router the project pinned for itself once nothing routes through it.
     *
     * The shared router is never stopped here: other projects expect it on their
     * next `up`, and as an `unless-stopped` container it would stay down.
     *
     * @return Outcome How the step ended.
     */
    private function stopRouterIfIdle(): Outcome
    {
        $instance = $this->instance();
        $router = $this->router();

        if ($instance->sharesRouter) {
            return Outcome::skipped('shared — use `rift router:stop`');
        }

        if (! $router->isRunning()) {
            return Outcome::skipped('not running');
        }

        $routes = $router->admin->routeIds();

        // A router that cannot be reached is not an idle router.
        if ($routes === null) {
            return Outcome::skipped('unreachable');
        }

        if ($routes !== []) {
            return Outcome::skipped('still in use');
        }

        return Outcome::succeeded($router->stop(), failed: 'could not stop');
    }
}
