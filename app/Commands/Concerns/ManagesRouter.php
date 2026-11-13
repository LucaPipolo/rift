<?php

declare(strict_types=1);

namespace App\Commands\Concerns;

use App\Config\PortBinding;
use App\Exceptions\AddressNotAssignedException;
use App\Exceptions\PortConflictException;
use App\Router\PortHolder;
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
        $unassigned = null;

        $started = $this->step('Starting router', function () use ($router, &$conflict, &$unassigned) {
            try {
                return Outcome::changed($router->ensureRunning(), skipped: 'already running');
            } catch (PortConflictException $e) {
                $conflict = $e;

                return Outcome::failed("ports {$router->config->binding->http}/{$router->config->binding->https} in use");
            } catch (AddressNotAssignedException $e) {
                $unassigned = $e;

                return Outcome::failed("{$e->address} not assigned");
            }
        });

        if ($started->fatal) {
            if ($conflict !== null) {
                $this->reportPortConflict($conflict->holder, $router->config->binding);
            }

            if ($unassigned !== null) {
                $this->notice([
                    "<span class=\"font-bold\">{$unassigned->address}</span> is not assigned to this machine,",
                    "so the router cannot listen on it. {$unassigned->fix}",
                ]);
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
     * Render the notice asking the user to free the router's web ports.
     *
     * @param  PortHolder  $holder  The container or process holding the ports.
     * @param  PortBinding  $binding  The binding the router needs.
     *
     * @return void
     */
    protected function reportPortConflict(PortHolder $holder, PortBinding $binding): void
    {
        $lines = [
            "<span class=\"font-bold\">{$holder->name}</span> already holds ports {$binding->describe()},",
            "which rift's router needs. Stop it first, then try again" . ($holder->onHost ? '.' : ':'),
        ];

        if (! $holder->onHost) {
            $lines = [...$lines, '', "  <span class=\"font-bold\">docker stop {$holder->name}</span>"];
        }

        $this->notice($lines);
    }
}
