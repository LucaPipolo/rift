<?php

declare(strict_types=1);

use App\Config\Hook;
use App\Exceptions\ConfigException;

describe('fromArray()', function (): void {
    it('trims the command to run', function (): void {
        expect(Hook::fromArray(['run' => '  composer install  '], 'ctx')->run)->toBe('composer install');
    });

    it('runs in the app container when `on` is omitted', function (): void {
        expect(Hook::fromArray(['run' => 'composer install'], 'ctx')->onHost)->toBeFalse();
    });

    it('runs in the app container when `on` is app', function (): void {
        expect(Hook::fromArray(['run' => 'composer install', 'on' => 'app'], 'ctx')->onHost)->toBeFalse();
    });

    it('runs on the host when `on` is host', function (): void {
        expect(Hook::fromArray(['run' => 'composer install', 'on' => 'host'], 'ctx')->onHost)->toBeTrue();
    });

    it('reads the guard path from `unless_exists`', function (): void {
        expect(Hook::fromArray(['run' => 'composer install', 'unless_exists' => 'vendor'], 'ctx')->unlessExists)
            ->toBe('vendor');
    });

    it('has no guard path when `unless_exists` is omitted', function (): void {
        expect(Hook::fromArray(['run' => 'composer install'], 'ctx')->unlessExists)->toBeNull();
    });

    it('reads the step label from `label`', function (): void {
        expect(Hook::fromArray(['run' => 'composer install', 'label' => 'Installing'], 'ctx')->label)
            ->toBe('Installing');
    });

    it('has no step label when `label` is omitted', function (): void {
        expect(Hook::fromArray(['run' => 'composer install'], 'ctx')->label)->toBeNull();
    });

    it('throws a ConfigException when `run` is missing', function (): void {
        Hook::fromArray(['label' => 'Installing'], 'rift.yaml: `hooks.after_up`');
    })->throws(ConfigException::class, 'rift.yaml: `hooks.after_up`: every hook needs a non-empty `run`.');

    it('throws a ConfigException when `run` is blank or not a string', function (mixed $run): void {
        Hook::fromArray(['run' => $run], 'rift.yaml: `hooks.after_up`');
    })->with([
        'a null run' => null,
        'a list' => [['composer install']],
        'a whitespace-only run' => '   ',
    ])->throws(ConfigException::class, 'rift.yaml: `hooks.after_up`: every hook needs a non-empty `run`.');

    it('throws a ConfigException when `on` is neither app nor host', function (): void {
        Hook::fromArray(['run' => 'composer install', 'on' => 'remote'], 'ctx');
    })->throws(ConfigException::class, 'ctx: `on` must be either `app` or `host`.');

    it('throws a ConfigException when `unless_exists` is not a string', function (): void {
        Hook::fromArray(['run' => 'composer install', 'unless_exists' => ['vendor']], 'ctx');
    })->throws(ConfigException::class, 'ctx: `unless_exists` must be a string.');

    it('throws a ConfigException when `label` is not a string', function (): void {
        Hook::fromArray(['run' => 'composer install', 'label' => 42], 'ctx');
    })->throws(ConfigException::class, 'ctx: `label` must be a string.');
});

describe('applies()', function (): void {
    it('returns true when the hook has no guard path', function (): void {
        expect(new Hook('composer install')->applies(scratch()))->toBeTrue();
    });

    it('returns true when the guard path does not exist in the checkout', function (): void {
        expect(new Hook('composer install', unlessExists: 'vendor')->applies(scratch()))->toBeTrue();
    });

    it('returns false when the guard path exists in the checkout', function (): void {
        $root = scratch();
        mkdir($root . '/vendor');

        expect(new Hook('composer install', unlessExists: 'vendor')->applies($root))->toBeFalse();
    });

    it('resolves a guard path with a leading slash against the checkout', function (): void {
        $root = scratch();
        touch($root . '/.env');

        expect(new Hook('cp .env.example .env', unlessExists: '/.env')->applies($root))->toBeFalse();
    });
});
