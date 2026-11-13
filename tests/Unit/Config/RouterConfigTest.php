<?php

declare(strict_types=1);

use App\Config\PortBinding;
use App\Config\RouterConfig;
use App\Exceptions\ConfigException;

describe('path()', function (): void {
    $home = getenv('HOME');

    afterEach(function () use ($home): void {
        putenv('XDG_CONFIG_HOME');
        putenv('USERPROFILE');
        putenv($home === false ? 'HOME' : 'HOME=' . $home);
    });

    it('places the config under XDG_CONFIG_HOME when it is set', function (): void {
        putenv('XDG_CONFIG_HOME=/xdg');

        expect(RouterConfig::path())->toBe('/xdg/rift/config.yaml');
    });

    it('places the config under HOME/.config when XDG_CONFIG_HOME is not set', function (): void {
        putenv('XDG_CONFIG_HOME');
        putenv('HOME=/home/luca');

        expect(RouterConfig::path())->toBe('/home/luca/.config/rift/config.yaml');
    });

    it('places the config under USERPROFILE/.config when HOME is not set', function (): void {
        putenv('XDG_CONFIG_HOME');
        putenv('HOME');
        putenv('USERPROFILE=/Users/luca');

        expect(RouterConfig::path())->toBe('/Users/luca/.config/rift/config.yaml');
    });

    it('places the config under the working directory when no home is set', function (): void {
        putenv('XDG_CONFIG_HOME');
        putenv('HOME');
        putenv('USERPROFILE');

        expect(RouterConfig::path())->toBe('./.config/rift/config.yaml');
    });
});

describe('load()', function (): void {
    afterEach(function (): void {
        putenv('XDG_CONFIG_HOME');
    });

    it('returns the defaults when the config file does not exist', function (): void {
        expect(RouterConfig::load(scratch() . '/config.yaml'))->toEqual(new RouterConfig());
    });

    it('reads the default config path when no path is given', function (): void {
        $base = scratch();
        mkdir($base . '/rift');
        file_put_contents($base . '/rift/config.yaml', "router:\n  name: from-xdg\n");
        putenv('XDG_CONFIG_HOME=' . $base);

        expect(RouterConfig::load()->name)->toBe('from-xdg');
    });

    it('reads every router setting from the file', function (): void {
        $path = routerConfigFile("router:\n  name: mine\n  image: caddy:2\n  admin: http://localhost:2020\n");

        expect(RouterConfig::load($path))->toEqual(new RouterConfig('mine', 'caddy:2', 'http://localhost:2020'));
    });

    it('keeps the default for every setting the file leaves out', function (): void {
        expect(RouterConfig::load(routerConfigFile("router:\n  name: mine\n")))
            ->toEqual(new RouterConfig(name: 'mine'));
    });

    it('returns the defaults when the file has no router section', function (): void {
        expect(RouterConfig::load(routerConfigFile("other: true\n")))->toEqual(new RouterConfig());
    });

    it('strips trailing slashes from the admin URL', function (): void {
        expect(RouterConfig::load(routerConfigFile("router:\n  admin: http://localhost:2019//\n"))->admin)
            ->toBe('http://localhost:2019');
    });

    it('throws a ConfigException when the file does not hold a mapping', function (string $contents): void {
        RouterConfig::load(routerConfigFile($contents));
    })->with([
        'an empty file' => '',
        'a bare string' => 'router',
    ])->throws(ConfigException::class, 'must contain a YAML mapping.');

    it('throws a ConfigException when `router` is not a mapping', function (): void {
        RouterConfig::load(routerConfigFile("router: mine\n"));
    })->throws(ConfigException::class, '`router` must be a mapping.');

    it('throws a ConfigException when a router setting is not a string', function (string $setting): void {
        RouterConfig::load(routerConfigFile("router:\n  {$setting}: [x]\n"));
    })->with([
        'name',
        'image',
        'admin',
    ])->throws(ConfigException::class, 'router `name`, `image`, and `admin` must be strings.');

    it('reads the bind address and web ports from the file', function (): void {
        expect(RouterConfig::load(routerConfigFile("router:\n  bind: 127.0.0.2\n  http: 8080\n  https: 8443\n")))
            ->toEqual(new RouterConfig(binding: new PortBinding(address: '127.0.0.2', http: 8080, https: 8443)));
    });

    it('binds every address on ports 80 and 443 by default', function (): void {
        expect(RouterConfig::load(routerConfigFile("router:\n  name: mine\n")))
            ->binding->toEqual(new PortBinding(address: null, http: 80, https: 443));
    });

    it('reads a bind of 0.0.0.0 as every address', function (): void {
        expect(RouterConfig::load(routerConfigFile("router:\n  bind: 0.0.0.0\n"))->binding->address)->toBeNull();
    });

    it('throws a ConfigException when `bind` is not an IPv4 address', function (string $bind): void {
        RouterConfig::load(routerConfigFile("router:\n  bind: {$bind}\n"));
    })->with([
        'a hostname' => 'localhost',
        'an IPv6 address' => '"::1"',
        'a list' => '[127.0.0.2]',
    ])->throws(ConfigException::class, 'router `bind` must be an IPv4 address.');

    it('throws a ConfigException when a web port is not a valid port', function (string $setting, string $value): void {
        RouterConfig::load(routerConfigFile("router:\n  {$setting}: {$value}\n"));
    })->with([
        'http as a string' => ['http', '"8080"'],
        'https as zero' => ['https', '0'],
        'https above 65535' => ['https', '65536'],
    ])->throws(ConfigException::class, 'router `http` and `https` must be ports between 1 and 65535.');

    it('throws a ConfigException when `http` and `https` are the same port', function (): void {
        RouterConfig::load(routerConfigFile("router:\n  http: 8443\n  https: 8443\n"));
    })->throws(ConfigException::class, 'router `http` and `https` must be different ports.');
});

describe('container()', function (): void {
    it('returns the router name', function (): void {
        expect(new RouterConfig('mine')->container())->toBe('mine');
    });
});

describe('network()', function (): void {
    it('returns the router name', function (): void {
        expect(new RouterConfig('mine')->network())->toBe('mine');
    });
});

describe('dataVolume()', function (): void {
    it('returns the router name suffixed with -data', function (): void {
        expect(new RouterConfig('mine')->dataVolume())->toBe('mine-data');
    });
});

describe('configVolume()', function (): void {
    it('returns the router name suffixed with -config', function (): void {
        expect(new RouterConfig('mine')->configVolume())->toBe('mine-config');
    });
});

describe('caCertFilename()', function (): void {
    it('returns the router name suffixed with -ca.crt', function (): void {
        expect(new RouterConfig('mine')->caCertFilename())->toBe('mine-ca.crt');
    });
});

describe('named()', function (): void {
    it('returns the same router when no name is pinned', function (): void {
        $router = new RouterConfig();

        expect($router->named(null))->toBe($router);
    });

    it('returns the same router when the pinned name is its own', function (): void {
        $router = new RouterConfig();

        expect($router->named(RouterConfig::DEFAULT_NAME))->toBe($router);
    });

    it('returns a router with the pinned name and the same image and admin URL', function (): void {
        $router = new RouterConfig(image: 'caddy:2', admin: 'http://localhost:2020');

        expect($router->named('myapp-router'))->toEqual(new RouterConfig('myapp-router', 'caddy:2', 'http://localhost:2020'));
    });

    it('keeps the bind address and web ports for a pinned router', function (): void {
        $router = new RouterConfig(binding: new PortBinding(address: '127.0.0.2', http: 8080, https: 8443));

        expect($router->named('myapp-router')->binding)->toBe($router->binding);
    });
});
