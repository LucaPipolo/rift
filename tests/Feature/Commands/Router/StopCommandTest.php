<?php

declare(strict_types=1);

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

describe('when Docker is not running', function (): void {
    beforeEach(function (): void {
        host(project(), ['*docker*info*' => Process::result(exitCode: 1)]);
    });

    it('explains that rift needs a running Docker daemon', function (): void {
        artisan('router:stop')
            ->expectsOutputToContain('rift needs a running Docker daemon and could not reach one.');
    });

    it('exits with failure', function (): void {
        artisan('router:stop')->assertExitCode(1);
    });
});

describe('when the router is not running', function (): void {
    beforeEach(function (): void {
        host(project(), ['*docker*ps*' => '']);
    });

    it('skips stopping the router', function (): void {
        artisan('router:stop')->expectsOutputToContain('not running');
    });

    it('does not run docker stop', function (): void {
        artisan('router:stop')->run();

        Process::assertNotRan(fn (PendingProcess $process): bool => ($process->command[1] ?? null) === 'stop');
    });

    it('exits with success', function (): void {
        artisan('router:stop')->assertExitCode(0);
    });
});

describe('when the router serves no route', function (): void {
    beforeEach(function (): void {
        host(project());
        routerServing([]);
    });

    it('opens with the router name', function (): void {
        artisan('router:stop')->expectsOutputToContain('rift-router');
    });

    it('stops the router container', function (): void {
        artisan('router:stop')->run();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['docker', 'stop', 'rift-router']);
    });

    it('does not mention dropped routes', function (): void {
        artisan('router:stop')->doesntExpectOutputToContain('dropped');
    });

    it('says the router stopped', function (): void {
        artisan('router:stop')->expectsOutputToContain('✓  stopped');
    });

    it('exits with success', function (): void {
        artisan('router:stop')->assertExitCode(0);
    });
});

describe('when the router is still serving routes', function (): void {
    beforeEach(function (): void {
        host(project());
        routerServing([statusRoute('myapp', ['myapp.test']), statusRoute('shop', ['shop.test'])]);
    });

    it('counts the routes it dropped', function (): void {
        artisan('router:stop')->expectsOutputToContain('2 route(s) dropped');
    });

    it('names the routes it was serving with -v', function (): void {
        artisan('router:stop -v')->expectsOutputToContain('still serving: myapp, shop');
    });

    it('keeps the routes it was serving out of the output without -v', function (): void {
        artisan('router:stop')->doesntExpectOutputToContain('still serving');
    });
});

describe('when the router cannot be stopped', function (): void {
    beforeEach(function (): void {
        host(project(), ['*docker*stop*' => Process::result(exitCode: 1)]);
        routerServing([]);
    });

    it('says it could not stop the router', function (): void {
        artisan('router:stop')->expectsOutputToContain('could not stop');
    });

    it('does not say the router stopped', function (): void {
        artisan('router:stop')->doesntExpectOutputToContain('✓  stopped');
    });

    it('exits with failure', function (): void {
        artisan('router:stop')->assertExitCode(1);
    });
});
