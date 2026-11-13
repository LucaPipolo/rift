<?php

declare(strict_types=1);

use App\Dashboard\Companion;
use App\Dashboard\Rift;

/**
 * Create an executable rift-dash in a directory.
 *
 * @param  string  $directory  The directory.
 *
 * @return string The rift-dash path.
 */
function executableIn(string $directory): string
{
    if (! is_dir($directory)) {
        mkdir($directory, 0o777, true);
    }

    file_put_contents($directory . '/rift-dash', "#!/bin/sh\n");
    chmod($directory . '/rift-dash', 0o755);

    return $directory . '/rift-dash';
}

describe('find()', function (): void {
    it('finds rift-dash beside the native binary', function (): void {
        $directory = scratch();
        $path = executableIn($directory);

        expect(new Companion(new Rift('micro', $directory . '/rift'))->find())->toBe($path);
    });

    it('finds rift-dash in builds/ from source', function (): void {
        $root = scratch();
        $path = executableIn($root . '/builds');

        expect(new Companion(new Rift('cli', '/usr/bin/php', $root . '/rift'))->find())->toBe($path);
    });

    it('finds rift-dash on the PATH', function (): void {
        $bin = scratch();
        $path = executableIn($bin);

        expect(new Companion(new Rift('micro', '/nowhere/rift'), '/missing' . PATH_SEPARATOR . $bin)->find())->toBe($path);
    });

    it('prefers the one beside rift to one on the PATH', function (): void {
        $directory = scratch();
        $beside = executableIn($directory);
        executableIn(scratch());

        expect(new Companion(new Rift('micro', $directory . '/rift'), scratch())->find())->toBe($beside);
    });

    it('skips a rift-dash that cannot run', function (): void {
        $directory = scratch();
        file_put_contents($directory . '/rift-dash', '');
        chmod($directory . '/rift-dash', 0o644);

        expect(new Companion(new Rift('micro', $directory . '/rift'))->find())->toBeNull();
    });

    it('returns null when there is none', function (): void {
        expect(new Companion(new Rift('micro', '/nowhere/rift'), '')->find())->toBeNull();
    });
});
