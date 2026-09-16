<?php

declare(strict_types=1);

namespace App\Commands;

use App\Router\Router;
use App\Support\Outcome;

/**
 * Installs the shared router's CA into the system trust store.
 */
final class TrustCommand extends RiftCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trust';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trust the shared router\'s certificate authority';

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

        if (! $router->isRunning()) {
            return $this->bail("{$router->config->name} is not running. Run `rift router:start` first.");
        }

        $this->header($router->config->name);

        $ca = $router->ca;

        $this->step('Waiting for the CA', fn () => Outcome::succeeded(
            $ca->waitUntilIssued(),
            failed: 'never appeared',
        ));

        if ($this->hasFailed()) {
            return self::FAILURE;
        }

        $this->step('Trusting CA certificate', function () use ($ca) {
            if (! $ca->extract()) {
                return Outcome::failed('could not read the CA');
            }

            if ($ca->isTrusted()) {
                return Outcome::skipped('already trusted');
            }

            return Outcome::succeeded($ca->trust(), failed: 'could not trust');
        });

        if ($this->hasFailed()) {
            return self::FAILURE;
        }

        $this->success('trusted');

        return self::SUCCESS;
    }
}
