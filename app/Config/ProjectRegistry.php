<?php

declare(strict_types=1);

namespace App\Config;

use App\Exceptions\ConfigException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * The projects rift has started on this machine, so `rift dash` can list them from anywhere.
 *
 * We decided to record projects as they come up rather than ask the router, because the
 * router only knows checkouts that are serving: a project whose checkouts are all stopped
 * would vanish from the dashboard.
 */
final readonly class ProjectRegistry
{
    private const string HEADER = "# Projects rift has started on this machine, listed by `rift dash`.\n"
        . "# Rift keeps this file up to date; remove a line to hide a project.\n";

    /**
     * Create a new project registry instance.
     *
     * @param  string  $path  The registry file path.
     */
    public function __construct(public string $path) {}

    /**
     * Get the path of the registry beside the global rift config.
     *
     * @return string The registry file path.
     */
    public static function path(): string
    {
        return dirname(RouterConfig::path()) . '/projects.yaml';
    }

    /**
     * Get the main checkout root of every recorded project that still has a rift.yaml.
     *
     * @return list<string> The project roots, sorted.
     *
     * @throws ConfigException
     */
    public function roots(): array
    {
        return array_values(array_filter($this->recorded(), ProjectConfig::existsIn(...)));
    }

    /**
     * Record a project, dropping any whose rift.yaml is gone.
     *
     * @param  string  $root  The project's main checkout root.
     *
     * @return bool Whether the file changed.
     *
     * @throws ConfigException
     */
    public function remember(string $root): bool
    {
        $directory = dirname($this->path);

        if (! is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }

        // Projects come up in parallel, so each update holds a lock while it reads and rewrites the list.
        $lockPath = $this->path . '.lock';
        $lock = is_writable($directory) && ! is_dir($lockPath) ? fopen($lockPath, 'c') : false;

        if ($lock === false) {
            throw new ConfigException("Could not write {$this->path}.");
        }

        flock($lock, LOCK_EX);

        try {
            return $this->update($root);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Add a project to the file and drop any whose rift.yaml is gone, under the caller's lock.
     *
     * @param  string  $root  The project's main checkout root.
     *
     * @return bool Whether the file changed.
     *
     * @throws ConfigException
     */
    private function update(string $root): bool
    {
        $recorded = $this->recorded();
        $roots = array_values(array_unique([...$this->roots(), $root]));

        sort($roots);

        if ($roots === $recorded) {
            return false;
        }

        // Moved into place whole, so the dashboard never reads a half-written list.
        $temporary = $this->path . '.' . getmypid() . '.tmp';
        file_put_contents($temporary, self::HEADER . Yaml::dump(['projects' => $roots]));
        rename($temporary, $this->path);

        return true;
    }

    /**
     * Get every root the file lists, whether or not it still exists.
     *
     * @return list<string> The roots, in file order.
     *
     * @throws ConfigException
     */
    private function recorded(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        try {
            $data = Yaml::parseFile($this->path) ?? [];
        } catch (ParseException $e) {
            throw new ConfigException("{$this->path} is not valid YAML: {$e->getMessage()}");
        }

        $projects = is_array($data) ? ($data['projects'] ?? []) : null;

        if (! is_array($projects)) {
            throw new ConfigException("{$this->path}: `projects` must be a list of directories.");
        }

        $roots = [];

        foreach ($projects as $root) {
            if (! is_string($root) || $root === '') {
                throw new ConfigException("{$this->path}: every entry under `projects` must be a directory path.");
            }

            $roots[] = $root;
        }

        return $roots;
    }
}
