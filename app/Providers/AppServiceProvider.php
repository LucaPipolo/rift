<?php

declare(strict_types=1);

namespace App\Providers;

use App\Config\RouterConfig;
use App\Contracts\Network;
use App\Contracts\PortProbe;
use App\Env\SystemNetwork;
use App\Env\SystemPortProbe;
use App\Router\Router;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Registers rift's services in the container.
 */
final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(RouterConfig::class, fn () => RouterConfig::load());

        $this->app->bind(PortProbe::class, SystemPortProbe::class);

        $this->app->bind(Network::class, SystemNetwork::class);

        $this->app->bind(Router::class, fn (Application $app) => Router::for($app->make(RouterConfig::class)));
    }
}
