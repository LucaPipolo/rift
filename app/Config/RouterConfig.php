<?php

declare(strict_types=1);

namespace App\Config;

use App\Exceptions\ConfigException;
use Symfony\Component\Yaml\Yaml;

/**
 * The settings of the router every project shares unless one pins its own.
 */
final readonly class RouterConfig
{
    public const string DEFAULT_NAME = 'rift-router';

    /**
     * Create a new router config instance.
     *
     * @param  string  $name  The router name shared by its container, network and volumes.
     * @param  string  $image  The Caddy image.
     * @param  string  $admin  The admin API URL.
     */
    public function __construct(
        public string $name = self::DEFAULT_NAME,
        public string $image = 'caddy:latest',
        public string $admin = 'http://localhost:2019',
    ) {}

    /**
     * Get the path of the global rift config file.
     *
     * @return string The config file path.
     */
    public static function path(): string
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE') ?: '.';
        $base = getenv('XDG_CONFIG_HOME') ?: $home . '/.config';

        return $base . '/rift/config.yaml';
    }

    /**
     * Load the router config, falling back to the defaults.
     *
     * @param  string|null  $path  The config file path, or null for the default.
     *
     * @return self The router config.
     *
     * @throws ConfigException
     */
    public static function load(?string $path = null): self
    {
        $path ??= self::path();

        if (! is_file($path)) {
            return new self();
        }

        $data = Yaml::parseFile($path);

        if (! is_array($data)) {
            throw new ConfigException("{$path} must contain a YAML mapping.");
        }

        $router = $data['router'] ?? [];

        if (! is_array($router)) {
            throw new ConfigException("{$path}: `router` must be a mapping.");
        }

        $defaults = new self();

        $name = $router['name'] ?? $defaults->name;
        $image = $router['image'] ?? $defaults->image;
        $admin = $router['admin'] ?? $defaults->admin;

        if (! is_string($name) || ! is_string($image) || ! is_string($admin)) {
            throw new ConfigException("{$path}: router `name`, `image`, and `admin` must be strings.");
        }

        return new self(
            name: $name,
            image: $image,
            admin: rtrim($admin, '/'),
        );
    }

    /**
     * Get the router's container name.
     *
     * @return string The container name.
     */
    public function container(): string
    {
        return $this->name;
    }

    /**
     * Get the Docker network checkouts join to be reachable by the router.
     *
     * @return string The network name.
     */
    public function network(): string
    {
        return $this->name;
    }

    /**
     * Get the volume holding the router's certificates.
     *
     * @return string The volume name.
     */
    public function dataVolume(): string
    {
        return "{$this->name}-data";
    }

    /**
     * Get the volume holding the router's autosaved config.
     *
     * @return string The volume name.
     */
    public function configVolume(): string
    {
        return "{$this->name}-config";
    }

    /**
     * Get the filename the router's CA takes in a trust store.
     *
     * @return string The certificate filename.
     */
    public function caCertFilename(): string
    {
        return "{$this->name}-ca.crt";
    }

    /**
     * Get the config for the router a project pinned, or this one.
     *
     * @param  string|null  $name  The pinned router name, or null.
     *
     * @return self The effective router config.
     */
    public function named(?string $name): self
    {
        if ($name === null || $name === $this->name) {
            return $this;
        }

        return new self(name: $name, image: $this->image, admin: $this->admin);
    }
}
