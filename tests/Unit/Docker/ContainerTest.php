<?php

declare(strict_types=1);

use App\Docker\Container;

describe('fromJson()', function (): void {
    $line = fn (array $fields): string => (string) json_encode([
        'Names' => 'myapp-app-1',
        'Labels' => 'com.docker.compose.config_files=/a/compose.yaml,/a/compose.override.yaml,com.docker.compose.project=myapp,com.docker.compose.service=app',
        'State' => 'running',
        'Status' => 'Up 2 hours',
        'Image' => 'myapp-app',
        ...$fields,
    ]);

    it('reads the container from a docker ps line', function () use ($line): void {
        expect(Container::fromJson($line([])))->toEqual(new Container('myapp-app-1', 'myapp', 'app', 'running', 'Up 2 hours', 'myapp-app'));
    });

    it('reads labels whose neighbours hold commas', function () use ($line): void {
        expect(Container::fromJson($line([]))?->project)->toBe('myapp');
    });

    it('returns null for a container without Compose labels', function () use ($line): void {
        expect(Container::fromJson($line(['Labels' => 'maintainer=someone'])))->toBeNull();
    });

    it('returns null for a label with an empty value', function () use ($line): void {
        expect(Container::fromJson($line(['Labels' => 'com.docker.compose.project=,com.docker.compose.service=app'])))->toBeNull();
    });

    it('returns null for a line that is not JSON', function (): void {
        expect(Container::fromJson('rift-router'))->toBeNull();
    });

    it('returns null for JSON that is not an object', function (): void {
        expect(Container::fromJson('"text"'))->toBeNull();
    });

    it('reads missing fields as empty', function () use ($line): void {
        expect(Container::fromJson($line(['Image' => null]))?->image)->toBe('');
    });
});

describe('isRunning()', function (): void {
    it('returns whether docker reports it running', function (string $state, bool $running): void {
        expect(new Container('a', 'p', 's', $state, '', '')->isRunning())->toBe($running);
    })->with([
        'running' => ['running', true],
        'exited' => ['exited', false],
        'restarting' => ['restarting', false],
    ]);
});

describe('exitCode()', function (): void {
    it('reads the exit code from the status', function (): void {
        expect(new Container('a', 'p', 's', 'exited', 'Exited (137) 4 minutes ago', '')->exitCode())->toBe(137);
    });

    it('returns null for a container that has not exited', function (): void {
        expect(new Container('a', 'p', 's', 'running', 'Up 2 hours', '')->exitCode())->toBeNull();
    });
});

describe('health()', function (): void {
    it('reads the health check result from the status', function (string $status, ?string $health): void {
        expect(new Container('a', 'p', 's', 'running', $status, '')->health())->toBe($health);
    })->with([
        'healthy' => ['Up 2 hours (healthy)', 'healthy'],
        'unhealthy' => ['Up 2 hours (unhealthy)', 'unhealthy'],
        'starting' => ['Up 3 seconds (health: starting)', 'starting'],
        'no health check' => ['Up 2 hours', null],
    ]);
});
