<?php

declare(strict_types=1);

use App\Config\PortBinding;
use App\Config\RouterConfig;
use App\Exceptions\AddressNotAssignedException;
use App\Exceptions\PortConflictException;
use App\Exceptions\RouterException;
use App\Router\CaddyAdmin;
use App\Router\PortHolder;
use App\Router\Router;
use Illuminate\Http\Client\Request;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;

describe('for()', function (): void {
    it('builds a router for the given config', function (): void {
        $config = new RouterConfig(name: 'other');

        expect(Router::for($config)->config)->toBe($config);
    });

    it('points the admin client at the configured admin API', function (): void {
        caddy([]);

        Router::for(new RouterConfig(admin: 'http://router.internal:2020'))->admin->reachable();

        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://router.internal:2020/config/');
    });
});

describe('isRunning()', function (): void {
    it('returns true when the router container is running', function (): void {
        Process::fake(['*docker*ps*' => "myapp-app-1\nrift-router\n", '*' => '']);

        expect(routerUsingAdmin(caddy([]))->isRunning())->toBeTrue();
    });

    it('returns false when the router container is not running', function (): void {
        Process::fake(['*docker*ps*' => "myapp-app-1\n", '*' => '']);

        expect(routerUsingAdmin(caddy([]))->isRunning())->toBeFalse();
    });
});

describe('blocker()', function (): void {
    it('returns the other container that holds the web ports', function (): void {
        Process::fake(['*Ports*' => "nginx\t0.0.0.0:80->80/tcp\n", '*' => '']);

        expect(routerUsingAdmin(caddy([]))->blocker())->toEqual(new PortHolder('nginx'));
    });

    it('returns a host process listening on a web port', function (string $port): void {
        Process::fake(["*lsof*-iTCP:{$port}*" => "p123\ncnginx\n", '*' => '']);

        expect(routerUsingAdmin(caddy([]))->blocker())->toEqual(new PortHolder('nginx', onHost: true));
    })->with(['80', '443']);

    it('prefers the container over a host process', function (): void {
        Process::fake(['*Ports*' => "traefik\t0.0.0.0:443->443/tcp\n", '*lsof*' => "p123\ncnginx\n", '*' => '']);

        expect(routerUsingAdmin(caddy([]))->blocker())->toEqual(new PortHolder('traefik'));
    });

    it('probes the host for listeners on the bind address and configured ports', function (): void {
        Process::fake(['*' => '']);

        routerUsingAdmin(caddy([]), new RouterConfig(binding: new PortBinding(address: '127.0.0.2', http: 8080, https: 8443)))->blocker();

        Process::assertRan(['lsof', '-nP', '-iTCP@127.0.0.2:8080', '-sTCP:LISTEN', '-Fc']);
        Process::assertRan(['lsof', '-nP', '-iTCP@127.0.0.2:8443', '-sTCP:LISTEN', '-Fc']);
    });

    it('ignores containers on another address than the bind address', function (): void {
        Process::fake(['*Ports*' => "nginx\t127.0.0.1:443->443/tcp\n", '*' => '']);

        expect(routerUsingAdmin(caddy([]), new RouterConfig(binding: new PortBinding(address: '127.0.0.2')))->blocker())->toBeNull();
    });

    it('ignores host listeners when the router itself holds the web ports', function (): void {
        Process::fake(['*Ports*' => "rift-router\t0.0.0.0:80->80/tcp\n", '*lsof*' => "p123\ncnginx\n", '*' => '']);

        expect(routerUsingAdmin(caddy([]))->blocker())->toBeNull();
    });

    it('returns null when the router is running', function (): void {
        Process::fake(['*Ports*' => "nginx\t0.0.0.0:80->80/tcp\n", '*docker*ps*' => "rift-router\n", '*lsof*' => "p123\ncnginx\n"]);

        expect(routerUsingAdmin(caddy([]))->blocker())->toBeNull();
    });

    it('returns null when the router itself holds the web ports', function (): void {
        Process::fake(['*Ports*' => "rift-router\t0.0.0.0:80->80/tcp\n", '*' => '']);

        expect(routerUsingAdmin(caddy([]))->blocker())->toBeNull();
    });

    it('returns null when nothing holds the web ports', function (): void {
        Process::fake(['*' => '']);

        expect(routerUsingAdmin(caddy([]))->blocker())->toBeNull();
    });
});

describe('ensureNetwork()', function (): void {
    it('returns false when the network already exists', function (): void {
        Process::fake(['*docker*network*ls*' => "rift-router\n", '*' => '']);

        expect(routerUsingAdmin(caddy([]))->ensureNetwork())->toBeFalse();
    });

    it('does not create a network that already exists', function (): void {
        Process::fake(['*docker*network*ls*' => "rift-router\n", '*' => '']);

        routerUsingAdmin(caddy([]))->ensureNetwork();

        Process::assertNotRan(fn (PendingProcess $process): bool => $process->command === ['docker', 'network', 'create', 'rift-router']);
    });

    it('creates the router network when it is missing', function (): void {
        Process::fake(['*' => '']);

        routerUsingAdmin(caddy([]))->ensureNetwork();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['docker', 'network', 'create', 'rift-router']);
    });

    it('returns true when it creates the network', function (): void {
        Process::fake(['*' => '']);

        expect(routerUsingAdmin(caddy([]))->ensureNetwork())->toBeTrue();
    });

    it('throws a RouterException when the network cannot be created', function (): void {
        Process::fake(['*network*create*' => Process::result(exitCode: 1), '*' => '']);

        routerUsingAdmin(caddy([]))->ensureNetwork();
    })->throws(RouterException::class, 'Could not create the rift-router network.');
});

describe('ensureRunning()', function (): void {
    it('returns false when the router is already running', function (): void {
        Process::fake(['*docker*ps*' => "rift-router\n", '*' => '']);

        expect(routerUsingAdmin(caddy([]))->ensureRunning())->toBeFalse();
    });

    it('throws a PortConflictException when another container holds the web ports', function (): void {
        Process::fake(['*Ports*' => "nginx\t0.0.0.0:443->443/tcp\n", '*' => '']);

        routerUsingAdmin(caddy([]))->ensureRunning();
    })->throws(PortConflictException::class, 'nginx already holds ports 80 and 443.');

    it('throws a PortConflictException when a host process holds the web ports', function (): void {
        Process::fake(['*lsof*' => "p123\ncnginx\n", '*' => '']);

        routerUsingAdmin(caddy([]))->ensureRunning();
    })->throws(PortConflictException::class, 'nginx already holds ports 80 and 443.');

    it('does not start the router when a host process holds the web ports', function (): void {
        Process::fake(['*lsof*' => "p123\ncnginx\n", '*' => '']);

        expect(fn () => routerUsingAdmin(caddy([]))->ensureRunning())->toThrow(PortConflictException::class);

        Process::assertNotRan(fn (PendingProcess $process): bool => ($process->command[1] ?? null) === 'run');
    });

    it('starts the router container when it already exists', function (): void {
        Process::fake(["'docker' 'ps' '-a' '--format' '{{.Names}}'" => "rift-router\n", '*' => '']);

        routerUsingAdmin(caddy([]))->ensureRunning();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['docker', 'start', 'rift-router']);
    });

    it('creates the router container with its ports, volumes and admin API when it does not exist', function (): void {
        Process::fake(['*' => '']);

        routerUsingAdmin(caddy([]))->ensureRunning();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
            'docker', 'run', '-d',
            '--name', 'rift-router',
            '--network', 'rift-router',
            '--restart', 'unless-stopped',
            '--label', 'dev.rift.binding=:80:443',
            '-p', '80:80',
            '-p', '443:443',
            '-p', '443:443/udp',
            '-p', '127.0.0.1:2019:2019',
            '-v', 'rift-router-data:/data',
            '-v', 'rift-router-config:/config',
            '-e', 'CADDY_ADMIN=0.0.0.0:2019',
            'caddy:latest',
        ]);
    });

    it('publishes the admin API on this machine only', function (): void {
        Process::fake(['*' => '']);

        routerUsingAdmin(caddy([]))->ensureRunning();

        Process::assertRan(fn (PendingProcess $process): bool => is_array($process->command)
            && in_array('127.0.0.1:2019:2019', $process->command, true)
            && ! in_array('2019:2019', $process->command, true));
    });

    it('publishes the configured web ports on the bind address', function (): void {
        Process::fake(['*' => '']);

        routerUsingAdmin(caddy([]), new RouterConfig(binding: new PortBinding(address: '127.0.0.2', http: 8080, https: 8443)))->ensureRunning();

        Process::assertRan(fn (PendingProcess $process): bool => is_array($process->command)
            && array_slice($process->command, 9, 10) === [
                '--label', 'dev.rift.binding=127.0.0.2:8080:8443',
                '-p', '127.0.0.2:8080:80',
                '-p', '127.0.0.2:8443:443',
                '-p', '127.0.0.2:8443:443/udp',
                '-p', '127.0.0.1:2019:2019',
            ]);
    });

    it('starts an existing container created with the configured bindings', function (): void {
        Process::fake([
            "'docker' 'ps' '-a' '--format' '{{.Names}}'" => "rift-router\n",
            '*docker*inspect*' => "127.0.0.2:8080:8443\n",
            '*' => '',
        ]);

        routerUsingAdmin(caddy([]), new RouterConfig(binding: new PortBinding(address: '127.0.0.2', http: 8080, https: 8443)))->ensureRunning();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['docker', 'start', 'rift-router']);
    });

    it('recreates an existing container created with other bindings', function (string $label): void {
        Process::fake([
            "'docker' 'ps' '-a' '--format' '{{.Names}}'" => "rift-router\n",
            '*docker*inspect*' => $label,
            '*' => '',
        ]);

        routerUsingAdmin(caddy([]), new RouterConfig(binding: new PortBinding(https: 8443)))->ensureRunning();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['docker', 'rm', '-f', 'rift-router']);
        Process::assertRan(fn (PendingProcess $process): bool => ($process->command[1] ?? null) === 'run');
    })->with([
        'other bindings' => ['127.0.0.2:80:443'],
        'no label, from before bindings were configurable' => [''],
    ]);

    it('does not create a new container when the stale one cannot be removed', function (): void {
        Process::fake([
            "'docker' 'ps' '-a' '--format' '{{.Names}}'" => "rift-router\n",
            '*docker*rm*' => Process::result(exitCode: 1),
            '*' => '',
        ]);

        expect(fn () => routerUsingAdmin(caddy([]), new RouterConfig(binding: new PortBinding(https: 8443)))->ensureRunning())
            ->toThrow(RouterException::class, 'Could not start the router.');

        Process::assertNotRan(fn (PendingProcess $process): bool => ($process->command[1] ?? null) === 'run');
    });

    it('throws an AddressNotAssignedException when the bind address is missing', function (): void {
        Process::fake(['*' => '']);

        routerUsingAdmin(caddy([]), new RouterConfig(binding: new PortBinding('127.0.0.3')))->ensureRunning();
    })->throws(AddressNotAssignedException::class, '127.0.0.3 is not assigned to this machine. Assign 127.0.0.3.');

    it('does not start the router when the bind address is missing', function (): void {
        Process::fake(['*' => '']);

        expect(fn () => routerUsingAdmin(caddy([]), new RouterConfig(binding: new PortBinding('127.0.0.3')))->ensureRunning())
            ->toThrow(AddressNotAssignedException::class);

        Process::assertNotRan(fn (PendingProcess $process): bool => ($process->command[1] ?? null) === 'run');
    });

    it('names the configured ports when another container holds them', function (): void {
        Process::fake(['*Ports*' => "nginx\t0.0.0.0:8443->443/tcp\n", '*' => '']);

        routerUsingAdmin(caddy([]), new RouterConfig(binding: new PortBinding(http: 8080, https: 8443)))->ensureRunning();
    })->throws(PortConflictException::class, 'nginx already holds ports 8080 and 8443.');

    it('returns true when the router starts and its admin API answers', function (): void {
        Process::fake(['*' => '']);

        expect(routerUsingAdmin(caddy([]))->ensureRunning())->toBeTrue();
    });

    it('throws a RouterException when the router container cannot start', function (): void {
        Process::fake(['*docker*run*' => Process::result(exitCode: 125), '*' => '']);

        routerUsingAdmin(caddy([]))->ensureRunning();
    })->throws(RouterException::class, 'Could not start the router.');

    it('throws a RouterException when the admin API never answers', function (): void {
        Sleep::fake();
        Process::fake(['*' => '']);
        Http::fake(['*' => Http::failedConnection()]);

        routerUsingAdmin(new CaddyAdmin(new RouterConfig()))->ensureRunning();
    })->throws(RouterException::class, 'The router started but its admin API never answered.');
});

describe('isStale()', function (): void {
    it('returns false when the container was created with the configured bindings', function (): void {
        Process::fake(['*docker*inspect*' => "127.0.0.2:8080:8443\n", '*' => '']);

        expect(routerUsingAdmin(caddy([]), new RouterConfig(binding: new PortBinding(address: '127.0.0.2', http: 8080, https: 8443)))->isStale())->toBeFalse();
    });

    it('returns true when the container was created with other bindings', function (): void {
        Process::fake(['*docker*inspect*' => ":80:443\n", '*' => '']);

        expect(routerUsingAdmin(caddy([]), new RouterConfig(binding: new PortBinding(https: 8443)))->isStale())->toBeTrue();
    });

    it('treats an unlabelled container as created with the defaults', function (): void {
        Process::fake(['*' => '']);

        expect(routerUsingAdmin(caddy([]))->isStale())->toBeFalse();
    });
});

describe('stop()', function (): void {
    it('stops the router container', function (): void {
        Process::fake(['*' => '']);

        routerUsingAdmin(caddy([]))->stop();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['docker', 'stop', 'rift-router']);
    });

    it('returns false when the container cannot be stopped', function (): void {
        Process::fake(['*' => Process::result(exitCode: 1)]);

        expect(routerUsingAdmin(caddy([]))->stop())->toBeFalse();
    });
});

describe('register()', function (): void {
    it('registers a route for a checkout the router has never seen', function (): void {
        routerUsingAdmin(caddy([]))->register(myapp(slug: 'feat-x'));

        expect(caddyWrites()[0]['body'])->toMatchArray([
            '@id' => 'myapp-feat-x',
            'match' => [['host' => ['feat-x.myapp.test']]],
        ]);
    });

    it('proxies a new route to the checkout app container', function (): void {
        routerUsingAdmin(caddy([]))->register(myapp(slug: 'feat-x'));

        expect(data_get(caddyWrites()[0]['body'], 'handle.0.routes.0.handle.0.upstreams'))->toBe([['dial' => 'myapp-feat-x-app-1:80']]);
    });

    it('returns true when it registers a new route', function (): void {
        expect(routerUsingAdmin(caddy([]))->register(myapp(slug: 'feat-x')))->toBeTrue();
    });

    it('updates a registered route when the project changes its aliases', function (): void {
        routerUsingAdmin(caddy([
            '/id/myapp' => ['@id' => 'myapp'],
            '/id/myapp/match/0/host' => ['myapp.test'],
        ]))->register(myapp(['hosts' => ['api', 'admin']]));

        expect(caddyWrites())->toBe([[
            'method' => 'PATCH',
            'path' => '/id/myapp/match/0/host',
            'body' => ['myapp.test', 'api.myapp.test', 'admin.myapp.test'],
        ]]);
    });

    it('returns true when it updates the route hosts', function (): void {
        $router = routerUsingAdmin(caddy([
            '/id/myapp' => ['@id' => 'myapp'],
            '/id/myapp/match/0/host' => ['myapp.test'],
        ]));

        expect($router->register(myapp(['hosts' => ['api']])))->toBeTrue();
    });

    it('leaves a registered route alone when its aliases already match', function (): void {
        routerUsingAdmin(caddy([
            '/id/myapp' => ['@id' => 'myapp'],
            '/id/myapp/match/0/host' => ['myapp.test', 'api.myapp.test'],
        ]))->register(myapp(['hosts' => ['api']]));

        expect(caddyWrites())->toBeEmpty();
    });

    it('returns false when the registered route already matches', function (): void {
        $router = routerUsingAdmin(caddy([
            '/id/myapp' => ['@id' => 'myapp'],
            '/id/myapp/match/0/host' => ['myapp.test', 'api.myapp.test'],
        ]));

        expect($router->register(myapp(['hosts' => ['api']])))->toBeFalse();
    });
});

describe('deregister()', function (): void {
    it('deletes the checkout route', function (): void {
        Process::fake(['*docker*ps*' => "rift-router\n", '*' => '']);

        routerUsingAdmin(caddy(['/id/myapp-feat-x' => ['@id' => 'myapp-feat-x']]))->deregister(myapp(slug: 'feat-x'));

        expect(caddyWrites())->toBe([[
            'method' => 'DELETE',
            'path' => '/id/myapp-feat-x',
            'body' => null,
        ]]);
    });

    it('returns true when it removes the route', function (): void {
        Process::fake(['*docker*ps*' => "rift-router\n", '*' => '']);

        expect(routerUsingAdmin(caddy(['/id/myapp' => ['@id' => 'myapp']]))->deregister(myapp()))->toBeTrue();
    });

    it('returns false when the checkout has no route', function (): void {
        Process::fake(['*docker*ps*' => "rift-router\n", '*' => '']);

        expect(routerUsingAdmin(caddy([]))->deregister(myapp()))->toBeFalse();
    });

    it('returns false without calling the admin API when the router is not running', function (): void {
        Process::fake(['*' => '']);

        routerUsingAdmin(caddy(['/id/myapp' => ['@id' => 'myapp']]))->deregister(myapp());

        Http::assertNothingSent();
    });

    it('returns false when the router is not running', function (): void {
        Process::fake(['*' => '']);

        expect(routerUsingAdmin(caddy(['/id/myapp' => ['@id' => 'myapp']]))->deregister(myapp()))->toBeFalse();
    });
});
