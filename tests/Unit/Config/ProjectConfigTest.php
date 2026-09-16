<?php

declare(strict_types=1);

use App\Config\ProjectConfig;
use App\Enums\HookStage;
use App\Enums\Preset;
use App\Exceptions\ConfigException;

describe('existsIn()', function (): void {
    it('returns true when the checkout has a rift.yaml', function (): void {
        expect(ProjectConfig::existsIn(checkoutWithRiftYaml('name: myapp')))->toBeTrue();
    });

    it('returns false when the checkout has no rift.yaml', function (): void {
        expect(ProjectConfig::existsIn(scratch()))->toBeFalse();
    });

    it('returns false when rift.yaml is a directory', function (): void {
        $root = scratch();
        mkdir($root . '/rift.yaml');

        expect(ProjectConfig::existsIn($root))->toBeFalse();
    });
});

describe('pathIn()', function (): void {
    it('returns the rift.yaml path inside the checkout root', function (): void {
        expect(ProjectConfig::pathIn('/Sites/myapp'))->toBe('/Sites/myapp/rift.yaml');
    });
});

describe('load()', function (): void {
    it('parses the rift.yaml in the checkout', function (): void {
        $root = checkoutWithRiftYaml("name: myapp\ndomain: myapp.test\napp:\n  port: 80\n");

        expect(ProjectConfig::load($root)->name)->toBe('myapp');
    });

    it('throws a ConfigException pointing at rift init when there is no rift.yaml', function (): void {
        ProjectConfig::load(scratch());
    })->throws(ConfigException::class, 'No rift.yaml in this checkout. Run `rift init` to create one.');

    it('throws a ConfigException when rift.yaml is not valid YAML', function (): void {
        ProjectConfig::load(checkoutWithRiftYaml("name: [myapp\n"));
    })->throws(ConfigException::class, 'rift.yaml is not valid YAML: ');

    it('throws a ConfigException when rift.yaml does not hold a mapping', function (string $contents): void {
        ProjectConfig::load(checkoutWithRiftYaml($contents));
    })->with([
        'an empty file' => '',
        'a bare string' => 'myapp',
    ])->throws(ConfigException::class, 'rift.yaml must contain a YAML mapping.');
});

describe('fromArray()', function (): void {
    it('reads the project name', function (): void {
        expect(myappConfig()->name)->toBe('myapp');
    });

    it('accepts a valid project name', function (string $name): void {
        expect(myappConfig(['name' => $name])->name)->toBe($name);
    })->with([
        'letters only' => 'myapp',
        'a leading digit' => '1app',
        'hyphens and underscores' => 'my-app_2',
    ]);

    it('throws a ConfigException when the name is not a valid Compose project name', function (mixed $name): void {
        myappConfig(['name' => $name]);
    })->with([
        'a missing name' => null,
        'a non-string name' => 42,
        'uppercase letters' => 'MyApp',
        'a leading hyphen' => '-myapp',
        'a dot' => 'my.app',
    ])->throws(ConfigException::class, 'rift.yaml: `name` must start with a letter or digit');

    it('reads the base domain', function (): void {
        expect(myappConfig(['domain' => 'app.myapp.test'])->baseDomain)->toBe('app.myapp.test');
    });

    it('throws a ConfigException when the domain is not a hostname', function (mixed $domain): void {
        myappConfig(['domain' => $domain]);
    })->with([
        'a missing domain' => null,
        'a non-string domain' => 42,
        'a single label' => 'myapp',
        'a leading hyphen' => '-myapp.test',
        'uppercase letters' => 'MyApp.test',
    ])->throws(ConfigException::class, 'rift.yaml: `domain` must be a hostname such as `myapp.test`.');

    it('uses the compose preset when none is given', function (): void {
        expect(myappConfig()->preset)->toBe(Preset::Compose);
    });

    it('reads the named preset', function (): void {
        expect(myappConfig(['preset' => 'sail'])->preset)->toBe(Preset::Sail);
    });

    it('throws a ConfigException when the preset is not a string', function (): void {
        myappConfig(['preset' => ['sail']]);
    })->throws(ConfigException::class, 'rift.yaml: `preset` must be a string.');

    it('throws a ConfigException when the preset is unknown', function (): void {
        myappConfig(['preset' => 'podman']);
    })->throws(ConfigException::class, 'Unknown preset `podman`.');

    it('throws a ConfigException when `app` is not a mapping', function (): void {
        myappConfig(['app' => 'web']);
    })->throws(ConfigException::class, 'rift.yaml: `app` must be a mapping of `service` and `port`.');

    it('reads the app service', function (): void {
        expect(myappConfig(['app' => ['service' => 'web', 'port' => 80]])->appService)->toBe('web');
    });

    it('uses the preset default service when the app names none', function (string $preset, string $service): void {
        expect(myappConfig(['preset' => $preset])->appService)->toBe($service);
    })->with([
        'compose' => ['compose', 'app'],
        'sail' => ['sail', 'laravel.test'],
    ]);

    it('throws a ConfigException when the app service is empty or not a string', function (mixed $service): void {
        myappConfig(['app' => ['service' => $service, 'port' => 80]]);
    })->with([
        'a whitespace-only service' => '  ',
        'a non-string service' => 42,
    ])->throws(ConfigException::class, 'rift.yaml: `app.service` cannot be empty.');

    it('reads the app port', function (): void {
        expect(myappConfig(['app' => ['port' => 3000]])->appPort)->toBe(3000);
    });

    it('throws a ConfigException when the app port is not a valid port', function (array $app): void {
        myappConfig(['app' => $app]);
    })->with([
        'an app section without a port' => [[]],
        'a numeric string' => [['port' => '80']],
        'zero' => [['port' => 0]],
        'a port above 65535' => [['port' => 65536]],
    ])->throws(ConfigException::class, 'rift.yaml: `app.port` must be the port the app listens on');

    it('throws a ConfigException when the whole app section is missing', function (): void {
        ProjectConfig::fromArray(['name' => 'myapp', 'domain' => 'myapp.test']);
    })->throws(ConfigException::class, 'rift.yaml: `app.port` must be the port the app listens on');

    it('has no forwarded ports when none are declared', function (): void {
        expect(myappConfig()->ports)->toBe([]);
    });

    it('reads the forwarded ports in order', function (): void {
        $config = myappConfig(['ports' => [
            ['key' => 'VITE_PORT', 'default' => 5173],
            ['key' => 'FORWARD_DB_PORT', 'default' => 5432],
        ]]);

        expect(array_map(fn ($port) => [$port->key, $port->default], $config->ports))
            ->toBe([['VITE_PORT', 5173], ['FORWARD_DB_PORT', 5432]]);
    });

    it('throws a ConfigException when the ports are not a list', function (mixed $ports): void {
        myappConfig(['ports' => $ports]);
    })->with([
        'a mapping' => [['VITE_PORT' => 5173]],
        'a string' => 'VITE_PORT',
    ])->throws(ConfigException::class, 'rift.yaml: `ports` must be a list.');

    it('throws a ConfigException when a port entry is not a mapping', function (): void {
        myappConfig(['ports' => ['VITE_PORT']]);
    })->throws(ConfigException::class, 'rift.yaml: `ports` must be a list of mappings.');

    it('throws a ConfigException naming the ports section when a port entry is invalid', function (): void {
        myappConfig(['ports' => [['key' => 'vite']]]);
    })->throws(ConfigException::class, 'rift.yaml: `ports`: each port needs a `key`');

    it('has no host aliases when none are declared', function (): void {
        expect(myappConfig()->hosts)->toBe([]);
    });

    it('accepts a single lowercase DNS label as a host', function (string $label): void {
        expect(myappConfig(['hosts' => [$label]])->hosts)->toBe([$label]);
    })->with([
        'letters' => 'api',
        'a hyphen inside' => 'admin-v2',
        'a trailing digit' => 'a1',
        'a single character' => 'a',
    ]);

    it('keeps the host aliases in order', function (): void {
        expect(myappConfig(['hosts' => ['api', 'admin']])->hosts)->toBe(['api', 'admin']);
    });

    // A dotted label would land one level below the wildcard certificate and
    // come up without one; the rest are not hostnames at all.
    it('throws a ConfigException when a host is not a single lowercase DNS label', function (mixed $label): void {
        myappConfig(['hosts' => [$label]]);
    })->with([
        'a dotted label' => 'api.v1',
        'uppercase letters' => 'API',
        'a leading hyphen' => '-api',
        'a trailing hyphen' => 'api-',
        'an empty string' => '',
        'an integer' => 42,
        'a list' => [['api']],
    ])->throws(ConfigException::class, 'rift.yaml: `hosts` entries must be single lowercase DNS labels such as `api`, served as `api.myapp.test`.');

    it('throws a ConfigException when the hosts are not a list', function (mixed $hosts): void {
        myappConfig(['hosts' => $hosts]);
    })->with([
        'a mapping' => [['api' => true]],
        'a string' => 'api',
    ])->throws(ConfigException::class, 'rift.yaml: `hosts` must be a list of subdomain labels such as `api`.');

    it('throws a ConfigException when a host is listed twice', function (): void {
        myappConfig(['hosts' => ['api', 'api']]);
    })->throws(ConfigException::class, 'rift.yaml: `hosts` lists `api` twice.');

    it('has no env templates when none are declared', function (): void {
        expect(myappConfig()->env)->toBe([]);
    });

    it('reads a string env template as is', function (): void {
        expect(myappConfig(['env' => ['APP_URL' => 'https://{domain}']])->env)->toBe(['APP_URL' => 'https://{domain}']);
    });

    it('casts a scalar or null env value to a string', function (mixed $value, string $expected): void {
        expect(myappConfig(['env' => ['APP_DEBUG' => $value]])->env)->toBe(['APP_DEBUG' => $expected]);
    })->with([
        'an integer' => [42, '42'],
        'true' => [true, '1'],
        'null' => [null, ''],
    ]);

    it('throws a ConfigException when an env key is not SCREAMING_SNAKE_CASE', function (mixed $env): void {
        myappConfig(['env' => $env]);
    })->with([
        'a lowercase key' => [['app_url' => 'x']],
        'a list instead of a mapping' => [['x']],
        'a bare string' => 'x',
    ])->throws(ConfigException::class, 'rift.yaml: `env` keys must be in SCREAMING_SNAKE_CASE.');

    it('throws a ConfigException when an env value is not scalar', function (): void {
        myappConfig(['env' => ['APP_URL' => ['https://myapp.test']]]);
    })->throws(ConfigException::class, 'rift.yaml: `env` values must be scalar or null.');

    it('has no hooks when none are declared', function (): void {
        expect(myappConfig()->hooks)->toBe([]);
    });

    it('ignores a hooks value that is not a mapping', function (): void {
        expect(myappConfig(['hooks' => 'composer install'])->hooks)->toBe([]);
    });

    it('reads a list of hooks for a stage', function (): void {
        $config = myappConfig(['hooks' => ['after_up' => [['run' => 'composer install'], ['run' => 'npm install']]]]);

        expect(array_map(fn ($hook) => $hook->run, $config->hooks['after_up']))->toBe(['composer install', 'npm install']);
    });

    it('reads a single hook written as a mapping rather than a list', function (): void {
        $config = myappConfig(['hooks' => ['after_up' => ['run' => 'composer run setup']]]);

        expect(array_map(fn ($hook) => $hook->run, $config->hooks['after_up']))->toBe(['composer run setup']);
    });

    it('keys the hooks by every stage that declares them', function (): void {
        $config = myappConfig(['hooks' => [
            'before_up' => ['run' => 'a'],
            'after_up' => ['run' => 'b'],
            'before_down' => ['run' => 'c'],
            'after_down' => ['run' => 'd'],
        ]]);

        expect(array_keys($config->hooks))->toBe(['before_up', 'after_up', 'before_down', 'after_down']);
    });

    it('ignores hooks under a stage rift does not know', function (): void {
        expect(myappConfig(['hooks' => ['during_up' => ['run' => 'composer install']]])->hooks)->toBe([]);
    });

    it('throws a ConfigException when a stage holds neither a hook nor a list', function (): void {
        myappConfig(['hooks' => ['after_up' => 'composer install']]);
    })->throws(ConfigException::class, 'rift.yaml: `hooks.after_up` must be a list.');

    it('throws a ConfigException naming the stage when a hook is invalid', function (): void {
        myappConfig(['hooks' => ['before_down' => [['run' => '']]]]);
    })->throws(ConfigException::class, 'rift.yaml: `hooks.before_down`: every hook needs a non-empty `run`.');

    it('pins no router when none is given', function (): void {
        expect(myappConfig()->router)->toBeNull();
    });

    it('reads the router the project pins', function (): void {
        expect(myappConfig(['router' => 'myapp-router'])->router)->toBe('myapp-router');
    });

    it('throws a ConfigException when the router is blank or not a string', function (mixed $router): void {
        myappConfig(['router' => $router]);
    })->with([
        'a whitespace-only name' => ' ',
        'a non-string name' => 42,
    ])->throws(ConfigException::class, 'rift.yaml: `router`, when given, must name a router.');
});

describe('hooks()', function (): void {
    it('returns the hooks declared for the stage', function (): void {
        $config = myappConfig(['hooks' => ['after_up' => ['run' => 'composer run setup']]]);

        expect($config->hooks(HookStage::AfterUp)[0]->run)->toBe('composer run setup');
    });

    it('returns an empty list for a stage with no hooks', function (): void {
        $config = myappConfig(['hooks' => ['after_up' => ['run' => 'composer run setup']]]);

        expect($config->hooks(HookStage::BeforeUp))->toBe([]);
    });
});
