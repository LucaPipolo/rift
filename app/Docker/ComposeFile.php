<?php

declare(strict_types=1);

namespace App\Docker;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * A project's Compose file, read but never edited.
 */
final readonly class ComposeFile
{
    /** @var list<string> */
    public const array NAMES = ['compose.yaml', 'compose.yml', 'docker-compose.yml', 'docker-compose.yaml'];

    /**
     * Create a new compose file instance.
     *
     * @param  string  $path  The compose file path.
     */
    public function __construct(public string $path) {}

    /**
     * Find every Compose file in a directory, in Compose's order of precedence.
     *
     * @param  string  $root  The directory to search.
     *
     * @return list<self> The compose files found.
     */
    public static function allIn(string $root): array
    {
        $files = [];

        foreach (self::NAMES as $name) {
            if (is_file($root . '/' . $name)) {
                $files[] = new self($root . '/' . $name);
            }
        }

        return $files;
    }

    /**
     * Get the file's name without its directory.
     *
     * @return string The filename.
     */
    public function name(): string
    {
        return basename($this->path);
    }

    /**
     * Determine whether the file joins the router network.
     *
     * @return bool Whether it references ROUTER_NAME.
     */
    public function joinsRouterNetwork(): bool
    {
        return is_file($this->path)
            && is_readable($this->path)
            && str_contains((string) file_get_contents($this->path), 'ROUTER_NAME');
    }

    /**
     * Get the configurable host ports the services publish, as `${KEY:-default}`.
     *
     * Hardcoded ports such as '9000:9000' have no key to move, so they are skipped.
     *
     * @return array<string, int> The default port, keyed by env key.
     */
    public function publishedPorts(): array
    {
        try {
            $data = Yaml::parseFile($this->path);
        } catch (ParseException) {
            return [];
        }

        if (! is_array($data) || ! is_array($data['services'] ?? null)) {
            return [];
        }

        $ports = [];

        foreach ($data['services'] as $service) {
            if (! is_array($service) || ! is_array($service['ports'] ?? null)) {
                continue;
            }

            foreach ($service['ports'] as $mapping) {
                // The host side comes first, so the first variable decides the host port.
                if (is_string($mapping) && preg_match('/\$\{([A-Z_][A-Z0-9_]*):-(\d+)\}/', $mapping, $match)) {
                    $ports[$match[1]] = (int) $match[2];
                }
            }
        }

        return $ports;
    }
}
