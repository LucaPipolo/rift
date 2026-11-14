<?php

declare(strict_types=1);

namespace App\Enums;

use App\Config\Hook;
use App\Exceptions\ConfigException;

/**
 * The tool that drives a project's containers.
 */
enum Preset: string
{
    case Compose = 'compose';
    case Sail = 'sail';

    /**
     * Parse a preset name from rift.yaml.
     *
     * @param  string  $value  The preset name.
     *
     * @return self The preset.
     *
     * @throws ConfigException
     */
    public static function parse(string $value): self
    {
        return self::tryFrom($value) ?? throw new ConfigException(
            "Unknown preset `{$value}`. Use one of: " . implode(', ', array_column(self::cases(), 'value')) . '.'
        );
    }

    /**
     * Get the service the router proxies to unless the project names another.
     *
     * @return string The service name.
     */
    public function defaultService(): string
    {
        return match ($this) {
            self::Compose => 'app',
            self::Sail => 'laravel.test',
        };
    }

    /**
     * Get the command that builds and starts the containers.
     *
     * @return list<string> The command arguments.
     */
    public function upCommand(): array
    {
        return match ($this) {
            self::Compose => ['docker', 'compose', 'up', '--build', '-d'],
            self::Sail => ['./vendor/bin/sail', 'up', '--build', '-d'],
        };
    }

    /**
     * Get the command that stops the containers.
     *
     * @return list<string> The command arguments.
     */
    public function downCommand(): array
    {
        return match ($this) {
            self::Compose => ['docker', 'compose', 'down'],
            self::Sail => ['./vendor/bin/sail', 'down'],
        };
    }

    /**
     * Get the command prefix that runs a hook inside a service's container.
     *
     * @param  string  $service  The Compose service name.
     *
     * @return list<string> The command arguments preceding the hook.
     */
    public function execPrefix(string $service): array
    {
        return match ($this) {
            self::Compose => ['docker', 'compose', 'exec', '-T', $service],
            self::Sail => ['./vendor/bin/sail', 'exec', '-T', $service],
        };
    }

    /**
     * Get the command that opens an interactive shell in a service's container, bash where it has one.
     *
     * @param  string  $service  The Compose service name.
     *
     * @return list<string> The command arguments.
     */
    public function shellCommand(string $service): array
    {
        $prefix = match ($this) {
            self::Compose => ['docker', 'compose', 'exec', $service],
            self::Sail => ['./vendor/bin/sail', 'exec', $service],
        };

        return [...$prefix, 'sh', '-c', 'command -v bash >/dev/null && exec bash || exec sh'];
    }

    /**
     * Get the hook that installs what the up command itself depends on.
     *
     * Sail is a Composer dependency, so a fresh checkout installs it through a
     * throwaway Composer container before it can start its own.
     *
     * @return Hook|null The bootstrap hook, or null when none is needed.
     */
    public function bootstrap(): ?Hook
    {
        return match ($this) {
            self::Compose => null,
            self::Sail => new Hook(
                run: 'docker run --rm -u "$(id -u):$(id -g)" -v "$PWD:/var/www/html" '
                    . '-w /var/www/html composer:latest '
                    . 'composer install --ignore-platform-reqs --no-scripts',
                onHost: true,
                unlessExists: 'vendor/bin/sail',
                label: 'Installing PHP dependencies',
            ),
        };
    }

    /**
     * Get the file whose absence means the checkout was never set up.
     *
     * @return string|null The path relative to the checkout, or null.
     */
    public function requires(): ?string
    {
        return match ($this) {
            self::Compose => null,
            self::Sail => 'vendor/bin/sail',
        };
    }
}
