<?php

declare(strict_types=1);

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

describe('when Docker is not running', function (): void {
    beforeEach(function (): void {
        host(project(), ['*docker*info*' => Process::result(exitCode: 1)]);
    });

    it('explains that rift needs a running Docker daemon', function (): void {
        artisan('router:restart')
            ->expectsOutputToContain('rift needs a running Docker daemon and could not reach one.');
    });

    it('exits with failure', function (): void {
        artisan('router:restart')->assertExitCode(1);
    });
});

describe('when the router is running', function (): void {
    beforeEach(function (): void {
        host(project());
        routerServing([]);
    });

    it('opens with the router name', function (): void {
        artisan('router:restart')->expectsOutputToContain('rift-router');
    });

    it('stops the router', function (): void {
        artisan('router:restart')->run();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['docker', 'stop', 'rift-router']);
    });

    it('starts the router again', function (): void {
        artisan('router:restart')->expectsOutputToContain('Starting router');
    });

    it('says the router is running', function (): void {
        artisan('router:restart')->expectsOutputToContain('✓  running');
    });

    it('exits with success', function (): void {
        artisan('router:restart')->assertExitCode(0);
    });
});

describe('when the router cannot be stopped', function (): void {
    beforeEach(function (): void {
        host(project(), ['*docker*stop*' => Process::result(exitCode: 1)]);
        routerServing([]);
    });

    it('does not start the router', function (): void {
        artisan('router:restart')->doesntExpectOutputToContain('Setting up network');
    });

    it('exits with failure', function (): void {
        artisan('router:restart')->assertExitCode(1);
    });
});

describe('when the router cannot be started', function (): void {
    beforeEach(function (): void {
        host(project(), ['*docker*ps*' => '', "*docker*'run'*" => Process::result(exitCode: 1)]);
        caddy([]);
    });

    it('says the router could not start', function (): void {
        artisan('router:restart')->expectsOutputToContain('Could not start the router.');
    });

    it('does not say the router is running', function (): void {
        artisan('router:restart')->doesntExpectOutputToContain('✓  running');
    });

    it('exits with failure', function (): void {
        artisan('router:restart')->assertExitCode(1);
    });
});
