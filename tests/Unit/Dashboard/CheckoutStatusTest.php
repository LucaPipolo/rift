<?php

declare(strict_types=1);

use App\Dashboard\CheckoutStatus;
use App\Enums\CheckoutState;

describe('label()', function (): void {
    it('lists the main checkout as main', function (): void {
        expect(new CheckoutStatus(myapp(), 'main', CheckoutState::Stopped)->label())->toBe('main');
    });

    it('lists a worktree by its slug without the project prefix', function (): void {
        expect(new CheckoutStatus(myapp(slug: 'myapp-search'), 'feat/search', CheckoutState::Stopped)->label())->toBe('search');
    });
});

describe('root()', function (): void {
    it('returns the checkout root', function (): void {
        expect(new CheckoutStatus(myapp(slug: 'search'), null, CheckoutState::Stopped)->root())->toBe('/Sites/trees/search');
    });
});

describe('app()', function (): void {
    it('returns the container the router serves the checkout from', function (): void {
        $checkout = dashboardSnapshot()->projects[0]->checkouts[0];

        expect($checkout->app()?->service)->toBe('app');
    });

    it('returns null when the app container does not exist', function (): void {
        expect(new CheckoutStatus(myapp(), 'main', CheckoutState::Stopped)->app())->toBeNull();
    });
});
