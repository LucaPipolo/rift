<?php

declare(strict_types=1);

namespace App\Config;

use App\Exceptions\ConfigException;

/**
 * A host port a checkout publishes, and the .env key that carries it.
 */
final readonly class ForwardedPort
{
    /**
     * Create a new forwarded port instance.
     *
     * @param  string  $key  The .env key carrying the port.
     * @param  int  $default  The port to start scanning from.
     */
    public function __construct(
        public string $key,
        public int $default,
    ) {}

    /**
     * Create a forwarded port from its rift.yaml entry.
     *
     * @param  array<string, mixed>  $data  The entry from rift.yaml.
     * @param  string  $context  Where the entry sits, for error messages.
     *
     * @return self The forwarded port.
     *
     * @throws ConfigException
     */
    public static function fromArray(array $data, string $context): self
    {
        $key = $data['key'] ?? null;

        if (! is_string($key) || ! preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
            throw new ConfigException("{$context}: each port needs a `key` in SCREAMING_SNAKE_CASE.");
        }

        $default = $data['default'] ?? null;

        if (! is_int($default) || $default < 1 || $default > 65535) {
            throw new ConfigException("{$context}: `{$key}` needs a `default` port between 1 and 65535.");
        }

        return new self($key, $default);
    }
}
