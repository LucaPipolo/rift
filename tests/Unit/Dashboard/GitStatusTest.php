<?php

declare(strict_types=1);

use App\Dashboard\GitStatus;

describe('__construct()', function (): void {
    it('describes a clean branch level with main by default', function (): void {
        expect(new GitStatus())->toEqual(new GitStatus(ahead: 0, behind: 0, changes: [], changedAt: null));
    });
});
