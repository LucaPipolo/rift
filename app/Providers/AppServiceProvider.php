<?php

declare(strict_types=1);

namespace App\Providers;

use App\Config\ProjectRegistry;
use App\Config\RouterConfig;
use App\Contracts\Network;
use App\Contracts\PortProbe;
use App\Contracts\Terminal;
use App\Dashboard\Companion;
use App\Dashboard\Input;
use App\Dashboard\Rift;
use App\Dashboard\SystemTerminal;
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

        // Bound per resolution, so it follows XDG_CONFIG_HOME like the router config's path does.
        $this->app->bind(ProjectRegistry::class, fn () => new ProjectRegistry(ProjectRegistry::path()));

        $this->app->bind(PortProbe::class, SystemPortProbe::class);

        $this->app->bind(Network::class, SystemNetwork::class);

        $this->app->bind(Terminal::class, SystemTerminal::class);

        $this->app->bind(Input::class, fn () => new Input(STDIN));

        $this->app->bind(Companion::class, fn (Application $app) => new Companion($app->make(Rift::class), (string) getenv('PATH')));

        $this->app->bind(Rift::class, function (): Rift {
            $argv = $_SERVER['argv'] ?? [];
            $script = is_array($argv) && is_string($argv[0] ?? null) ? $argv[0] : 'rift';

            // The native binary leaves PHP_BINARY empty and reports its own path through the micro runtime.
            $binary = PHP_SAPI === 'micro' && function_exists('micro_get_self_filename') ? micro_get_self_filename() : PHP_BINARY;

            // Children start in a checkout, so a script path relative to here would not resolve there.
            return new Rift(PHP_SAPI, $binary, realpath($script) ?: $script);
        });

        $this->app->bind(Router::class, fn (Application $app) => Router::for($app->make(RouterConfig::class)));
    }
}
