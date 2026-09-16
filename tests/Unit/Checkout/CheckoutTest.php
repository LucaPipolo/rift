<?php

declare(strict_types=1);

use App\Checkout\Checkout;

describe('slugify()', function (): void {
    it('reduces a directory name to a slug Docker and DNS accept', function (string $name, string $slug): void {
        expect(Checkout::slugify($name))->toBe($slug);
    })->with([
        'slashes, spaces and symbols become single hyphens' => ['Feature/Ticket #42', 'feature-ticket-42'],
        'leading and trailing hyphens are trimmed' => ['--weird--', 'weird'],
        'underscores and dots are kept' => ['AB_c.d', 'ab_c.d'],
        'leading and trailing dots are trimmed' => ['.hidden.', 'hidden'],
    ]);
});

describe('isPrimary()', function (): void {
    it('returns true for the main checkout', function (): void {
        expect(primaryCheckout()->isPrimary())->toBeTrue();
    });

    it('returns false for a worktree', function (): void {
        expect(worktreeCheckout('feat-x')->isPrimary())->toBeFalse();
    });
});

describe('composeName()', function (): void {
    it('returns the bare project name for the main checkout', function (): void {
        expect(primaryCheckout()->composeName('myapp'))->toBe('myapp');
    });

    it('suffixes the project name with the slug for a worktree', function (): void {
        expect(worktreeCheckout('feat-x')->composeName('myapp'))->toBe('myapp-feat-x');
    });

    // Deriving the domain by stripping "${BASE_NAME}-" off the compose name
    // would mangle any project whose own name has hyphens.
    it('keeps a hyphenated project name intact', function (): void {
        expect(worktreeCheckout('feat-x', '/Sites/lcp-web')->composeName('lcp-web'))->toBe('lcp-web-feat-x');
    });

    // `git worktree add ../myapp-feat-x` is the usual spelling, and naming the
    // containers myapp-myapp-feat-x helps nobody.
    it('does not repeat the project name a worktree directory already carries', function (): void {
        expect(worktreeCheckout('myapp-feat-x')->composeName('myapp'))->toBe('myapp-feat-x');
    });

    it('does not collapse a worktree named exactly after the project', function (): void {
        expect(worktreeCheckout('myapp')->composeName('myapp'))->toBe('myapp-myapp');
    });

    it('agrees with the domain on the slug', function (string $directory): void {
        $checkout = worktreeCheckout($directory);

        expect($checkout->composeName('myapp'))->toBe('myapp-' . $checkout->slugFor('myapp'));
    })->with(['feat-x', 'myapp-feat-x', 'myapp', 'Myapp-Feat/X']);
});

describe('slugFor()', function (): void {
    it('returns null for the main checkout', function (): void {
        expect(primaryCheckout()->slugFor('myapp'))->toBeNull();
    });

    it('returns the slug unchanged when it does not start with the project name', function (): void {
        expect(worktreeCheckout('feat-x')->slugFor('myapp'))->toBe('feat-x');
    });

    it('drops the project name prefix from the slug', function (): void {
        expect(worktreeCheckout('myapp-feat-x')->slugFor('myapp'))->toBe('feat-x');
    });

    it('drops a prefix matching the slugified project name', function (): void {
        expect(worktreeCheckout('my_app-feat-x')->slugFor('My_App'))->toBe('feat-x');
    });

    it('keeps the full slug when the worktree is named exactly after the project', function (): void {
        expect(worktreeCheckout('myapp')->slugFor('myapp'))->toBe('myapp');
    });

    it('keeps the full slug when nothing follows the project name prefix', function (): void {
        $checkout = new Checkout('/Sites/trees/myapp-', '/Sites/myapp/.git', '/Sites/myapp', 'myapp-');

        expect($checkout->slugFor('myapp'))->toBe('myapp-');
    });
});

describe('domain()', function (): void {
    it('returns the base domain for the main checkout', function (): void {
        expect(primaryCheckout()->domain('myapp.test', 'myapp'))->toBe('myapp.test');
    });

    it('prefixes the base domain with the slug for a worktree', function (): void {
        expect(worktreeCheckout('feat-x')->domain('myapp.test', 'myapp'))->toBe('feat-x.myapp.test');
    });

    it('keeps a hyphenated project name intact', function (): void {
        expect(worktreeCheckout('feat-x', '/Sites/lcp-web')->domain('lcp-web.test', 'lcp-web'))->toBe('feat-x.lcp-web.test');
    });

    it('does not flatten a domain that is deeper than the project name', function (): void {
        expect(worktreeCheckout('feat-x')->domain('app.myapp.test', 'myapp'))->toBe('feat-x.app.myapp.test');
    });

    it('does not repeat the project name a worktree directory already carries', function (): void {
        expect(worktreeCheckout('myapp-feat-x')->domain('myapp.test', 'myapp'))->toBe('feat-x.myapp.test');
    });

    it('does not collapse a worktree named exactly after the project', function (): void {
        expect(worktreeCheckout('myapp')->domain('myapp.test', 'myapp'))->toBe('myapp.myapp.test');
    });

    it('agrees with the compose name on the slug', function (string $directory): void {
        $checkout = worktreeCheckout($directory);

        expect($checkout->domain('myapp.test', 'myapp'))->toBe($checkout->slugFor('myapp') . '.myapp.test');
    })->with(['feat-x', 'myapp-feat-x', 'myapp', 'Myapp-Feat/X']);
});
