<?php

declare(strict_types=1);

use App\Checkout\Instance;
use App\Config\PortBinding;
use App\Config\RouterConfig;

describe('make()', function (): void {
    it('binds a project with no router of its own to the shared router', function (): void {
        expect(myapp()->router->name)->toBe(RouterConfig::DEFAULT_NAME);
    });

    it('marks a project with no router of its own as sharing', function (): void {
        expect(myapp()->sharesRouter)->toBeTrue();
    });

    it('marks a project that names the shared router explicitly as sharing', function (): void {
        expect(myapp(['router' => RouterConfig::DEFAULT_NAME])->sharesRouter)->toBeTrue();
    });

    // This is the only case where `rift down` may stop a router, so an inverted
    // comparison here would stop a router other projects are using.
    it('marks a project that pins its own router as owning it', function (): void {
        expect(myapp(['router' => 'myapp-router'])->sharesRouter)->toBeFalse();
    });

    it('binds a project that pins its own router to that router', function (): void {
        $router = myapp(['router' => 'myapp-router'])->router;

        expect([$router->name, $router->network(), $router->dataVolume()])
            ->toBe(['myapp-router', 'myapp-router', 'myapp-router-data']);
    });

    it('keeps a private router CA separate from the shared one', function (): void {
        expect(myapp(['router' => 'myapp-router'])->router->caCertFilename())
            ->not->toBe(myapp()->router->caCertFilename());
    });

    it('keeps the checkout and config it was given', function (): void {
        $checkout = primaryCheckout();
        $config = myappConfig();

        $instance = Instance::make($checkout, $config, new RouterConfig());

        expect([$instance->checkout, $instance->config])->toBe([$checkout, $config]);
    });
});

describe('composeName()', function (): void {
    it('returns the project name for the main checkout', function (): void {
        expect(myapp()->composeName())->toBe('myapp');
    });

    it('returns the project name suffixed with the slug for a worktree', function (): void {
        expect(myapp(slug: 'feat-x')->composeName())->toBe('myapp-feat-x');
    });
});

describe('domain()', function (): void {
    it('returns the base domain for the main checkout', function (): void {
        expect(myapp()->domain())->toBe('myapp.test');
    });

    it('returns the worktree subdomain for a worktree', function (): void {
        expect(myapp(slug: 'feat-x')->domain())->toBe('feat-x.myapp.test');
    });
});

describe('hosts()', function (): void {
    it('returns only the checkout domain when no aliases are declared', function (): void {
        expect(myapp()->hosts())->toBe(['myapp.test']);
    });

    it('hangs every alias off the main checkout domain', function (): void {
        expect(myapp(['hosts' => ['api', 'admin']])->hosts())
            ->toBe(['myapp.test', 'api.myapp.test', 'admin.myapp.test']);
    });

    // api.myapp.test belongs to the main checkout. A worktree answering there
    // too would shadow it, so its aliases go one level down.
    it('hangs a worktree aliases off the worktree domain, not the base domain', function (): void {
        expect(myapp(['hosts' => ['api', 'admin']], slug: 'feat-x')->hosts())
            ->toBe(['feat-x.myapp.test', 'api.feat-x.myapp.test', 'admin.feat-x.myapp.test']);
    });
});

describe('urls()', function (): void {
    it('returns the HTTPS URL of the checkout domain when no aliases are declared', function (): void {
        expect(myapp()->urls())->toBe(['https://myapp.test']);
    });

    it('returns the HTTPS URL of every hostname, own domain first', function (): void {
        expect(myapp(['hosts' => ['api', 'admin']])->urls())
            ->toBe(['https://myapp.test', 'https://api.myapp.test', 'https://admin.myapp.test']);
    });

    it('adds the HTTPS port to every URL when the router does not use 443', function (): void {
        expect(onPort(myapp(['hosts' => ['api']]), 8443)->urls())
            ->toBe(['https://myapp.test:8443', 'https://api.myapp.test:8443']);
    });
});

describe('url()', function (): void {
    it('returns the HTTPS URL of the main checkout domain', function (): void {
        expect(myapp()->url())->toBe('https://myapp.test');
    });

    it('returns the HTTPS URL of the worktree domain', function (): void {
        expect(myapp(['hosts' => ['api']], slug: 'feat-x')->url())->toBe('https://feat-x.myapp.test');
    });

    it('adds the HTTPS port when the router does not use 443', function (): void {
        expect(onPort(myapp(), 8443)->url())->toBe('https://myapp.test:8443');
    });
});

/**
 * Serve an instance through a router on another HTTPS host port.
 *
 * @param  Instance  $instance  The instance.
 * @param  int  $https  The HTTPS host port.
 *
 * @return Instance The instance on that port.
 */
function onPort(Instance $instance, int $https): Instance
{
    return new Instance($instance->checkout, $instance->config, new RouterConfig(binding: new PortBinding(https: $https)));
}

describe('needsOwnWildcard()', function (): void {
    it('returns false for the main checkout without aliases', function (): void {
        expect(myapp()->needsOwnWildcard())->toBeFalse();
    });

    // *.myapp.test already covers api.myapp.test.
    it('returns false for the main checkout with aliases', function (): void {
        expect(myapp(['hosts' => ['api']])->needsOwnWildcard())->toBeFalse();
    });

    it('returns false for a worktree without aliases', function (): void {
        expect(myapp(slug: 'feat-x')->needsOwnWildcard())->toBeFalse();
    });

    // *.myapp.test reaches feat-x.myapp.test but not api.feat-x.myapp.test.
    it('returns true for a worktree with aliases', function (): void {
        expect(myapp(['hosts' => ['api']], slug: 'feat-x')->needsOwnWildcard())->toBeTrue();
    });
});

describe('hostCollision()', function (): void {
    // `git worktree add ../myapp-api` would be served at api.myapp.test, the
    // same host the main checkout's `api` alias claims.
    it('returns the alias a worktree slug collides with', function (string $slug): void {
        expect(myapp(['hosts' => ['api', 'admin']], slug: $slug)->hostCollision())->toBe('api');
    })->with([
        'a slug carrying the project prefix' => 'myapp-api',
        'a bare slug' => 'api',
    ]);

    it('returns null for a worktree whose slug is not an alias', function (): void {
        expect(myapp(['hosts' => ['api', 'admin']], slug: 'feat-x')->hostCollision())->toBeNull();
    });

    it('returns null for the main checkout', function (): void {
        expect(myapp(['hosts' => ['api', 'admin']])->hostCollision())->toBeNull();
    });
});

describe('container()', function (): void {
    it('names the app container after this checkout, not the project', function (): void {
        expect(myapp(['preset' => 'sail'], slug: 'feat-x')->container())->toBe('myapp-feat-x-laravel.test-1');
    });

    it('reads the service name from the config', function (): void {
        expect(myapp(['app' => ['service' => 'web', 'port' => 3000]], slug: 'feat-x')->container())
            ->toBe('myapp-feat-x-web-1');
    });
});

describe('upstream()', function (): void {
    it('points at this checkout container and the app port', function (): void {
        expect(myapp(['preset' => 'sail'], slug: 'feat-x')->upstream())->toBe('myapp-feat-x-laravel.test-1:80');
    });

    it('reads the service and port from the config', function (): void {
        expect(myapp(['app' => ['service' => 'web', 'port' => 3000]], slug: 'feat-x')->upstream())
            ->toBe('myapp-feat-x-web-1:3000');
    });
});

describe('routeId()', function (): void {
    it('returns the compose name of the checkout', function (): void {
        expect(myapp(slug: 'feat-x')->routeId())->toBe('myapp-feat-x');
    });
});

describe('envPath()', function (): void {
    it('returns the .env inside the checkout root', function (): void {
        expect(myapp(slug: 'feat-x')->envPath())->toBe('/Sites/trees/feat-x/.env');
    });
});
