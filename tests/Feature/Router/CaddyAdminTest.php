<?php

declare(strict_types=1);

use App\Config\RouterConfig;
use App\Router\CaddyAdmin;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

describe('reachable()', function (): void {
    it('returns true when the admin API answers', function (): void {
        expect(caddy([])->reachable())->toBeTrue();
    });

    it('calls the admin API at the configured address', function (): void {
        caddy([]);

        (new CaddyAdmin(new RouterConfig(admin: 'http://router.internal:2020')))->reachable();

        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://router.internal:2020/config/');
    });

    it('returns false when the admin API answers with an error', function (): void {
        Http::fake(['*' => Http::response('', 500)]);

        expect((new CaddyAdmin(new RouterConfig()))->reachable())->toBeFalse();
    });

    it('returns false when the admin API refuses the connection', function (): void {
        Http::fake(['*' => Http::failedConnection()]);

        expect((new CaddyAdmin(new RouterConfig()))->reachable())->toBeFalse();
    });
});

describe('waitUntilReachable()', function (): void {
    it('returns true without sleeping when the admin API answers at once', function (): void {
        Sleep::fake();

        caddy([])->waitUntilReachable();

        Sleep::assertNeverSlept();
    });

    it('returns true once the admin API starts answering', function (): void {
        Sleep::fake();
        Http::fakeSequence()->pushFailedConnection()->pushFailedConnection()->push('null');

        expect((new CaddyAdmin(new RouterConfig()))->waitUntilReachable())->toBeTrue();
    });

    it('sleeps one second between attempts', function (): void {
        Sleep::fake();
        Http::fakeSequence()->pushFailedConnection()->pushFailedConnection()->push('null');

        (new CaddyAdmin(new RouterConfig()))->waitUntilReachable();

        Sleep::assertSequence([Sleep::for(1)->second(), Sleep::for(1)->second()]);
    });

    it('returns false when the admin API never answers in time', function (): void {
        Sleep::fake();
        Http::fake(['*' => Http::failedConnection()]);

        expect((new CaddyAdmin(new RouterConfig()))->waitUntilReachable(3))->toBeFalse();
    });

    it('tries once per second it may wait', function (): void {
        Sleep::fake();
        Http::fake(['*' => Http::failedConnection()]);

        (new CaddyAdmin(new RouterConfig()))->waitUntilReachable(3);

        Sleep::assertSleptTimes(3);
    });
});

describe('ensureServer()', function (): void {
    it('returns true when it loads the base server into an empty router', function (): void {
        expect(caddy([])->ensureServer())->toBeTrue();
    });

    it('loads the base server through the load endpoint', function (): void {
        caddy([])->ensureServer();

        expect(caddyWrites()[0]['path'])->toBe('/load');
    });

    it('loads a server listening on ports 80 and 443 with an empty route list', function (): void {
        caddy([])->ensureServer();

        expect(data_get(caddyWrites()[0]['body'], 'apps.http.servers.main'))->toMatchArray([
            'listen' => [':80', ':443'],
            'routes' => [],
        ]);
    });

    it('returns false when a server is already loaded', function (): void {
        expect(caddy(['/config/apps/http/servers/main' => ['routes' => []]])->ensureServer())->toBeFalse();
    });

    it('never reloads config over a router another project is already using', function (): void {
        // POST /load replaces everything. On a shared router that would silently
        // drop every route the other projects registered.
        caddy(['/config/apps/http/servers/main' => ['routes' => []]])->ensureServer();

        expect(caddyWrites())->toBeEmpty();
    });

    it('throws a RequestException when the router rejects the config', function (): void {
        Http::fake(fn (Request $request) => $request->method() === 'GET' ? Http::response('null') : Http::response('', 400));

        (new CaddyAdmin(new RouterConfig()))->ensureServer();
    })->throws(RequestException::class);
});

describe('ensureCertificateFor()', function (): void {
    it('creates the first TLS policy when no project has registered one', function (mixed $policies): void {
        caddy([
            '/config/apps/tls/automation/policies' => $policies,
            '/config/apps/tls/certificates/automate' => [],
        ])->ensureCertificateFor('lcp-web.test');

        expect(caddyWrites()[0])->toBe([
            'method' => 'POST',
            'path' => '/config/apps/tls/automation/policies',
            'body' => [
                'subjects' => ['lcp-web.test', '*.lcp-web.test'],
                'issuers' => [['module' => 'internal']],
            ],
        ]);
    })->with([
        'an empty policy list' => [[]],
        'an unset policy list' => [null],
    ]);

    it('returns true when it creates the first TLS policy', function (): void {
        $admin = caddy([
            '/config/apps/tls/automation/policies' => [],
            '/config/apps/tls/certificates/automate' => ['lcp-web.test', '*.lcp-web.test'],
        ]);

        expect($admin->ensureCertificateFor('lcp-web.test'))->toBeTrue();
    });

    it('appends a second project domain to the first policy and the automation list', function (): void {
        caddy([
            '/config/apps/tls/automation/policies' => [[
                'subjects' => ['lcp-web.test', '*.lcp-web.test'],
                'issuers' => [['module' => 'internal']],
            ]],
            '/config/apps/tls/certificates/automate' => ['lcp-web.test', '*.lcp-web.test'],
        ])->ensureCertificateFor('myapp.test');

        expect(array_map(fn (array $write): array => [$write['path'], $write['body']], caddyWrites()))->toBe([
            ['/config/apps/tls/automation/policies/0/subjects', 'myapp.test'],
            ['/config/apps/tls/automation/policies/0/subjects', '*.myapp.test'],
            ['/config/apps/tls/certificates/automate', 'myapp.test'],
            ['/config/apps/tls/certificates/automate', '*.myapp.test'],
        ]);
    });

    it('returns true when it appends a second project domain', function (): void {
        $admin = caddy([
            '/config/apps/tls/automation/policies' => [['subjects' => ['lcp-web.test', '*.lcp-web.test']]],
            '/config/apps/tls/certificates/automate' => ['lcp-web.test', '*.lcp-web.test'],
        ]);

        expect($admin->ensureCertificateFor('myapp.test'))->toBeTrue();
    });

    it('appends only the subjects that are missing', function (): void {
        caddy([
            '/config/apps/tls/automation/policies' => [['subjects' => ['myapp.test']]],
            '/config/apps/tls/certificates/automate' => ['myapp.test', '*.myapp.test'],
        ])->ensureCertificateFor('myapp.test');

        expect(caddyWrites())->toBe([[
            'method' => 'POST',
            'path' => '/config/apps/tls/automation/policies/0/subjects',
            'body' => '*.myapp.test',
        ]]);
    });

    it('appends every subject to a first policy that has no subjects', function (): void {
        caddy([
            '/config/apps/tls/automation/policies' => [['issuers' => [['module' => 'internal']]]],
            '/config/apps/tls/certificates/automate' => ['myapp.test', '*.myapp.test'],
        ])->ensureCertificateFor('myapp.test');

        expect(array_column(caddyWrites(), 'body'))->toBe(['myapp.test', '*.myapp.test']);
    });

    it('returns true when only the automation list is missing a subject', function (): void {
        $admin = caddy([
            '/config/apps/tls/automation/policies' => [['subjects' => ['myapp.test', '*.myapp.test']]],
            '/config/apps/tls/certificates/automate' => ['myapp.test'],
        ]);

        expect($admin->ensureCertificateFor('myapp.test'))->toBeTrue();
    });

    it('creates the automation list when the router has none', function (): void {
        caddy([
            '/config/apps/tls/automation/policies' => [['subjects' => ['myapp.test', '*.myapp.test']]],
        ])->ensureCertificateFor('myapp.test');

        expect(caddyWrites())->toBe([[
            'method' => 'PATCH',
            'path' => '/config/apps/tls/certificates',
            'body' => ['automate' => ['myapp.test', '*.myapp.test']],
        ]]);
    });

    it('returns true when it creates the automation list', function (): void {
        $admin = caddy([
            '/config/apps/tls/automation/policies' => [['subjects' => ['myapp.test', '*.myapp.test']]],
        ]);

        expect($admin->ensureCertificateFor('myapp.test'))->toBeTrue();
    });

    it('writes nothing when the domain is already covered', function (): void {
        caddy([
            '/config/apps/tls/automation/policies' => [['subjects' => ['myapp.test', '*.myapp.test']]],
            '/config/apps/tls/certificates/automate' => ['myapp.test', '*.myapp.test'],
        ])->ensureCertificateFor('myapp.test');

        expect(caddyWrites())->toBeEmpty();
    });

    it('returns false when the base domain wildcard already covers every worktree', function (): void {
        // feat-x.myapp.test needs no registration of its own.
        $admin = caddy([
            '/config/apps/tls/automation/policies' => [['subjects' => ['myapp.test', '*.myapp.test']]],
            '/config/apps/tls/certificates/automate' => ['myapp.test', '*.myapp.test'],
        ]);

        expect($admin->ensureCertificateFor('myapp.test'))->toBeFalse();
    });
});

describe('ensureWildcardFor()', function (): void {
    it('adds only a wildcard for a worktree, never a bare subject', function (): void {
        // A bare feat-x.myapp.test subject would be read by certifiedDomains() as
        // a project of its own, and `rift status` would list it apart from myapp.
        caddy([
            '/config/apps/tls/automation/policies' => [['subjects' => ['myapp.test', '*.myapp.test']]],
            '/config/apps/tls/certificates/automate' => ['myapp.test', '*.myapp.test'],
        ])->ensureWildcardFor('feat-x.myapp.test');

        expect(array_column(caddyWrites(), 'body'))->toBe(['*.feat-x.myapp.test', '*.feat-x.myapp.test']);
    });

    it('returns true when the wildcard is missing', function (): void {
        $admin = caddy([
            '/config/apps/tls/automation/policies' => [['subjects' => ['myapp.test', '*.myapp.test']]],
            '/config/apps/tls/certificates/automate' => ['myapp.test', '*.myapp.test'],
        ]);

        expect($admin->ensureWildcardFor('feat-x.myapp.test'))->toBeTrue();
    });

    it('returns false when the wildcard is already certified', function (): void {
        $admin = caddy([
            '/config/apps/tls/automation/policies' => [['subjects' => ['myapp.test', '*.myapp.test', '*.feat-x.myapp.test']]],
            '/config/apps/tls/certificates/automate' => ['myapp.test', '*.myapp.test', '*.feat-x.myapp.test'],
        ]);

        expect($admin->ensureWildcardFor('feat-x.myapp.test'))->toBeFalse();
    });
});

describe('hasRoute()', function (): void {
    it('returns true when a route is registered under the id', function (): void {
        expect(caddy(['/id/myapp' => ['@id' => 'myapp']])->hasRoute('myapp'))->toBeTrue();
    });

    it('returns false when the admin API answers 404 for the id', function (): void {
        expect(caddy([])->hasRoute('myapp'))->toBeFalse();
    });

    it('returns false when the admin API refuses the connection', function (): void {
        Http::fake(['*' => Http::failedConnection()]);

        expect((new CaddyAdmin(new RouterConfig()))->hasRoute('myapp'))->toBeFalse();
    });
});

describe('registerRoute()', function (): void {
    it('appends the route to the main server', function (): void {
        caddy([])->registerRoute('myapp-feat-x', ['feat-x.myapp.test'], 'myapp-feat-x-app-1:80');

        expect(caddyWrites()[0]['path'])->toBe('/config/apps/http/servers/main/routes');
    });

    it('registers a terminal route under the id matching the hosts', function (): void {
        caddy([])->registerRoute('myapp-feat-x', ['feat-x.myapp.test'], 'myapp-feat-x-app-1:80');

        expect(caddyWrites()[0]['body'])->toMatchArray([
            '@id' => 'myapp-feat-x',
            'match' => [['host' => ['feat-x.myapp.test']]],
            'terminal' => true,
        ]);
    });

    it('proxies the route to the checkout own container', function (): void {
        caddy([])->registerRoute('myapp-feat-x', ['feat-x.myapp.test'], 'myapp-feat-x-app-1:80');

        expect(data_get(caddyWrites()[0]['body'], 'handle.0.routes.0.handle.0'))->toBe([
            'handler' => 'reverse_proxy',
            'upstreams' => [['dial' => 'myapp-feat-x-app-1:80']],
        ]);
    });

    it('matches every host alias on the one route a checkout owns', function (): void {
        // One route per checkout is what `rift down` and `rift status` lean on, so
        // aliases join the matcher rather than becoming routes of their own.
        caddy([])->registerRoute('myapp', ['myapp.test', 'api.myapp.test', 'admin.myapp.test'], 'myapp-app-1:80');

        expect(array_map(fn (array $write): mixed => data_get($write['body'], 'match'), caddyWrites()))
            ->toBe([[['host' => ['myapp.test', 'api.myapp.test', 'admin.myapp.test']]]]);
    });

    it('throws a RequestException when the router rejects the route', function (): void {
        Http::fake(['*' => Http::response('', 400)]);

        (new CaddyAdmin(new RouterConfig()))->registerRoute('myapp', ['myapp.test'], 'myapp-app-1:80');
    })->throws(RequestException::class);
});

describe('deregisterRoute()', function (): void {
    it('deletes the route registered under the id', function (): void {
        caddy([])->deregisterRoute('myapp-feat-x');

        expect(caddyWrites())->toBe([[
            'method' => 'DELETE',
            'path' => '/id/myapp-feat-x',
            'body' => null,
        ]]);
    });

    it('throws a RequestException when the route does not exist', function (): void {
        Http::fake(['*' => Http::response('', 404)]);

        (new CaddyAdmin(new RouterConfig()))->deregisterRoute('myapp');
    })->throws(RequestException::class);
});

describe('routeHosts()', function (): void {
    it('returns the hosts a registered route currently matches', function (): void {
        $admin = caddy(['/id/myapp/match/0/host' => ['myapp.test', 'api.myapp.test']]);

        expect($admin->routeHosts('myapp'))->toBe(['myapp.test', 'api.myapp.test']);
    });

    it('drops values that are not hostnames', function (): void {
        $admin = caddy(['/id/myapp/match/0/host' => ['myapp.test', 42, null, 'api.myapp.test']]);

        expect($admin->routeHosts('myapp'))->toBe(['myapp.test', 'api.myapp.test']);
    });

    it('returns null when no route is registered under the id', function (): void {
        expect(caddy([])->routeHosts('nowhere'))->toBeNull();
    });

    it('returns null when the admin API refuses the connection', function (): void {
        Http::fake(['*' => Http::failedConnection()]);

        expect((new CaddyAdmin(new RouterConfig()))->routeHosts('myapp'))->toBeNull();
    });
});

describe('updateRouteHosts()', function (): void {
    it('swaps a route hosts in place without touching its upstream', function (): void {
        caddy([])->updateRouteHosts('myapp', ['myapp.test', 'api.myapp.test']);

        expect(caddyWrites())->toBe([[
            'method' => 'PATCH',
            'path' => '/id/myapp/match/0/host',
            'body' => ['myapp.test', 'api.myapp.test'],
        ]]);
    });

    it('throws a RequestException when the router rejects the hosts', function (): void {
        Http::fake(['*' => Http::response('', 400)]);

        (new CaddyAdmin(new RouterConfig()))->updateRouteHosts('myapp', ['myapp.test']);
    })->throws(RequestException::class);
});

describe('routes()', function (): void {
    it('returns every registered route', function (): void {
        $admin = caddy(['/config/apps/http/servers/main/routes' => [['@id' => 'lcp-web'], ['@id' => 'myapp']]]);

        expect($admin->routes())->toBe([['@id' => 'lcp-web'], ['@id' => 'myapp']]);
    });

    it('returns an empty list for a router with no routes', function (): void {
        expect(caddy(['/config/apps/http/servers/main/routes' => []])->routes())->toBe([]);
    });

    it('drops entries that are not routes', function (): void {
        $admin = caddy(['/config/apps/http/servers/main/routes' => ['stray', ['@id' => 'myapp']]]);

        expect($admin->routes())->toBe([['@id' => 'myapp']]);
    });

    it('returns null when the router has no server loaded', function (): void {
        expect(caddy([])->routes())->toBeNull();
    });

    it('returns null when the admin API answers with an error', function (): void {
        Http::fake(['*' => Http::response('', 500)]);

        expect((new CaddyAdmin(new RouterConfig()))->routes())->toBeNull();
    });

    it('returns null when the admin API refuses the connection, so it is never mistaken for an idle router', function (): void {
        // An unreachable router must never look idle, or it could be stopped while in use.
        Http::fake(['*' => Http::failedConnection()]);

        expect((new CaddyAdmin(new RouterConfig()))->routes())->toBeNull();
    });
});

describe('routeIds()', function (): void {
    it('returns the id of every registered route in order', function (): void {
        $admin = caddy(['/config/apps/http/servers/main/routes' => [['@id' => 'lcp-web'], ['@id' => 'myapp-feat-x']]]);

        expect($admin->routeIds())->toBe(['lcp-web', 'myapp-feat-x']);
    });

    it('skips a route without a usable id', function (mixed $route): void {
        $admin = caddy(['/config/apps/http/servers/main/routes' => [$route, ['@id' => 'myapp']]]);

        expect($admin->routeIds())->toBe(['myapp']);
    })->with([
        'no id' => [['match' => [['host' => ['stray.test']]]]],
        'an empty id' => [['@id' => '']],
        'a numeric id' => [['@id' => 7]],
    ]);

    it('returns null when the admin API refuses the connection', function (): void {
        Http::fake(['*' => Http::failedConnection()]);

        expect((new CaddyAdmin(new RouterConfig()))->routeIds())->toBeNull();
    });
});

describe('certifiedDomains()', function (): void {
    it('returns the base domains and skips their wildcards', function (): void {
        $admin = caddy(['/config/apps/tls/automation/policies' => [
            ['subjects' => ['myapp.test', '*.myapp.test', '*.feat-x.myapp.test']],
        ]]);

        expect($admin->certifiedDomains())->toBe(['myapp.test']);
    });

    it('collects the domains of every policy', function (): void {
        $admin = caddy(['/config/apps/tls/automation/policies' => [
            ['subjects' => ['aa.test']],
            ['subjects' => ['bb.test']],
        ]]);

        expect($admin->certifiedDomains())->toBe(['aa.test', 'bb.test']);
    });

    it('orders the longest domain first so a deeper base domain wins', function (): void {
        $admin = caddy(['/config/apps/tls/automation/policies' => [
            ['subjects' => ['myapp.test', 'api.myapp.test']],
        ]]);

        expect($admin->certifiedDomains())->toBe(['api.myapp.test', 'myapp.test']);
    });

    it('lists a domain once even when several policies hold it', function (): void {
        $admin = caddy(['/config/apps/tls/automation/policies' => [
            ['subjects' => ['myapp.test']],
            ['subjects' => ['myapp.test']],
        ]]);

        expect($admin->certifiedDomains())->toBe(['myapp.test']);
    });

    it('skips policies and subjects that are malformed', function (): void {
        $admin = caddy(['/config/apps/tls/automation/policies' => [
            'stray',
            ['issuers' => [['module' => 'internal']]],
            ['subjects' => 'myapp.test'],
            ['subjects' => [42, 'myapp.test']],
        ]]);

        expect($admin->certifiedDomains())->toBe(['myapp.test']);
    });

    it('returns an empty list when the router holds no policies', function (): void {
        expect(caddy([])->certifiedDomains())->toBe([]);
    });
});
