<?php

declare(strict_types=1);

use App\Dashboard\ProjectStatus;

describe('name()', function (): void {
    it('uses the project name from rift.yaml', function (): void {
        expect(dashboardSnapshot()->projects[0]->name())->toBe('myapp');
    });

    it('falls back to the directory name when rift.yaml cannot be read', function (): void {
        expect(new ProjectStatus('/Sites/broken', null, error: 'bad yaml')->name())->toBe('broken');
    });
});

describe('upCount()', function (): void {
    it('counts the checkouts whose app container is running', function (): void {
        expect(dashboardSnapshot()->projects[0]->upCount())->toBe(3);
    });
});
