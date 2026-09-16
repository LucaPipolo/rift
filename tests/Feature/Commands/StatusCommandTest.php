<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;

describe('when the router is not running', function (): void {
    beforeEach(function (): void {
        host(project(), ['*docker*ps*' => '']);
    });

    it('says the router is not running', function (): void {
        artisan('status')->expectsOutputToContain('rift-router is not running');
    });

    it('exits with success', function (): void {
        artisan('status')->assertExitCode(0);
    });
});

describe('when the admin API is not answering', function (): void {
    beforeEach(function (): void {
        host(project());
        caddy([]);
    });

    it('explains that the router is running without an admin API', function (): void {
        artisan('status')
            ->expectsOutputToContain('rift-router is running but its admin API is not answering.');
    });

    it('exits with failure', function (): void {
        artisan('status')->assertExitCode(1);
    });
});

describe('when no route is registered', function (): void {
    beforeEach(function (): void {
        host(project());
        routerServing([]);
    });

    it('says nothing is registered', function (): void {
        artisan('status')->expectsOutputToContain('Nothing registered.');
    });

    it('exits with success', function (): void {
        artisan('status')->assertExitCode(0);
    });
});

describe('when every route container is running', function (): void {
    beforeEach(function (): void {
        host(project(), ['*docker*ps*' => "rift-router\nmyapp-app-1\n"]);
        routerServing([statusRoute('myapp', ['myapp.test', 'api.myapp.test'], 'myapp-app-1:80')]);
    });

    it('labels the group with the main checkout route id', function (): void {
        artisan('status')->expectsOutputToContain('MYAPP');
    });

    it('marks the current checkout with an arrow', function (): void {
        artisan('status')->expectsOutputToContain('❯ myapp.test');
    });

    it('does not mark an alias of the current checkout', function (): void {
        artisan('status')->doesntExpectOutputToContain('❯ api.myapp.test');
    });

    it('lists an alias under the checkout it belongs to', function (): void {
        artisan('status')->expectsOutputToContain('api.myapp.test');
    });

    it('shows the upstream of a healthy route without a state', function (): void {
        artisan('status')->doesntExpectOutputToContain('(down)');
    });

    it('counts an alias as part of its checkout in the summary', function (): void {
        artisan('status')->expectsOutputToContain('1 checkout · 1 project ─');
    });

    it('explains the arrow marking the current checkout', function (): void {
        artisan('status')->expectsOutputToContain('❯ marks the checkout you are standing in.');
    });

    it('does not explain the container states', function (): void {
        artisan('status')->doesntExpectOutputToContain('(unreachable) it is running');
    });

    it('exits with success', function (): void {
        artisan('status')->assertExitCode(0);
    });
});

describe('when a route container is down', function (): void {
    beforeEach(function (): void {
        host(project());
        routerServing([statusRoute('myapp', ['myapp.test', 'api.myapp.test'], 'myapp-app-1:80')]);
    });

    it('marks the route as down', function (): void {
        artisan('status')->expectsOutputToContain('myapp-app-1:80 (down)');
    });

    it('counts a down checkout once however many aliases it has', function (): void {
        artisan('status')->expectsOutputToContain('1 checkout · 1 project · 1 down');
    });

    it('explains the container states', function (): void {
        artisan('status')
            ->expectsOutputToContain('(down) the container is not running · (unreachable) it is running but not on the router network');
    });

    it('exits with failure', function (): void {
        artisan('status')->assertExitCode(1);
    });
});

describe('when a route container is running off the router network', function (): void {
    beforeEach(function (): void {
        host(project(), [
            '*docker*ps*' => "rift-router\nmyapp-app-1\n",
            '*network*inspect*' => "rift-router\n",
        ]);
        routerServing([statusRoute('myapp', ['myapp.test'], 'myapp-app-1:80')]);
    });

    it('marks the route as unreachable', function (): void {
        artisan('status')->expectsOutputToContain('myapp-app-1:80 (unreachable)');
    });

    it('exits with failure', function (): void {
        artisan('status')->assertExitCode(1);
    });
});

describe('when several projects and worktrees are registered', function (): void {
    beforeEach(function (): void {
        $containers = "rift-router\nmyapp-app-1\nmyapp-feat-x-app-1\nshop-feat-y-app-1\nblog-app-1\n";

        host(project(), ['*docker*ps*' => $containers, '*network*inspect*' => $containers]);
        routerServing([
            statusRoute('myapp-feat-x', ['feat-x.myapp.test'], 'myapp-feat-x-app-1:80'),
            statusRoute('myapp', ['myapp.test'], 'myapp-app-1:80'),
            statusRoute('shop-feat-y', ['feat-y.shop.test'], 'shop-feat-y-app-1:80'),
            statusRoute('blog', ['blog.local'], 'blog-app-1:80'),
        ], ['myapp.test', 'shop.test']);
    });

    it('lists the main checkout above its worktrees', function (): void {
        expect(bufferedOutputOf('status'))->toMatch('/myapp\.test .*\n.*feat-x\.myapp\.test/');
    });

    it('sorts the projects by domain', function (): void {
        expect(bufferedOutputOf('status'))->toMatch('/BLOG\n(.*\n)*.*MYAPP\n(.*\n)*.*SHOP\.TEST\n/');
    });

    it('lists a project with no main checkout under its domain', function (): void {
        expect(bufferedOutputOf('status'))->toMatch('/SHOP\.TEST\n.*feat-y\.shop\.test/');
    });

    it('groups a route outside every certified domain under its own host', function (): void {
        expect(bufferedOutputOf('status'))->toMatch('/BLOG\n.*blog\.local/');
    });

    it('pluralises the checkouts and projects in the summary', function (): void {
        artisan('status')->expectsOutputToContain('4 checkouts · 3 projects');
    });
});

describe('when a route is malformed', function (): void {
    beforeEach(function (): void {
        host(project());
    });

    it('skips a route that matches no host', function (array $route): void {
        routerServing([$route]);

        artisan('status')->expectsOutputToContain('Nothing registered.');
    })->with([
        'without a match' => [['@id' => 'myapp']],
        'with an empty host list' => [statusRoute('myapp', [])],
        'with only empty or non-string hosts' => [statusRoute('myapp', ['', 42])],
    ]);

    it('shows a dash for a route without an upstream', function (): void {
        routerServing([statusRoute('myapp', ['myapp.test'])]);

        artisan('status')->expectsOutputToContain('— (down)');
    });

    it('labels a group with its domain when the main route has no string id', function (): void {
        routerServing([statusRoute(42, ['myapp.test'], 'myapp-app-1:80')]);

        artisan('status')->expectsOutputToContain('MYAPP.TEST');
    });
});

describe('when run outside a project', function (): void {
    beforeEach(function (): void {
        chdir(scratch());
        putenv('XDG_CONFIG_HOME=' . getcwd() . '/.config');
        host((string) getcwd(), [
            '*rev-parse*--show-toplevel*' => Process::result(exitCode: 128),
            '*docker*ps*' => "rift-router\nmyapp-app-1\n",
        ]);
        routerServing([statusRoute('myapp', ['myapp.test'], 'myapp-app-1:80')]);
    });

    it('lists the shared router routes', function (): void {
        artisan('status')->expectsOutputToContain('myapp.test');
    });

    it('marks no checkout with an arrow', function (): void {
        artisan('status')->doesntExpectOutputToContain('❯');
    });
});
