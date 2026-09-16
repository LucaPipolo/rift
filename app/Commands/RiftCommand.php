<?php

declare(strict_types=1);

namespace App\Commands;

use App\Checkout\CheckoutLocator;
use App\Checkout\Instance;
use App\Commands\Concerns\RendersSteps;
use App\Config\ProjectConfig;
use App\Config\RouterConfig;
use App\Docker\ContainerRunner;
use App\Docker\Docker;
use App\Enums\HookStage;
use App\Exceptions\ConfigException;
use App\Router\Router;
use App\Support\Outcome;
use App\Support\Ui;
use LaravelZero\Framework\Commands\Command;

/**
 * The base for every rift command, resolving the current checkout and its router.
 */
abstract class RiftCommand extends Command
{
    use RendersSteps;

    private ?Instance $instance = null;

    private ?Router $router = null;

    /**
     * Get the checkout the command runs in, bound to its router.
     *
     * @return Instance The current checkout.
     *
     * @throws ConfigException
     */
    protected function instance(): Instance
    {
        if ($this->instance !== null) {
            return $this->instance;
        }

        $checkout = $this->laravel->make(CheckoutLocator::class)->locate();

        return $this->instance = Instance::make(
            $checkout,
            ProjectConfig::load($checkout->root),
            $this->laravel->make(RouterConfig::class),
        );
    }

    /**
     * Get the router the current checkout routes through.
     *
     * @return Router The checkout's router.
     *
     * @throws ConfigException
     */
    protected function router(): Router
    {
        return $this->router ??= Router::for($this->instance()->router);
    }

    /**
     * Create a container runner that streams into this run's output.
     *
     * @return ContainerRunner The container runner.
     */
    protected function runner(): ContainerRunner
    {
        return new ContainerRunner($this->sink());
    }

    /**
     * Run a lifecycle stage's hooks, stopping at the first failure.
     *
     * @param  ContainerRunner  $runner  The container runner.
     * @param  Instance  $instance  The current checkout.
     * @param  HookStage  $stage  The lifecycle stage.
     *
     * @return bool Whether every hook succeeded.
     */
    protected function runHooks(ContainerRunner $runner, Instance $instance, HookStage $stage): bool
    {
        foreach ($runner->hooksFor($instance, $stage) as $hook) {
            $outcome = $this->step($hook->label ?? $stage->defaultLabel(), fn () => Outcome::succeeded(
                $runner->runHook($hook, $instance),
                failed: 'hook failed',
            ));

            if ($outcome->fatal) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ensure a Docker daemon is answering, explaining how to start one if not.
     *
     * @return bool Whether Docker is available.
     */
    protected function requireDocker(): bool
    {
        if ($this->laravel->make(Docker::class)->available()) {
            return true;
        }

        $this->notice([
            'rift needs a running Docker daemon and could not reach one.',
            '',
            'Start Docker Desktop, OrbStack or Colima, then try again.',
        ], 'docker');

        return false;
    }

    /**
     * Refuse a worktree whose name is also one of the project's host aliases.
     *
     * Both would be served at the same hostname, and the router would silently
     * hand it to whichever registered last.
     *
     * @param  Instance  $instance  The current checkout.
     *
     * @return bool Whether the worktree was refused.
     */
    protected function refuseHostCollision(Instance $instance): bool
    {
        $slug = $instance->hostCollision();

        if ($slug === null) {
            return false;
        }

        $this->bail(sprintf(
            'This worktree is named `%s`, which is also a host alias in rift.yaml, so it would answer at %s.%s '
            . 'alongside the main checkout. Rename the worktree directory.',
            $slug,
            $slug,
            $instance->config->baseDomain,
        ));

        return true;
    }

    /**
     * Render an error and return the failure exit code.
     *
     * @param  string  $message  The error message.
     *
     * @return int The failure exit code.
     */
    protected function bail(string $message): int
    {
        Ui::error($message);
        Ui::blank();

        return self::FAILURE;
    }
}
