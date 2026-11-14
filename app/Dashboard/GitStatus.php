<?php

declare(strict_types=1);

namespace App\Dashboard;

/**
 * Where a checkout's branch stands against the main checkout's, and what it has not committed.
 */
final readonly class GitStatus
{
    /**
     * Create a new git status instance.
     *
     * @param  int  $ahead  Commits on this branch that the main branch lacks.
     * @param  int  $behind  Commits on the main branch that this branch lacks.
     * @param  list<string>  $changes  Uncommitted paths, relative to the checkout root.
     * @param  int|null  $changedAt  When an uncommitted file last changed, as a Unix time.
     */
    public function __construct(
        public int $ahead = 0,
        public int $behind = 0,
        public array $changes = [],
        public ?int $changedAt = null,
    ) {}
}
