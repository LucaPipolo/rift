<?php

declare(strict_types=1);

use App\Dashboard\Companion;
use App\Dashboard\Rift;
use App\Exceptions\ConfigException;

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

describe('find() in a binary that carries rift-dash', function (): void {
    beforeEach(function (): void {
        $this->embedded = scratch() . '/rift-dash';
        file_put_contents($this->embedded, "#!/bin/sh\necho dash\n");
        $this->cache = scratch() . '/cache/rift';
    });

    it('unpacks it to the cache, named after its contents', function (): void {
        $found = new Companion(new Rift('micro', '/nowhere/rift'), '', $this->embedded, $this->cache)->find();

        expect($found)->toBe($this->cache . '/rift-dash-' . md5_file($this->embedded))
            ->and(file_get_contents((string) $found))->toBe("#!/bin/sh\necho dash\n")
            ->and(is_executable((string) $found))->toBeTrue();
    });

    it('reuses the copy it unpacked before', function (): void {
        $companion = new Companion(new Rift('micro', '/nowhere/rift'), '', $this->embedded, $this->cache);
        $first = (string) $companion->find();
        touch($first, 1_000_000_000);

        $companion->find();

        expect(filemtime($first))->toBe(1_000_000_000);
    });

    it('deletes the copies earlier releases unpacked', function (): void {
        mkdir($this->cache, 0o777, true);
        file_put_contents($this->cache . '/rift-dash-0123456789abcdef', 'old');
        file_put_contents($this->cache . '/other-file', 'kept');

        new Companion(new Rift('micro', '/nowhere/rift'), '', $this->embedded, $this->cache)->find();

        expect(array_map(basename(...), glob($this->cache . '/*') ?: []))->toBe(['other-file', 'rift-dash-' . md5_file($this->embedded)]);
    });

    it('leaves a copy another run is still writing', function (): void {
        mkdir($this->cache, 0o777, true);
        file_put_contents($this->cache . '/rift-dash-0123456789abcdef.42.part', 'half');

        new Companion(new Rift('micro', '/nowhere/rift'), '', $this->embedded, $this->cache)->find();

        expect($this->cache . '/rift-dash-0123456789abcdef.42.part')->toBeFile();
    });

    it('refuses a cache it cannot write to', function (): void {
        mkdir(dirname($this->cache));
        mkdir($this->cache, 0o555);

        try {
            new Companion(new Rift('micro', '/nowhere/rift'), '', $this->embedded, $this->cache)->find();
        } finally {
            chmod($this->cache, 0o755);
        }
    })->throws(ConfigException::class, 'Could not unpack rift-dash to');

    it('prefers it to one on the PATH', function (): void {
        executableIn($bin = scratch());

        expect(new Companion(new Rift('micro', '/nowhere/rift'), $bin, $this->embedded, $this->cache)->find())->toStartWith($this->cache);
    });
});

describe('cacheDirectory()', function (): void {
    $home = getenv('HOME');

    afterEach(function () use ($home): void {
        putenv('XDG_CACHE_HOME');
        putenv($home === false ? 'HOME' : 'HOME=' . $home);
    });

    it('sits under XDG_CACHE_HOME when it is set', function (): void {
        putenv('XDG_CACHE_HOME=/xdg-cache');

        expect(Companion::cacheDirectory())->toBe('/xdg-cache/rift');
    });

    it('sits under HOME/.cache otherwise', function (): void {
        putenv('XDG_CACHE_HOME');
        putenv('HOME=/home/you');

        expect(Companion::cacheDirectory())->toBe('/home/you/.cache/rift');
    });

    it('sits under the working directory without a home', function (): void {
        putenv('XDG_CACHE_HOME');
        putenv('HOME');

        expect(Companion::cacheDirectory())->toBe('./.cache/rift');
    });
});
