<?php

declare(strict_types=1);

use App\Config\RouterConfig;
use App\Contracts\PortProbe;
use App\Env\SystemPortProbe;
use App\Router\Router;

describe('register()', function (): void {
    it('loads the router config from the global config file', function (): void {
        $home = scratchConfigHome();
        mkdir($home . '/rift', 0o777, true);
        file_put_contents($home . '/rift/config.yaml', "router:\n  name: custom-router\n");

        expect(app(RouterConfig::class)->name)->toBe('custom-router');
    });

    it('shares one router config across the application', function (): void {
        scratchConfigHome();

        expect(app(RouterConfig::class))->toBe(app(RouterConfig::class));
    });

    it('probes ports through the system', function (): void {
        expect(app(PortProbe::class)::class)->toBe(SystemPortProbe::class);
    });

    it('builds the router from the shared router config', function (): void {
        scratchConfigHome();

        expect(app(Router::class)->config)->toBe(app(RouterConfig::class));
    });

    it('builds a fresh router on every resolution', function (): void {
        scratchConfigHome();

        expect(app(Router::class))->not->toBe(app(Router::class));
    });
});
