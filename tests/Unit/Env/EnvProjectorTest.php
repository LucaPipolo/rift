<?php

declare(strict_types=1);

use App\Env\EnvProjector;
use App\Env\PortResolver;
use App\Exceptions\ConfigException;

describe('project()', function (): void {
    $lcpWeb = [
        'name' => 'lcp-web',
        'domain' => 'lcp-web.test',
        'preset' => 'compose',
        'app' => ['service' => 'app', 'port' => 4321],
        'ports' => [['key' => 'APP_PORT', 'default' => 4321]],
    ];

    $sail = [
        'name' => 'myapp',
        'domain' => 'myapp.test',
        'preset' => 'sail',
        'app' => ['port' => 80],
        'ports' => [
            ['key' => 'VITE_PORT', 'default' => 5173],
            ['key' => 'FORWARD_DB_PORT', 'default' => 5432],
        ],
        'env' => ['APP_URL' => 'https://{domain}'],
    ];

    $withEnv = fn (array $env): array => [
        'name' => 'myapp',
        'domain' => 'myapp.test',
        'app' => ['port' => 80],
        'ports' => [['key' => 'VITE_PORT', 'default' => 5173]],
        'env' => $env,
    ];

    it('reports the .env as created when it had to be seeded', function () use ($lcpWeb): void {
        [$projection] = projectEnv(scratchInstance($lcpWeb));

        expect($projection->created)->toBeTrue();
    });

    it('reports the .env as not created when it already existed', function () use ($lcpWeb): void {
        $instance = scratchInstance($lcpWeb);
        file_put_contents($instance->envPath(), "APP_ENV=production\n");

        [$projection] = projectEnv($instance);

        expect($projection->created)->toBeFalse();
    });

    it('seeds a main checkout .env from its .env.example', function () use ($lcpWeb): void {
        [, $env] = projectEnv(scratchInstance($lcpWeb, example: "APP_ENV=example\n"));

        expect($env->get('APP_ENV'))->toBe('example');
    });

    it('keeps the values already in an existing .env', function () use ($lcpWeb): void {
        $instance = scratchInstance($lcpWeb);
        file_put_contents($instance->envPath(), "APP_ENV=production\n");

        [, $env] = projectEnv($instance);

        expect($env->get('APP_ENV'))->toBe('production');
    });

    it('seeds a worktree .env from the main checkout .env', function () use ($lcpWeb): void {
        $instance = scratchInstance($lcpWeb, slug: 'feat-x');
        file_put_contents($instance->checkout->mainRoot . '/.env', "DB_PASSWORD=secret\n");

        [, $env] = projectEnv($instance);

        expect($env->get('DB_PASSWORD'))->toBe('secret');
    });

    it('seeds a worktree .env from its .env.example when the main checkout has none', function () use ($lcpWeb): void {
        [, $env] = projectEnv(scratchInstance($lcpWeb, slug: 'feat-x', example: "APP_ENV=example\n"));

        expect($env->get('APP_ENV'))->toBe('example');
    });

    it('throws a ConfigException when there is no .env to seed from', function () use ($lcpWeb): void {
        $instance = scratchInstance($lcpWeb);
        unlink($instance->checkout->root . '/.env.example');

        projectEnv($instance);
    })->throws(ConfigException::class, 'No .env to start from.');

    it('writes the value compose.yaml interpolates for a main checkout', function (string $key, string $value) use ($lcpWeb): void {
        [, $env] = projectEnv(scratchInstance($lcpWeb));

        expect($env->get($key))->toBe($value);
    })->with([
        'the compose project name' => ['COMPOSE_PROJECT_NAME', 'lcp-web'],
        'the project name' => ['BASE_NAME', 'lcp-web'],
        'the base domain' => ['BASE_DOMAIN', 'lcp-web.test'],
        'the router network' => ['ROUTER_NAME', 'rift-router'],
        'the app service' => ['APP_SERVICE', 'app'],
        'the container port' => ['CONTAINER_PORT', '4321'],
        'the forwarded port' => ['APP_PORT', '4321'],
    ]);

    it('writes the shared git directory', function () use ($lcpWeb): void {
        $instance = scratchInstance($lcpWeb);

        [, $env] = projectEnv($instance);

        expect($env->get('GIT_COMMON_DIR'))->toBe($instance->checkout->commonDir);
    });

    // BASE_DOMAIN stays the base: compose uses it for the wildcard host.
    it('writes the value a Sail compose.yaml interpolates for a worktree', function (string $key, string $value) use ($sail): void {
        [, $env] = projectEnv(scratchInstance($sail, slug: 'feat-x'));

        expect($env->get($key))->toBe($value);
    })->with([
        'the worktree compose project name' => ['COMPOSE_PROJECT_NAME', 'myapp-feat-x'],
        'the base domain, not the worktree one' => ['BASE_DOMAIN', 'myapp.test'],
        'the preset default service' => ['APP_SERVICE', 'laravel.test'],
        'the container port' => ['CONTAINER_PORT', '80'],
        'the worktree URL' => ['APP_URL', 'https://feat-x.myapp.test'],
        'the first forwarded port' => ['VITE_PORT', '5173'],
        'the second forwarded port' => ['FORWARD_DB_PORT', '5432'],
    ]);

    it('returns every key it wrote', function () use ($sail): void {
        [$projection] = projectEnv(scratchInstance($sail));

        expect(array_keys($projection->values))->toBe([
            'COMPOSE_PROJECT_NAME', 'GIT_COMMON_DIR', 'BASE_NAME', 'BASE_DOMAIN', 'ROUTER_NAME',
            'APP_SERVICE', 'CONTAINER_PORT', 'VITE_PORT', 'FORWARD_DB_PORT', 'APP_URL',
        ]);
    });

    it('returns the resolved ports keyed by env key', function () use ($sail): void {
        [$projection] = projectEnv(scratchInstance($sail));

        expect($projection->ports)->toBe(['VITE_PORT' => 5173, 'FORWARD_DB_PORT' => 5432]);
    });

    it('keeps the port already in the .env when it is still free', function () use ($lcpWeb): void {
        $instance = scratchInstance($lcpWeb);
        file_put_contents($instance->envPath(), "APP_PORT=4400\n");

        [$projection] = projectEnv($instance);

        expect($projection->ports)->toBe(['APP_PORT' => 4400]);
    });

    it('scans from the default when the .env holds a port that is not numeric', function () use ($lcpWeb): void {
        $instance = scratchInstance($lcpWeb);
        file_put_contents($instance->envPath(), "APP_PORT=auto\n");

        [$projection] = projectEnv($instance);

        expect($projection->ports)->toBe(['APP_PORT' => 4321]);
    });

    it('steps over a port a sibling worktree reserved in its .env', function () use ($lcpWeb): void {
        $sibling = scratch();
        file_put_contents($sibling . '/.env', "APP_PORT=4321\n");

        [$projection] = projectEnv(scratchInstance($lcpWeb, slug: 'feat-x', siblings: [$sibling]));

        expect($projection->ports)->toBe(['APP_PORT' => 4322]);
    });

    it('ignores a sibling whose .env holds no numeric port', function () use ($lcpWeb): void {
        $sibling = scratch();
        file_put_contents($sibling . '/.env', "APP_PORT=auto\n");

        [$projection] = projectEnv(scratchInstance($lcpWeb, slug: 'feat-x', siblings: [$sibling, scratch()]));

        expect($projection->ports)->toBe(['APP_PORT' => 4321]);
    });

    it('steps over a port that several siblings reserved', function () use ($lcpWeb): void {
        $first = scratch();
        $second = scratch();
        file_put_contents($first . '/.env', "APP_PORT=4321\n");
        file_put_contents($second . '/.env', "APP_PORT=4321\n");

        [$projection] = projectEnv(scratchInstance($lcpWeb, slug: 'feat-x', siblings: [$first, $second]));

        expect($projection->ports)->toBe(['APP_PORT' => 4322]);
    });

    it('renders an env template placeholder', function (string $template, string $value) use ($withEnv): void {
        [, $env] = projectEnv(scratchInstance($withEnv(['RENDERED' => $template]), slug: 'feat-x'));

        expect($env->get('RENDERED'))->toBe($value);
    })->with([
        'the project name' => ['{name}', 'myapp'],
        'the worktree slug' => ['{slug}', 'feat-x'],
        'the checkout domain' => ['{domain}', 'feat-x.myapp.test'],
        'the base domain' => ['{base_domain}', 'myapp.test'],
        'the compose project name' => ['{compose_name}', 'myapp-feat-x'],
        'the checkout URL' => ['{url}', 'https://feat-x.myapp.test'],
        'a derived env key' => ['{BASE_NAME}', 'myapp'],
        'a resolved port' => ['https://{domain}:{VITE_PORT}', 'https://feat-x.myapp.test:5173'],
    ]);

    it('renders the slug placeholder as empty for the main checkout', function () use ($withEnv): void {
        [, $env] = projectEnv(scratchInstance($withEnv(['SLUG' => 'x{slug}'])));

        expect($env->get('SLUG'))->toBe('x');
    });

    it('writes a template without placeholders as is', function () use ($withEnv): void {
        [, $env] = projectEnv(scratchInstance($withEnv(['APP_ENV' => 'local'])));

        expect($env->get('APP_ENV'))->toBe('local');
    });

    it('throws a ConfigException naming the placeholder it cannot derive', function () use ($withEnv): void {
        projectEnv(scratchInstance($withEnv(['APP_URL' => 'https://{nope}'])));
    })->throws(ConfigException::class, 'rift.yaml: `env.APP_URL` uses `{nope}`, which rift does not derive. Available: ');

    it('leaves the .env unchanged when run a second time', function () use ($lcpWeb): void {
        $instance = scratchInstance($lcpWeb);
        projectEnv($instance);
        $after = file_get_contents($instance->envPath());

        projectEnv($instance);

        expect(file_get_contents($instance->envPath()))->toBe($after);
    });
});

describe('requiredKeys()', function (): void {
    it('returns the derived keys followed by every forwarded port key', function (): void {
        $instance = myapp(['ports' => [['key' => 'VITE_PORT', 'default' => 5173]]]);

        expect(new EnvProjector(new PortResolver(probe()))->requiredKeys($instance))->toBe([
            'COMPOSE_PROJECT_NAME', 'GIT_COMMON_DIR', 'BASE_NAME', 'BASE_DOMAIN',
            'ROUTER_NAME', 'APP_SERVICE', 'CONTAINER_PORT', 'VITE_PORT',
        ]);
    });
});

describe('missingKeys()', function (): void {
    $lcpWeb = [
        'name' => 'lcp-web',
        'domain' => 'lcp-web.test',
        'app' => ['port' => 4321],
        'ports' => [['key' => 'APP_PORT', 'default' => 4321]],
    ];

    it('returns the required keys an .env written by an older version lacks', function () use ($lcpWeb): void {
        $instance = scratchInstance($lcpWeb);
        file_put_contents($instance->envPath(), "COMPOSE_PROJECT_NAME=lcp-web\n");

        expect(new EnvProjector(new PortResolver(probe()))->missingKeys($instance))
            ->toBe(['GIT_COMMON_DIR', 'BASE_NAME', 'BASE_DOMAIN', 'ROUTER_NAME', 'APP_SERVICE', 'CONTAINER_PORT', 'APP_PORT']);
    });

    it('returns no keys once the .env has been projected', function () use ($lcpWeb): void {
        $instance = scratchInstance($lcpWeb);
        projectEnv($instance);

        expect(new EnvProjector(new PortResolver(probe()))->missingKeys($instance))->toBe([]);
    });

    it('returns every required key when there is no .env', function () use ($lcpWeb): void {
        $instance = scratchInstance($lcpWeb);
        $projector = new EnvProjector(new PortResolver(probe()));

        expect($projector->missingKeys($instance))->toBe($projector->requiredKeys($instance));
    });
});
