<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Exceptions\ConfigException;

/**
 * Finds rift-dash, the Go program that draws `rift dash`.
 *
 * We decided to draw the dashboard in a separate Go program, because PHP has no
 * maintained library for full-screen terminal views. Each release binary carries
 * the rift-dash built for its platform inside its PHAR, so one download is enough
 * and the two never come from different releases; it is unpacked to a cache on
 * first use, named after its contents so a new release never reuses an old copy.
 */
final readonly class Companion
{
    public const string NAME = 'rift-dash';

    /**
     * Create a new companion instance.
     *
     * @param  Rift  $rift  How this rift runs, which says where it lives.
     * @param  string  $path  The PATH to search when rift-dash is not beside rift.
     * @param  string  $embedded  Where the binary carries rift-dash, which a PHAR on its own does not.
     * @param  string  $cache  The directory rift-dash is unpacked to.
     */
    public function __construct(
        private Rift $rift,
        private string $path = '',
        private string $embedded = '',
        private string $cache = '',
    ) {}

    /**
     * Get the directory rift-dash is unpacked to, beside other caches.
     *
     * @return string The directory.
     */
    public static function cacheDirectory(): string
    {
        $home = getenv('HOME') ?: '.';

        return (getenv('XDG_CACHE_HOME') ?: $home . '/.cache') . '/rift';
    }

    /**
     * Find rift-dash.
     *
     * @return string|null Its path, or null when it is nowhere to be found.
     *
     * @throws ConfigException
     */
    public function find(): ?string
    {
        if ($this->embedded !== '' && is_file($this->embedded)) {
            return $this->unpack();
        }

        $directories = [...$this->rift->directories(), ...array_filter(explode(PATH_SEPARATOR, $this->path))];

        foreach ($directories as $directory) {
            $candidate = $directory . '/' . self::NAME;

            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Copy the rift-dash this binary carries out to the cache, unless an identical copy is there.
     *
     * @return string The unpacked rift-dash.
     *
     * @throws ConfigException
     */
    private function unpack(): string
    {
        $target = $this->cache . '/' . self::NAME . '-' . md5_file($this->embedded);

        if (! is_file($target)) {
            if (! is_dir($this->cache)) {
                @mkdir($this->cache, 0o755, true);
            }

            if (! is_dir($this->cache) || ! is_writable($this->cache)) {
                throw new ConfigException("Could not unpack rift-dash to {$this->cache}. Make it writable, or set XDG_CACHE_HOME to a directory that is.");
            }

            // Written aside, under a name of its own so two first runs never share one, and moved into place whole.
            $part = $target . '.' . getmypid() . '.part';
            copy($this->embedded, $part);
            chmod($part, 0o755);
            rename($part, $target);

            // Copies from earlier releases are never run again; one still being written belongs to another run.
            foreach (glob($this->cache . '/' . self::NAME . '-*') ?: [] as $old) {
                if ($old !== $target && ! str_ends_with($old, '.part')) {
                    unlink($old);
                }
            }
        }

        return $target;
    }
}
