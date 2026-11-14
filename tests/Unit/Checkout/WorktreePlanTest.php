<?php

declare(strict_types=1);

use App\Checkout\WorktreePlan;

describe('for()', function (): void {
    $plan = fn (string $branch, string $root = '/Sites/myapp'): WorktreePlan => WorktreePlan::for($root, myappConfig(['hosts' => ['api']]), $branch);

    it('places the worktree beside the main checkout, named after the branch', function () use ($plan): void {
        expect($plan(' feat/csv-import '))->toEqual(new WorktreePlan(
            branch: 'feat/csv-import',
            path: '/Sites/myapp-feat-csv-import',
            domain: 'feat-csv-import.myapp.test',
            composeName: 'myapp-feat-csv-import',
        ));
    });

    it('refuses no branch at all', function () use ($plan): void {
        expect($plan('  ')->error)->toBe('Type a branch name.');
    });

    it('refuses a name git would refuse', function (string $branch) use ($plan): void {
        expect($plan($branch)->error)->toBe('That is not a valid branch name.');
    })->with(['-x', '/x', 'a..b', 'a//b', 'a b', 'x.lock', 'x/', 'x.', 'a@{b', 'HEAD', '.x', 'a/.b', 'x.lock/y']);

    it('creates a branch that does not exist yet', function () use ($plan): void {
        expect($plan('feat/new')->exists)->toBeFalse();
    });

    it('checks out a branch that exists already', function (): void {
        expect(WorktreePlan::for('/Sites/myapp', myappConfig(), 'feat/old', ['main', 'feat/old'])->exists)->toBeTrue();
    });

    // git refuses a second worktree on one branch, so the dashboard says where it is instead.
    it('refuses a branch already checked out in another worktree', function (): void {
        expect(WorktreePlan::for('/Sites/myapp', myappConfig(), 'main', ['main'], ['main' => '/Sites/myapp'])->error)
            ->toBe('main is already checked out at /Sites/myapp.');
    });

    it('refuses a branch that would answer at a host alias', function () use ($plan): void {
        expect($plan('api')->error)->toBe('A worktree named api would answer at a host alias of myapp.');
    });

    it('refuses a path that already exists', function () use ($plan): void {
        $root = scratch() . '/myapp';
        mkdir($root);
        mkdir($root . '-taken');

        expect($plan('taken', $root)->error)->toBe("{$root}-taken already exists.");
    });
});
