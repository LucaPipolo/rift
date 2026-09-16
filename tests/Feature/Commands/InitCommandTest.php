<?php

declare(strict_types=1);

use App\Config\ProjectConfig;
use App\Enums\HookStage;
use App\Enums\Preset;

describe('when rift.yaml already exists', function (): void {
    beforeEach(function (): void {
        $this->root = project();
        host($this->root);
    });

    it('exits with failure', function (): void {
        lifecycleInitWithDefaults()->assertExitCode(1);
    });

    it('tells the user to pass --force', function (): void {
        lifecycleInitWithDefaults()->expectsOutputToContain('rift.yaml already exists. Pass --force to replace it.');
    });

    it('leaves the existing file untouched', function (): void {
        lifecycleInitWithDefaults()->run();

        expect(file_get_contents($this->root . '/rift.yaml'))->toBe("name: myapp\ndomain: myapp.test\napp:\n  port: 80\n");
    });

    it('replaces the file with --force', function (): void {
        artisan('init', ['--force' => true, '--no-interaction' => true])->run();

        expect(ProjectConfig::load($this->root)->appPort)->toBe(3000);
    });
});

describe('when accepting every default', function (): void {
    it('exits with success', function (): void {
        lifecycleInitProject();

        lifecycleInitWithDefaults()->assertExitCode(0);
    });

    it('takes the project name from the first place that declares one', function (array $files, string $name): void {
        /** @var array<string, string> $files */
        $root = lifecycleInitProject($files);

        lifecycleInitWithDefaults()->run();

        expect(ProjectConfig::load($root)->name)->toBe($name);
    })->with([
        'BASE_NAME in .env' => [['.env' => "BASE_NAME=shop\n", '.env.example' => "BASE_NAME=store\n"], 'shop'],
        'BASE_NAME in .env.example' => [['.env.example' => "BASE_NAME=store\n"], 'store'],
        'COMPOSE_PROJECT_NAME' => [['.env' => "COMPOSE_PROJECT_NAME=depot\n"], 'depot'],
    ]);

    it('names the project after its directory when nothing declares a name', function (): void {
        $root = lifecycleInitProject();

        lifecycleInitWithDefaults()->run();

        expect(ProjectConfig::load($root)->name)->toBe(basename($root));
    });

    it('takes the base domain from BASE_DOMAIN', function (): void {
        $root = lifecycleInitProject(['.env' => "BASE_DOMAIN=shop.localhost\n"]);

        lifecycleInitWithDefaults()->run();

        expect(ProjectConfig::load($root)->baseDomain)->toBe('shop.localhost');
    });

    it('derives the base domain from the project name', function (): void {
        $root = lifecycleInitProject(['.env' => "BASE_NAME=shop\n"]);

        lifecycleInitWithDefaults()->run();

        expect(ProjectConfig::load($root)->baseDomain)->toBe('shop.test');
    });

    it('detects the preset from what the checkout contains', function (array $files, Preset $preset): void {
        /** @var array<string, string> $files */
        $root = lifecycleInitProject($files);

        lifecycleInitWithDefaults()->run();

        expect(ProjectConfig::load($root)->preset)->toBe($preset);
    })->with([
        'an artisan file' => [['artisan' => ''], Preset::Sail],
        'Sail installed in vendor' => [['vendor/laravel/sail/bin/sail' => ''], Preset::Sail],
        'neither' => [[], Preset::Compose],
    ]);

    it('takes the service from APP_SERVICE', function (): void {
        $root = lifecycleInitProject(['.env' => "APP_SERVICE=web\n"]);

        lifecycleInitWithDefaults()->run();

        expect(ProjectConfig::load($root)->appService)->toBe('web');
    });

    it('falls back to the preset service', function (array $files, string $service): void {
        /** @var array<string, string> $files */
        $root = lifecycleInitProject($files);

        lifecycleInitWithDefaults()->run();

        expect(ProjectConfig::load($root)->appService)->toBe($service);
    })->with([
        'compose' => [[], 'app'],
        'sail' => [['artisan' => ''], 'laravel.test'],
    ]);

    it('takes the container port from CONTAINER_PORT', function (): void {
        $root = lifecycleInitProject(['.env' => "CONTAINER_PORT=8000\n"]);

        lifecycleInitWithDefaults()->run();

        expect(ProjectConfig::load($root)->appPort)->toBe(8000);
    });

    it('falls back to the preset container port', function (array $files, int $port): void {
        /** @var array<string, string> $files */
        $root = lifecycleInitProject($files);

        lifecycleInitWithDefaults()->run();

        expect(ProjectConfig::load($root)->appPort)->toBe($port);
    })->with([
        'compose' => [[], 3000],
        'sail' => [['artisan' => ''], 80],
    ]);

    it('keeps APP_URL pointing at the checkout domain for Sail', function (): void {
        $root = lifecycleInitProject(['artisan' => '', '.env.example' => "APP_URL=http://localhost\n"]);

        lifecycleInitWithDefaults()->run();

        expect(ProjectConfig::load($root)->env)->toBe(['APP_URL' => 'https://{domain}']);
    });

    it('leaves APP_URL alone', function (array $files): void {
        /** @var array<string, string> $files */
        $root = lifecycleInitProject($files);

        lifecycleInitWithDefaults()->run();

        expect(ProjectConfig::load($root)->env)->toBe([]);
    })->with([
        'for Sail without an APP_URL' => [['artisan' => '']],
        'for compose with an APP_URL' => [['.env.example' => "APP_URL=http://localhost\n"]],
    ]);

    it('runs the project setup after Sail starts', function (): void {
        $root = lifecycleInitProject(['artisan' => '']);

        lifecycleInitWithDefaults()->run();

        expect(ProjectConfig::load($root)->hooks(HookStage::AfterUp)[0]->run)->toBe('composer run setup');
    });

    it('declares no hooks for compose', function (): void {
        $root = lifecycleInitProject();

        lifecycleInitWithDefaults()->run();

        expect(ProjectConfig::load($root)->hooks(HookStage::AfterUp))->toBe([]);
    });

    it('says rift.yaml was written', function (): void {
        lifecycleInitProject();

        lifecycleInitWithDefaults()->expectsOutputToContain('written');
    });

    it('lists the domains it will serve', function (string $fragment): void {
        lifecycleInitProject(['.env' => "BASE_NAME=shop\n"]);

        lifecycleInitWithDefaults()->expectsOutputToContain($fragment);
    })->with([
        'main checkout' => 'shop.test',
        'worktrees' => '<worktree>.shop.test',
    ]);

    it('points to rift setup as the next step', function (): void {
        lifecycleInitProject();

        lifecycleInitWithDefaults()->expectsOutputToContain('rift setup to bring it up');
    });

    it('does not mark the banner as a worktree in the main checkout', function (): void {
        lifecycleInitProject();

        lifecycleInitWithDefaults()->doesntExpectOutputToContain('init  worktree');
    });
});

describe('when the compose files publish configurable host ports', function (): void {
    it('keeps the port with its default', function (array $files, int $port): void {
        /** @var array<string, string> $files */
        $root = lifecycleInitProject([
            'compose.yaml' => "services:\n  app:\n    ports: ['\${APP_PORT:-8000}:80', '9000:9000']\n",
            ...$files,
        ]);

        lifecycleInitWithDefaults()->run();

        expect(ProjectConfig::load($root)->ports[0]->default)->toBe($port);
    })->with([
        'from the compose file' => [[], 8000],
        'from .env.example' => [['.env.example' => "APP_PORT=8100\n", '.env' => "APP_PORT=8200\n"], 8100],
        'from .env when .env.example has none' => [['.env' => "APP_PORT=8200\n"], 8200],
        'from the compose file when .env.example is not numeric' => [['.env.example' => "APP_PORT=auto\n"], 8000],
    ]);

    it('collects ports from every compose file', function (): void {
        $root = lifecycleInitProject([
            'compose.yaml' => "services:\n  app:\n    ports: ['\${APP_PORT:-8000}:80']\n",
            'docker-compose.yml' => "services:\n  vite:\n    ports: ['\${VITE_PORT:-5173}:5173']\n",
        ]);

        lifecycleInitWithDefaults()->run();

        expect(array_map(fn ($port): string => $port->key, ProjectConfig::load($root)->ports))->toBe(['APP_PORT', 'VITE_PORT']);
    });

    it('lists the ports it keeps clear', function (): void {
        lifecycleInitProject(['compose.yaml' => "services:\n  app:\n    ports: ['\${APP_PORT:-8000}:80']\n"]);

        lifecycleInitWithDefaults()->expectsOutputToContain('APP_PORT');
    });
});

describe('when reporting how the compose file joins the router network', function (): void {
    it('confirms a compose file that already joins it', function (): void {
        lifecycleInitProject();

        lifecycleInitWithDefaults()->expectsOutputToContain('compose.yaml already joins the router network');
    });

    it('warns about a compose file that does not join it yet', function (): void {
        lifecycleInitProject(['compose.yaml' => "services:\n  app:\n    image: nginx\n"]);

        lifecycleInitWithDefaults()->expectsOutputToContain('compose.yaml does not join the router network yet');
    });

    it('shows the stanza that joins it', function (): void {
        lifecycleInitProject(['compose.yaml' => "services:\n  app:\n    image: nginx\n"]);

        lifecycleInitWithDefaults()->expectsOutputToContain('name: "${ROUTER_NAME:?run rift setup first}"');
    });

    it('warns when there is no compose file', function (): void {
        $root = lifecycleInitProject();
        unlink($root . '/compose.yaml');

        lifecycleInitWithDefaults()->expectsOutputToContain('No compose file found here.');
    });
});

describe('when run in a worktree', function (): void {
    beforeEach(function (): void {
        $this->root = lifecycleWorktree('feat-x', '');
        unlink($this->root . '/rift.yaml');
    });

    it('marks the banner as a worktree', function (): void {
        lifecycleInitWithDefaults()->expectsOutputToContain('init  worktree');
    });

    it('writes rift.yaml into the worktree', function (): void {
        lifecycleInitWithDefaults()->run();

        expect($this->root . '/rift.yaml')->toBeFile();
    });
});

describe('when answering the prompts', function (): void {
    beforeEach(function (): void {
        $this->root = lifecycleInitProject();
    });

    it('writes the answers to rift.yaml', function (): void {
        lifecycleInitAnswering()->run();

        $config = ProjectConfig::load($this->root);

        expect([$config->preset, $config->name, $config->baseDomain, $config->appService, $config->appPort])
            ->toBe([Preset::Compose, 'shop', 'shop.test', 'web', 8080]);
    });

    it('writes the preset picked', function (): void {
        lifecycleInitAnswering(preset: 'sail')->run();

        expect(ProjectConfig::load($this->root)->preset)->toBe(Preset::Sail);
    });

    it('rejects a port outside 1 to 65535', function (string $port): void {
        lifecycleInitAnswering(port: $port, write: null)->assertExitCode(1);
    })->with([
        'zero' => '0',
        'too high' => '65536',
        'not a number' => 'eighty',
    ]);

    it('says which ports are valid', function (): void {
        lifecycleInitAnswering(port: '0', write: null)->expectsOutputToContain('Enter a port between 1 and 65535.');
    });

    it('does not write rift.yaml when the user declines', function (): void {
        lifecycleInitAnswering(write: false)->run();

        expect($this->root . '/rift.yaml')->not->toBeFile();
    });

    it('exits with success when the user declines', function (): void {
        lifecycleInitAnswering(write: false)->assertExitCode(0);
    });
});
