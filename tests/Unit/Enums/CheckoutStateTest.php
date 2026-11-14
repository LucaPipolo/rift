<?php

declare(strict_types=1);

use App\Docker\Container;
use App\Enums\CheckoutState;

describe('of()', function (): void {
    $app = fn (string $state = 'running', string $status = 'Up 2 hours'): Container => new Container('myapp-app-1', 'myapp', 'app', $state, $status, '');

    it('is serving when the app runs on the router network with a route', function () use ($app): void {
        expect(CheckoutState::of($app(), onNetwork: true, routed: true))->toBe(CheckoutState::Serving);
    });

    it('is unrouted when the app runs on the router network without a route', function () use ($app): void {
        expect(CheckoutState::of($app(), onNetwork: true, routed: false))->toBe(CheckoutState::Unrouted);
    });

    it('is unreachable when the app runs off the router network', function () use ($app): void {
        expect(CheckoutState::of($app(), onNetwork: false, routed: true))->toBe(CheckoutState::Unreachable);
    });

    it('is stopped without an app container', function (): void {
        expect(CheckoutState::of(null, onNetwork: false, routed: false))->toBe(CheckoutState::Stopped);
    });

    it('is stopped when the app exited cleanly or was stopped', function (int $code) use ($app): void {
        expect(CheckoutState::of($app('exited', "Exited ({$code}) 1 minute ago"), onNetwork: false, routed: false))->toBe(CheckoutState::Stopped);
    })->with(['clean exit' => 0, 'SIGTERM' => 143]);

    it('is stopped when the app was created but never started', function () use ($app): void {
        expect(CheckoutState::of($app('created', 'Created'), onNetwork: false, routed: false))->toBe(CheckoutState::Stopped);
    });

    it('is failed when the app exited with an error', function () use ($app): void {
        expect(CheckoutState::of($app('exited', 'Exited (137) 1 minute ago'), onNetwork: false, routed: false))->toBe(CheckoutState::Failed);
    });
});

describe('isUp()', function (): void {
    it('returns whether the app container is running', function (CheckoutState $state, bool $up): void {
        expect($state->isUp())->toBe($up);
    })->with([
        'serving' => [CheckoutState::Serving, true],
        'unrouted' => [CheckoutState::Unrouted, true],
        'unreachable' => [CheckoutState::Unreachable, true],
        'failed' => [CheckoutState::Failed, false],
        'stopped' => [CheckoutState::Stopped, false],
    ]);
});
