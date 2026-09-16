<?php

declare(strict_types=1);

namespace App\Env;

use App\Exceptions\ConfigException;

/**
 * A .env file edited in place, keeping its comments and order.
 */
final class EnvFile
{
    /** @var list<string> */
    private array $lines;

    /**
     * Create a new env file instance.
     *
     * @param  string  $path  The file path.
     * @param  string  $contents  The file's current contents.
     */
    private function __construct(
        public readonly string $path,
        string $contents,
    ) {
        $this->lines = $contents === '' ? [] : self::split($contents);
    }

    /**
     * Open the env file at a path, which need not exist yet.
     *
     * @param  string  $path  The file path.
     *
     * @return self The env file.
     */
    public static function at(string $path): self
    {
        return new self($path, is_file($path) ? (string) file_get_contents($path) : '');
    }

    /**
     * Determine whether the file exists on disk.
     *
     * @return bool Whether the file exists.
     */
    public function exists(): bool
    {
        return is_file($this->path);
    }

    /**
     * Get a key's value, treating an empty value as absent.
     *
     * @param  string  $key  The env key.
     *
     * @return string|null The value, or null when absent or empty.
     */
    public function get(string $key): ?string
    {
        foreach ($this->lines as $line) {
            if (str_starts_with($line, $key . '=')) {
                $value = substr($line, strlen($key) + 1);

                return $value === '' ? null : $value;
            }
        }

        return null;
    }

    /**
     * Set a key where it already sits, or append it.
     *
     * @param  string  $key  The env key.
     * @param  string  $value  The value.
     *
     * @return $this The env file, for chaining.
     */
    public function set(string $key, string $value): self
    {
        $line = $key . '=' . $value;

        foreach ($this->lines as $index => $existing) {
            if (str_starts_with($existing, $key . '=')) {
                $this->lines[$index] = $line;

                return $this;
            }
        }

        // Keep a single trailing newline rather than growing a gap each run.
        if ($this->lines !== [] && end($this->lines) === '') {
            array_pop($this->lines);
        }

        $this->lines[] = $line;
        $this->lines[] = '';

        return $this;
    }

    /**
     * Seed the file from the first candidate that exists, unless it already exists.
     *
     * @param  list<string>  $candidates  The files to copy from, in order of preference.
     *
     * @return bool Whether the file was seeded.
     *
     * @throws ConfigException
     */
    public function seedFrom(array $candidates): bool
    {
        if ($this->exists()) {
            return false;
        }

        foreach ($candidates as $candidate) {
            if (! is_file($candidate) || ! is_readable($candidate)) {
                continue;
            }

            $this->lines = self::split((string) file_get_contents($candidate));
            $this->write();

            return true;
        }

        throw new ConfigException(
            'No .env to start from. Expected one of: ' . implode(', ', $candidates) . '.'
        );
    }

    /**
     * Write the file atomically.
     *
     * @return void
     *
     * @throws ConfigException
     */
    public function write(): void
    {
        $contents = implode("\n", $this->lines);

        if (! str_ends_with($contents, "\n")) {
            $contents .= "\n";
        }

        // Write beside the target and move it into place, so an interrupted run
        // never leaves a half-written .env.
        $temporary = $this->path . '.rift-tmp';

        if (is_dir($this->path) || ! is_writable(dirname($this->path))) {
            throw new ConfigException("Could not write {$this->path}.");
        }

        file_put_contents($temporary, $contents);
        rename($temporary, $this->path);
    }

    /**
     * Split file contents into lines, normalising line endings.
     *
     * @param  string  $contents  The file contents.
     *
     * @return list<string> The lines.
     */
    private static function split(string $contents): array
    {
        return explode("\n", str_replace("\r\n", "\n", $contents));
    }
}
