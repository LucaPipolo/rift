<?php

declare(strict_types=1);

use App\Config\Hook;
use App\Docker\ContainerRunner;
use App\Enums\HookStage;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

describe('up()', function (): void {
    it('runs the preset up command', function (string $preset, array $command): void {
        Process::fake(['*' => '']);

        (new ContainerRunner())->up(myapp(['preset' => $preset]));

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === $command);
    })->with([
        'compose' => ['compose', ['docker', 'compose', 'up', '--build', '-d']],
        'sail' => ['sail', ['./vendor/bin/sail', 'up', '--build', '-d']],
    ]);

    it('runs in the checkout root', function (): void {
        Process::fake(['*' => '']);

        (new ContainerRunner())->up(myapp());

        Process::assertRan(fn (PendingProcess $process): bool => $process->path === '/Sites/myapp');
    });

    it('runs without a timeout', function (): void {
        Process::fake(['*' => '']);

        (new ContainerRunner())->up(myapp());

        Process::assertRan(fn (PendingProcess $process): bool => $process->timeout === null);
    });

    it('returns true when the command succeeds', function (): void {
        Process::fake(['*' => '']);

        expect((new ContainerRunner())->up(myapp()))->toBeTrue();
    });

    it('returns false when the command fails', function (): void {
        Process::fake(['*' => Process::result(exitCode: 1)]);

        expect((new ContainerRunner())->up(myapp()))->toBeFalse();
    });
});

describe('down()', function (): void {
    it('runs the preset down command', function (string $preset, array $command): void {
        Process::fake(['*' => '']);

        (new ContainerRunner())->down(myapp(['preset' => $preset]));

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === $command);
    })->with([
        'compose' => ['compose', ['docker', 'compose', 'down']],
        'sail' => ['sail', ['./vendor/bin/sail', 'down']],
    ]);

    it('runs in the checkout root', function (): void {
        Process::fake(['*' => '']);

        (new ContainerRunner())->down(myapp(slug: 'feat-x'));

        Process::assertRan(fn (PendingProcess $process): bool => $process->path === '/Sites/trees/feat-x');
    });

    it('returns false when the command fails', function (): void {
        Process::fake(['*' => Process::result(exitCode: 1)]);

        expect((new ContainerRunner())->down(myapp()))->toBeFalse();
    });
});

describe('isBootstrapped()', function (): void {
    it('returns true for a preset that needs no installed files', function (): void {
        expect((new ContainerRunner())->isBootstrapped(myappRootedAt(scratch())))->toBeTrue();
    });

    it('returns true when the preset command is installed', function (): void {
        $root = scratch();
        mkdir($root . '/vendor/bin', 0o777, true);
        touch($root . '/vendor/bin/sail');

        expect((new ContainerRunner())->isBootstrapped(myappRootedAt($root, ['preset' => 'sail'])))->toBeTrue();
    });

    it('returns false when the preset command is not installed yet', function (): void {
        expect((new ContainerRunner())->isBootstrapped(myappRootedAt(scratch(), ['preset' => 'sail'])))->toBeFalse();
    });
});

describe('hooksFor()', function (): void {
    it('returns the hooks the project declares for the stage', function (): void {
        $instance = myappRootedAt(scratch(), ['hooks' => [
            'after_up' => [['run' => 'php artisan migrate'], ['run' => 'npm run build']],
            'before_down' => [['run' => 'php artisan down']],
        ]]);

        expect(array_map(fn (Hook $hook): string => $hook->run, (new ContainerRunner())->hooksFor($instance, HookStage::AfterUp)))
            ->toBe(['php artisan migrate', 'npm run build']);
    });

    it('runs the preset bootstrap before the project hooks', function (): void {
        $instance = myappRootedAt(scratch(), [
            'preset' => 'sail',
            'hooks' => ['before_up' => [['run' => 'cp .env.example .env']]],
        ]);

        expect(array_map(fn (Hook $hook): ?string => $hook->label, (new ContainerRunner())->hooksFor($instance, HookStage::BeforeUp)))
            ->toBe(['Installing PHP dependencies', null]);
    });

    it('adds the preset bootstrap only before up', function (HookStage $stage): void {
        $instance = myappRootedAt(scratch(), ['preset' => 'sail']);

        expect((new ContainerRunner())->hooksFor($instance, $stage))->toBe([]);
    })->with([
        'after up' => [HookStage::AfterUp],
        'before down' => [HookStage::BeforeDown],
        'after down' => [HookStage::AfterDown],
    ]);

    it('adds no bootstrap for a preset that needs none', function (): void {
        expect((new ContainerRunner())->hooksFor(myappRootedAt(scratch()), HookStage::BeforeUp))->toBe([]);
    });

    it('drops a hook whose unless_exists path is already there', function (): void {
        $root = scratch();
        touch($root . '/.env');
        $instance = myappRootedAt($root, ['hooks' => ['before_up' => [
            ['run' => 'cp .env.example .env', 'unless_exists' => '.env'],
            ['run' => 'npm install', 'unless_exists' => 'node_modules'],
        ]]]);

        expect(array_map(fn (Hook $hook): string => $hook->run, (new ContainerRunner())->hooksFor($instance, HookStage::BeforeUp)))
            ->toBe(['npm install']);
    });

    it('drops the preset bootstrap once the preset is installed', function (): void {
        $root = scratch();
        mkdir($root . '/vendor/bin', 0o777, true);
        touch($root . '/vendor/bin/sail');

        expect((new ContainerRunner())->hooksFor(myappRootedAt($root, ['preset' => 'sail']), HookStage::BeforeUp))->toBe([]);
    });
});

describe('runHook()', function (): void {
    it('runs a host hook as a shell string in the checkout root', function (): void {
        Process::fake(['*' => '']);

        (new ContainerRunner())->runHook(new Hook('echo "$(id -u)"', onHost: true), myapp());

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === 'echo "$(id -u)"' && $process->path === '/Sites/myapp');
    });

    it('runs an app hook through a shell inside the app service', function (string $preset, array $command): void {
        Process::fake(['*' => '']);

        (new ContainerRunner())->runHook(new Hook('php artisan migrate'), myapp(['preset' => $preset]));

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === $command);
    })->with([
        'compose' => ['compose', ['docker', 'compose', 'exec', '-T', 'app', 'sh', '-c', 'php artisan migrate']],
        'sail' => ['sail', ['./vendor/bin/sail', 'exec', '-T', 'laravel.test', 'sh', '-c', 'php artisan migrate']],
    ]);

    it('returns true when the hook succeeds', function (): void {
        Process::fake(['*' => '']);

        expect((new ContainerRunner())->runHook(new Hook('php artisan migrate'), myapp()))->toBeTrue();
    });

    it('returns false when the hook fails', function (): void {
        Process::fake(['*' => Process::result(exitCode: 1)]);

        expect((new ContainerRunner())->runHook(new Hook('php artisan migrate'), myapp()))->toBeFalse();
    });

    it('streams the hook output to the output callback', function (): void {
        $chunks = [];
        $runner = new ContainerRunner(function (string $chunk) use (&$chunks): void {
            $chunks[] = $chunk;
        });

        $runner->runHook(new Hook('printf streamed', onHost: true), myappRootedAt(scratch()));

        expect(implode('', $chunks))->toBe('streamed');
    });
});
