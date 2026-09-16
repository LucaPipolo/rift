<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

describe('when the checkout is running behind the shared router', function (): void {
    beforeEach(function (): void {
        host(lifecycleProject());
        caddy(['/id/myapp' => ['@id' => 'myapp']]);
    });

    it('exits with success', function (): void {
        artisan('down')->assertExitCode(0);
    });

    it('stops the containers', function (): void {
        artisan('down')->run();

        Process::assertRan(lifecycleRan('docker compose down'));
    });

    it('removes the checkout route', function (): void {
        artisan('down')->run();

        expect(caddyWrites())->toBe([['method' => 'DELETE', 'path' => '/id/myapp', 'body' => null]]);
    });

    it('leaves the shared router running', function (): void {
        artisan('down')->run();

        Process::assertNotRan(lifecycleRan('docker stop'));
    });

    it('points to router:stop for the shared router', function (): void {
        artisan('down')->expectsOutputToContain('shared — use `rift router:stop`');
    });

    it('closes with a stopped message', function (): void {
        artisan('down')->expectsOutputToContain('stopped');
    });
});

describe('when Docker is not running', function (): void {
    beforeEach(function (): void {
        host(lifecycleProject(), ['*docker*info*' => Process::result(exitCode: 1)]);
    });

    it('exits with failure', function (): void {
        artisan('down')->assertExitCode(1);
    });

    it('explains that it could not reach a Docker daemon', function (): void {
        artisan('down')->expectsOutputToContain('could not reach one');
    });

    it('does not stop the containers', function (): void {
        artisan('down')->run();

        Process::assertNotRan(lifecycleRan('docker compose down'));
    });
});

describe('when the preset was never bootstrapped', function (): void {
    beforeEach(function (): void {
        host(lifecycleProject(config: "name: myapp\ndomain: myapp.test\npreset: sail\napp:\n  port: 80\n"));
        caddy([]);
    });

    it('says there is nothing to stop', function (): void {
        artisan('down')->expectsOutputToContain('nothing to stop');
    });

    it('does not run the preset down command', function (): void {
        artisan('down')->run();

        Process::assertNotRan(lifecycleRan('sail down'));
    });
});

describe('when the containers fail to stop', function (): void {
    beforeEach(function (): void {
        host(lifecycleProject(), ["*'compose' 'down'*" => Process::result(exitCode: 1)]);
        caddy([]);
    });

    it('exits with failure', function (): void {
        artisan('down')->assertExitCode(1);
    });

    it('still removes the route', function (): void {
        artisan('down')->expectsOutputToContain('Deregistering route');
    });

    it('does not close with a stopped message', function (): void {
        artisan('down')->doesntExpectOutputToContain('stopped');
    });
});

describe('when no route is registered', function (): void {
    beforeEach(function (): void {
        host(lifecycleProject());
        caddy([]);
    });

    it('says nothing is registered', function (): void {
        artisan('down')->expectsOutputToContain('nothing registered');
    });

    it('exits with success', function (): void {
        artisan('down')->assertExitCode(0);
    });
});

describe('when the route cannot be removed', function (): void {
    beforeEach(function (): void {
        host(lifecycleProject());
        Http::fake(fn (Request $request) => $request->method() === 'DELETE'
            ? Http::response('', 500)
            : Http::response('null', 200));
    });

    it('exits with failure', function (): void {
        artisan('down')->assertExitCode(1);
    });
});

describe('when the project pins its own router', function (): void {
    beforeEach(function (): void {
        $this->root = lifecycleProject(config: "name: myapp\ndomain: myapp.test\napp:\n  port: 80\nrouter: myapp-router\n");
    });

    it('skips stopping a router that is not running', function (): void {
        host($this->root, ['*docker*ps*' => '']);
        caddy([]);

        artisan('down')->expectsOutputToContain('not running');
    });

    it('skips stopping a router whose admin API does not answer', function (): void {
        host($this->root, ['*docker*ps*' => "myapp-router\n"]);
        caddy([]);

        artisan('down')->expectsOutputToContain('unreachable');
    });

    it('skips stopping a router other checkouts still route through', function (): void {
        host($this->root, ['*docker*ps*' => "myapp-router\n"]);
        caddy(['/config/apps/http/servers/main/routes' => [['@id' => 'myapp-feat-x']]]);

        artisan('down')->expectsOutputToContain('still in use');
    });

    it('stops a router nothing routes through any more', function (): void {
        host($this->root, ['*docker*ps*' => "myapp-router\n"]);
        caddy(['/config/apps/http/servers/main/routes' => []]);

        artisan('down')->run();

        Process::assertRan(lifecycleRan('docker stop myapp-router'));
    });

    it('says it could not stop the router when docker stop fails', function (): void {
        host($this->root, [
            '*docker*ps*' => "myapp-router\n",
            "*'stop' 'myapp-router'*" => Process::result(exitCode: 1),
        ]);
        caddy(['/config/apps/http/servers/main/routes' => []]);

        artisan('down')->expectsOutputToContain('could not stop');
    });

    it('exits with failure when docker stop fails', function (): void {
        host($this->root, [
            '*docker*ps*' => "myapp-router\n",
            "*'stop' 'myapp-router'*" => Process::result(exitCode: 1),
        ]);
        caddy(['/config/apps/http/servers/main/routes' => []]);

        artisan('down')->assertExitCode(1);
    });
});

describe('when the project declares stop hooks', function (): void {
    beforeEach(function (): void {
        $this->root = lifecycleProject(config: "name: myapp\ndomain: myapp.test\napp:\n  port: 80\n"
            . "hooks:\n  before_down:\n    run: save-things\n    on: host\n    label: Saving\n"
            . "  after_down:\n    run: tidy-things\n    on: host\n");
        caddy([]);
    });

    it('runs the before_down hook under its label', function (): void {
        host($this->root);

        artisan('down')->expectsOutputToContain('Saving');
    });

    it('runs the after_down hook under the stage default label', function (): void {
        host($this->root);

        artisan('down')->expectsOutputToContain('Cleaning up');
    });

    it('still stops the containers when the before_down hook fails', function (): void {
        host($this->root, ['*save-things*' => Process::result(exitCode: 1)]);

        artisan('down')->run();

        Process::assertRan(lifecycleRan('docker compose down'));
    });

    it('exits with failure when a stop hook fails', function (string $hook): void {
        host($this->root, [$hook => Process::result(exitCode: 1)]);

        artisan('down')->assertExitCode(1);
    })->with([
        'the before_down hook' => ['*save-things*'],
        'the after_down hook' => ['*tidy-things*'],
    ]);
});
