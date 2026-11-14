<?php

declare(strict_types=1);

use App\Enums\CheckoutState;

describe('count()', function (): void {
    it('counts every project\'s checkouts in a state', function (CheckoutState $state, int $count): void {
        expect(dashboardSnapshot()->count($state))->toBe($count);
    })->with([
        'serving' => [CheckoutState::Serving, 3],
        'unreachable' => [CheckoutState::Unreachable, 1],
        'unrouted' => [CheckoutState::Unrouted, 0],
    ]);
});
