<?php

declare(strict_types=1);

namespace App\Commands\Concerns;

use App\Exceptions\PortConflictException;
use App\Router\Router;
use App\Support\Outcome;

/**
 * Starts and stops the router as a series of steps.
 */
trait ManagesRouter
{
    /**
     * Create the router network, start the router and load its base config.
     *
     * @param  Router  $router  The router to start.
     *
     * @return bool Whether every step succeeded.
     */
    protected function startRouter(Router $router): bool
    {
        $this->step('Setting up network', fn () => Outcome::changed(
            $router->ensureNetwork(),
            skipped: 'already exists',
        ));

        $conflict = null;

        $started = $this->step('Starting router', function () use ($router, &$conflict) {
            try {
                return Outcome::changed($router->ensureRunning(), skipped: 'already running');
            } catch (PortConflictException $e) {
                $conflict = $e;

                return Outcome::failed('ports 80/443 in use');
            }
        });

        if ($started->fatal) {
            if ($conflict !== null) {
                $this->reportPortConflict($conflict->holder);
            }

            return false;
        }

        $this->step('Loading router config', fn () => Outcome::changed(
            $router->admin->ensureServer(),
            skipped: 'already loaded',
        ));

        return ! $this->hasFailed();
    }

    /**
     * Stop the router, logging the routes it was still serving.
     *
     * @param  Router  $router  The router to stop.
     *
     * @return bool Whether the step succeeded.
     */
    protected function stopRouter(Router $router): bool
    {
        $outcome = $this->step('Stopping router', function () use ($router) {
            if (! $router->isRunning()) {
                return Outcome::skipped('not running');
            }

            $serving = $router->admin->routeIds() ?? [];

            if ($serving !== []) {
                $this->sink()('still serving: ' . implode(', ', $serving) . "\n");
            }

            return $router->stop()
                ? Outcome::done($serving === [] ? '' : count($serving) . ' route(s) dropped')
                : Outcome::failed('could not stop');
        });

        return ! $outcome->fatal;
    }

    /**
     * Render the notice asking the user to free ports 80 and 443.
     *
     * @param  string  $holder  The container holding the ports.
     *
     * @return void
     */
    protected function reportPortConflict(string $holder): void
    {
        $this->notice([
            "<span class=\"font-bold\">{$holder}</span> already holds ports 80 and 443,",
            "which rift's router needs. Stop it first, then try again:",
            '',
            "  <span class=\"font-bold\">docker stop {$holder}</span>",
        ]);
    }
}
