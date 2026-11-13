<?php

declare(strict_types=1);

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

    it('keeps the project registry beside the global config', function (): void {
        $home = scratchConfigHome();

        expect(app(ProjectRegistry::class)->path)->toBe($home . '/rift/projects.yaml');
    });

    it('probes ports through the system', function (): void {
        expect(app(PortProbe::class)::class)->toBe(SystemPortProbe::class);
    });

    it('asks the system about the network', function (): void {
        expect(app(Network::class)::class)->toBe(SystemNetwork::class);
    });

    it('draws on the system terminal', function (): void {
        expect(app(Terminal::class)::class)->toBe(SystemTerminal::class);
    });

    it('reads rift-dash\'s requests from standard input', function (): void {
        expect(app(Input::class))->toEqual(new Input(STDIN));
    });

    it('unpacks the rift-dash the binary carries, or looks beside rift, then on the PATH', function (): void {
        expect(app(Companion::class))->toEqual(new Companion(app(Rift::class), (string) getenv('PATH'), base_path('dash/rift-dash'), Companion::cacheDirectory()));
    });

    it('runs rift again through this PHP binary', function (): void {
        expect(app(Rift::class)->interactive('up')[0])->toBe(PHP_BINARY);
    });

    it('runs rift again from an absolute script path', function (): void {
        $script = app(Rift::class)->interactive('up')[1];

        expect($script)->toStartWith('/')->toBe(realpath($script));
    });

    it('runs rift again as `rift` when PHP was not started with a script', function (): void {
        $argv = $_SERVER['argv'];
        $_SERVER['argv'] = [];
        chdir(scratch());

        try {
            expect(app(Rift::class)->interactive('up')[1])->toBe('rift');
        } finally {
            $_SERVER['argv'] = $argv;
        }
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
