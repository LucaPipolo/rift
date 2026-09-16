<?php

declare(strict_types=1);

namespace App\Commands\Router;

use App\Commands\Concerns\ManagesRouter;
use App\Commands\RiftCommand;
use App\Router\Router;

/**
 * Stops the shared router, which `rift down` never does on its own.
 */
final class StopCommand extends RiftCommand
{
    use ManagesRouter;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'router:stop';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Stop the shared router';

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

        if (! $this->stopRouter($router)) {
            return self::FAILURE;
        }

        $this->success('stopped');

        return self::SUCCESS;
    }
}
