<?php

declare(strict_types=1);

use App\Checkout\CheckoutLocator;
use App\Exceptions\ConfigException;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

describe('locate()', function (): void {
    $git = function (
        string $root = '/Sites/myapp',
        string $commonDir = '/Sites/myapp/.git',
        ?string $worktrees = "worktree /Sites/myapp\nHEAD abc\nbranch refs/heads/main\n\nworktree /Sites/myapp-feat-x\nHEAD def\nbranch refs/heads/feat-x\n",
    ): void {
        Process::fake([
            '*--show-toplevel*' => Process::result($root . "\n"),
            '*--git-common-dir*' => Process::result($commonDir . "\n"),
            '*worktree*' => $worktrees === null ? Process::result('', 'fatal', 128) : Process::result($worktrees),
        ]);
    };

    it('returns the checkout root git reports', function () use ($git): void {
        $git(root: '/Sites/myapp-feat-x');

        expect(new CheckoutLocator()->locate('/Sites/myapp-feat-x/src')->root)->toBe('/Sites/myapp-feat-x');
    });

    it('returns the shared git directory', function () use ($git): void {
        $git(root: '/Sites/myapp-feat-x');

        expect(new CheckoutLocator()->locate('/Sites/myapp-feat-x')->commonDir)->toBe('/Sites/myapp/.git');
    });

    it('returns the parent of the shared git directory as the main checkout', function () use ($git): void {
        $git(root: '/Sites/myapp-feat-x');

        expect(new CheckoutLocator()->locate('/Sites/myapp-feat-x')->mainRoot)->toBe('/Sites/myapp');
    });

    it('returns no slug for the main checkout', function () use ($git): void {
        $git();

        expect(new CheckoutLocator()->locate('/Sites/myapp')->slug)->toBeNull();
    });

    it('returns the slugified directory name as the slug of a worktree', function () use ($git): void {
        $git(root: '/Sites/trees/Feat X');

        expect(new CheckoutLocator()->locate('/Sites/trees/Feat X')->slug)->toBe('feat-x');
    });

    it('returns every other worktree as a sibling', function () use ($git): void {
        $git();

        expect(new CheckoutLocator()->locate('/Sites/myapp')->siblings)->toBe(['/Sites/myapp-feat-x']);
    });

    it('returns no siblings when git cannot list the worktrees', function () use ($git): void {
        $git(worktrees: null);

        expect(new CheckoutLocator()->locate('/Sites/myapp')->siblings)->toBe([]);
    });

    it('runs git in the given directory', function () use ($git): void {
        $git();

        new CheckoutLocator()->locate('/Sites/myapp/src');

        Process::assertRan(fn (PendingProcess $process): bool => in_array('--show-toplevel', (array) $process->command, true)
            && $process->path === '/Sites/myapp/src');
    });

    it('runs git in the working directory when no directory is given', function () use ($git): void {
        $git();

        new CheckoutLocator()->locate();

        Process::assertRan(fn (PendingProcess $process): bool => in_array('--show-toplevel', (array) $process->command, true)
            && $process->path === getcwd());
    });

    it('throws a ConfigException when the directory is not inside a git checkout', function (): void {
        Process::fake(['*--show-toplevel*' => Process::result('', 'fatal: not a git repository', 128)]);

        new CheckoutLocator()->locate('/tmp');
    })->throws(ConfigException::class, 'Not inside a git checkout.');

    it('throws a ConfigException when git prints no checkout root', function (): void {
        Process::fake(['*--show-toplevel*' => Process::result("\n")]);

        new CheckoutLocator()->locate('/tmp');
    })->throws(ConfigException::class, 'Not inside a git checkout.');

    it('throws a ConfigException when git cannot report the shared git directory', function (): void {
        Process::fake([
            '*--show-toplevel*' => Process::result("/Sites/myapp\n"),
            '*--git-common-dir*' => Process::result('', 'fatal', 128),
        ]);

        new CheckoutLocator()->locate('/Sites/myapp');
    })->throws(ConfigException::class, 'Could not read this checkout\'s git directory.');
});
