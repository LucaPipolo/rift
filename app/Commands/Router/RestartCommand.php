<?php

declare(strict_types=1);

namespace App\Commands\Router;

use App\Commands\Concerns\ManagesRouter;
use App\Commands\RiftCommand;
use App\Router\Router;

/**
 * Restarts the shared router.
 */
final class RestartCommand extends RiftCommand
{
    use ManagesRouter;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'router:restart';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restart the shared router';

    /**
     * Execute the console command.
     *
     * @param  Router  $router  The shared router.
     *
     * @return int The exit code.
     */
    public function handle(Router $router): int
    {
        if (! $this->requireDocker()) {
            return self::FAILURE;
        }

        $this->header($router->config->name);

        if (! $this->stopRouter($router) || ! $this->startRouter($router)) {
            return self::FAILURE;
        }

        $this->success('running');

        return self::SUCCESS;
    }
}
