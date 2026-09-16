<?php

declare(strict_types=1);

use App\Config\ForwardedPort;
use App\Exceptions\ConfigException;

describe('fromArray()', function (): void {
    it('reads the key from the entry', function (): void {
        expect(ForwardedPort::fromArray(['key' => 'VITE_PORT', 'default' => 5173], 'ctx')->key)->toBe('VITE_PORT');
    });

    it('reads the default port from the entry', function (): void {
        expect(ForwardedPort::fromArray(['key' => 'VITE_PORT', 'default' => 5173], 'ctx')->default)->toBe(5173);
    });

    it('accepts a default port at either end of the valid range', function (int $port): void {
        expect(ForwardedPort::fromArray(['key' => 'APP_PORT', 'default' => $port], 'ctx')->default)->toBe($port);
    })->with([
        'the lowest port' => 1,
        'the highest port' => 65535,
    ]);

    it('throws a ConfigException when the key is not SCREAMING_SNAKE_CASE', function (mixed $key): void {
        ForwardedPort::fromArray(['key' => $key, 'default' => 5173], 'rift.yaml: `ports`');
    })->with([
        'a missing key' => null,
        'a non-string key' => 42,
        'a lowercase key' => 'vite_port',
        'a key starting with a digit' => '1PORT',
        'a key with a hyphen' => 'VITE-PORT',
    ])->throws(ConfigException::class, 'rift.yaml: `ports`: each port needs a `key` in SCREAMING_SNAKE_CASE.');

    it('throws a ConfigException when the default is not a valid port', function (mixed $default): void {
        ForwardedPort::fromArray(['key' => 'VITE_PORT', 'default' => $default], 'rift.yaml: `ports`');
    })->with([
        'a missing default' => null,
        'a numeric string' => '5173',
        'zero' => 0,
        'a port above 65535' => 65536,
    ])->throws(ConfigException::class, 'rift.yaml: `ports`: `VITE_PORT` needs a `default` port between 1 and 65535.');
});
