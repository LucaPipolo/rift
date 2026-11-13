<?php

declare(strict_types=1);

use App\Enums\Preset;
use App\Exceptions\ConfigException;

describe('cases()', function (): void {
    it('offers exactly the compose and sail presets', function (): void {
        expect(array_column(Preset::cases(), 'value'))->toBe(['compose', 'sail']);
    });
});

describe('parse()', function (): void {
    it('returns the preset with the given name', function (string $name, Preset $preset): void {
        expect(Preset::parse($name))->toBe($preset);
    })->with([
        'compose' => ['compose', Preset::Compose],
        'sail' => ['sail', Preset::Sail],
    ]);

    it('throws a ConfigException naming the valid presets when the name is unknown', function (): void {
        Preset::parse('podman');
    })->throws(ConfigException::class, 'Unknown preset `podman`. Use one of: compose, sail.');
});

describe('defaultService()', function (): void {
    it('returns the service the preset proxies to', function (Preset $preset, string $service): void {
        expect($preset->defaultService())->toBe($service);
    })->with([
        'compose' => [Preset::Compose, 'app'],
        'sail' => [Preset::Sail, 'laravel.test'],
    ]);
});

describe('upCommand()', function (): void {
    it('returns the command that builds and starts the containers', function (Preset $preset, array $command): void {
        expect($preset->upCommand())->toBe($command);
    })->with([
        'compose' => [Preset::Compose, ['docker', 'compose', 'up', '--build', '-d']],
        'sail' => [Preset::Sail, ['./vendor/bin/sail', 'up', '--build', '-d']],
    ]);
});

describe('downCommand()', function (): void {
    it('returns the command that stops the containers', function (Preset $preset, array $command): void {
        expect($preset->downCommand())->toBe($command);
    })->with([
        'compose' => [Preset::Compose, ['docker', 'compose', 'down']],
        'sail' => [Preset::Sail, ['./vendor/bin/sail', 'down']],
    ]);
});

describe('execPrefix()', function (): void {
    it('returns the command prefix that runs inside the service container', function (Preset $preset, array $prefix): void {
        expect($preset->execPrefix('web'))->toBe($prefix);
    })->with([
        'compose' => [Preset::Compose, ['docker', 'compose', 'exec', '-T', 'web']],
        'sail' => [Preset::Sail, ['./vendor/bin/sail', 'exec', '-T', 'web']],
    ]);
});

describe('bootstrap()', function (): void {
    it('returns null for compose', function (): void {
        expect(Preset::Compose->bootstrap())->toBeNull();
    });

    // Sail is itself a composer dependency, so a checkout without vendor/ has
    // no way to run its own containers. The preset breaks that circle.
    it('returns a host hook that installs Sail through a Composer container', function (): void {
        $bootstrap = Preset::Sail->bootstrap();

        expect([$bootstrap?->onHost, $bootstrap?->label])->toBe([true, 'Installing PHP dependencies'])
            ->and($bootstrap?->run)->toContain('composer:latest composer install --ignore-platform-reqs --no-scripts');
    });

    it('returns a Sail hook that is skipped once vendor/bin/sail exists', function (): void {
        expect(Preset::Sail->bootstrap()?->unlessExists)->toBe('vendor/bin/sail');
    });

    it('returns a Sail hook that applies to a fresh checkout', function (): void {
        expect(Preset::Sail->bootstrap()?->applies(scratch()))->toBeTrue();
    });

    it('returns a Sail hook that does not apply once Sail is installed', function (): void {
        $root = scratch();
        mkdir($root . '/vendor/bin', 0o777, true);
        touch($root . '/vendor/bin/sail');

        expect(Preset::Sail->bootstrap()?->applies($root))->toBeFalse();
    });
});

describe('requires()', function (): void {
    it('returns the file a set-up checkout has', function (Preset $preset, ?string $path): void {
        expect($preset->requires())->toBe($path);
    })->with([
        'compose needs nothing' => [Preset::Compose, null],
        'sail needs its binary' => [Preset::Sail, 'vendor/bin/sail'],
    ]);
});

describe('shellCommand()', function (): void {
    it('opens bash in the service where it has it, sh otherwise', function (Preset $preset, array $prefix): void {
        expect($preset->shellCommand('web'))->toBe([...$prefix, 'sh', '-c', 'command -v bash >/dev/null && exec bash || exec sh']);
    })->with([
        'compose' => [Preset::Compose, ['docker', 'compose', 'exec', 'web']],
        'sail' => [Preset::Sail, ['./vendor/bin/sail', 'exec', 'web']],
    ]);
});
