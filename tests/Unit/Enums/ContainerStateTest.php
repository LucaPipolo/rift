<?php

declare(strict_types=1);

use App\Enums\ContainerState;

describe('of()', function (): void {
    // These need different fixes. Down means the containers never started;
    // unreachable means they started but never joined the router's network.
    it('returns Down when the container is not running', function (): void {
        expect(ContainerState::of('myapp-app-1', [], ['myapp-app-1']))->toBe(ContainerState::Down);
    });

    it('returns Unreachable when the container runs but is not on the router network', function (): void {
        expect(ContainerState::of('myapp-app-1', ['myapp-app-1'], []))->toBe(ContainerState::Unreachable);
    });

    it('returns Running when the container runs on the router network', function (): void {
        expect(ContainerState::of('myapp-app-1', ['myapp-app-1'], ['myapp-app-1']))->toBe(ContainerState::Running);
    });
});

describe('isHealthy()', function (): void {
    it('returns true only for a running, reachable container', function (ContainerState $state, bool $healthy): void {
        expect($state->isHealthy())->toBe($healthy);
    })->with([
        'running' => [ContainerState::Running, true],
        'down' => [ContainerState::Down, false],
        'unreachable' => [ContainerState::Unreachable, false],
    ]);
});
