<?php

declare(strict_types=1);

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

describe('when Docker is not running', function (): void {
    beforeEach(function (): void {
        host(project(), ['*docker*info*' => Process::result(exitCode: 1)]);
    });

    it('explains that rift needs a running Docker daemon', function (): void {
        artisan('router:start')
            ->expectsOutputToContain('rift needs a running Docker daemon and could not reach one.');
    });

    it('exits with failure', function (): void {
        artisan('router:start')->assertExitCode(1);
    });
});

describe('when the router is already running', function (): void {
    beforeEach(function (): void {
        host(project());
        routerServing([]);
    });

    it('opens with the router name', function (): void {
        artisan('router:start')->expectsOutputToContain('rift-router');
    });

    it('leaves the existing network alone', function (): void {
        artisan('router:start')->expectsOutputToContain('already exists');
    });

    it('leaves the running router alone', function (): void {
        artisan('router:start')->expectsOutputToContain('already running');
    });

    it('leaves the loaded config alone', function (): void {
        artisan('router:start')->expectsOutputToContain('already loaded');
    });

    it('says the router is running', function (): void {
        artisan('router:start')->expectsOutputToContain('✓  running');
    });

    it('exits with success', function (): void {
        artisan('router:start')->assertExitCode(0);
    });
});

describe('when the router network is missing', function (): void {
    beforeEach(function (): void {
        host(project(), ['*network*ls*' => '']);
        routerServing([]);
    });

    it('creates the router network', function (): void {
        artisan('router:start')->run();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['docker', 'network', 'create', 'rift-router']);
    });

    it('exits with success', function (): void {
        artisan('router:start')->assertExitCode(0);
    });
});

describe('when the router network cannot be created', function (): void {
    beforeEach(function (): void {
        host(project(), ['*network*ls*' => '', '*network*create*' => Process::result(exitCode: 1)]);
        routerServing([]);
    });

    it('says the network could not be created', function (): void {
        artisan('router:start')->expectsOutputToContain('Could not create the rift-router network.');
    });

    it('does not say the router is running', function (): void {
        artisan('router:start')->doesntExpectOutputToContain('✓  running');
    });

    it('exits with failure', function (): void {
        artisan('router:start')->assertExitCode(1);
    });
});

describe('when the router has never been created', function (): void {
    beforeEach(function (): void {
        host(project(), ['*docker*ps*' => '']);
        caddy([]);
    });

    it('runs a new router container', function (): void {
        artisan('router:start')->run();

        Process::assertRan(fn (PendingProcess $process): bool => array_slice((array) $process->command, 0, 5)
            === ['docker', 'run', '-d', '--name', 'rift-router']);
    });

    it('loads the base server config', function (): void {
        artisan('router:start')->run();

        expect(caddyWrites()[0]['path'])->toBe('/load');
    });

    it('exits with success', function (): void {
        artisan('router:start')->assertExitCode(0);
    });
});

describe('when the router container is stopped', function (): void {
    beforeEach(function (): void {
        host(project(), ["*docker*'ps' '-a'*" => "rift-router\n", '*docker*ps*' => '']);
        caddy([]);
    });

    it('starts the existing router container', function (): void {
        artisan('router:start')->run();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['docker', 'start', 'rift-router']);
    });

    it('does not create a second router container', function (): void {
        artisan('router:start')->run();

        Process::assertNotRan(fn (PendingProcess $process): bool => ($process->command[1] ?? null) === 'run');
    });
});

describe('when another container holds ports 80 and 443', function (): void {
    beforeEach(function (): void {
        host(project(), ['*Ports*' => "traefik\t0.0.0.0:80->80/tcp\n", '*docker*ps*' => '']);
        caddy([]);
    });

    it('marks the start step as blocked by the ports', function (): void {
        artisan('router:start')->expectsOutputToContain('ports 80/443 in use');
    });

    it('names the container holding the ports', function (): void {
        artisan('router:start')->expectsOutputToContain('traefik already holds ports 80 and 443,');
    });

    it('shows how to stop the container holding the ports', function (): void {
        artisan('router:start')->expectsOutputToContain('docker stop traefik');
    });

    it('does not load the router config', function (): void {
        artisan('router:start')->doesntExpectOutputToContain('Loading router config');
    });

    it('exits with failure', function (): void {
        artisan('router:start')->assertExitCode(1);
    });
});

describe('when the router container fails to start', function (): void {
    beforeEach(function (): void {
        host(project(), ['*docker*ps*' => '', "*docker*'run'*" => Process::result(exitCode: 1)]);
        caddy([]);
    });

    it('says the router could not start', function (): void {
        artisan('router:start')->expectsOutputToContain('Could not start the router.');
    });

    it('does not report a port conflict', function (): void {
        artisan('router:start')->doesntExpectOutputToContain('already holds ports 80 and 443');
    });

    it('exits with failure', function (): void {
        artisan('router:start')->assertExitCode(1);
    });
});
