<?php

declare(strict_types=1);

use App\Env\PortResolver;
use App\Exceptions\ConfigException;

describe('resolve()', function (): void {
    it('keeps the current port when nothing else has taken it', function (): void {
        expect(new PortResolver(probe(listening: [4321]))->resolve(4400, 4321, 'lcp-web'))->toBe(4400);
    });

    it('scans upwards from the default when the current port was lost', function (): void {
        expect(new PortResolver(probe(listening: [4321, 4322, 4323]))->resolve(4321, 4321, 'lcp-web'))->toBe(4324);
    });

    it('returns the default when there is no current port and the default is free', function (): void {
        expect(new PortResolver(probe())->resolve(null, 5173, 'myapp'))->toBe(5173);
    });

    // Otherwise a plain re-run would march every worktree up a port each time,
    // because its own containers are still holding the port it wants back.
    it('keeps a current port the checkout itself publishes', function (): void {
        expect(new PortResolver(probe(listening: [5173], published: [5173]))->resolve(5173, 5173, 'myapp'))->toBe(5173);
    });

    it('steps over a port another process is listening on', function (): void {
        expect(new PortResolver(probe(listening: [5173]))->resolve(null, 5173, 'myapp-feat-x'))->toBe(5174);
    });

    // Two worktrees prepared before either is started: nothing is listening,
    // so only the sibling's .env reveals that 5173 is spoken for.
    it('steps over ports a sibling has reserved but not yet opened', function (): void {
        expect(new PortResolver(probe())->resolve(null, 5173, 'myapp-feat-y', reserved: [5173, 5174]))->toBe(5175);
    });

    it('keeps the current port when only unrelated ports are reserved', function (): void {
        expect(new PortResolver(probe())->resolve(5173, 5173, 'myapp', reserved: [9999]))->toBe(5173);
    });

    // A fresh worktree copies the main checkout's .env wholesale, so its
    // "current" port is really the main checkout's port.
    it('gives up a current port a sibling has already reserved', function (): void {
        expect(new PortResolver(probe())->resolve(8080, 8080, 'demo-feat-x', reserved: [8080]))->toBe(8081);
    });

    it('throws a ConfigException when no port up to 65535 is free', function (): void {
        new PortResolver(probe(listening: [65534, 65535]))->resolve(null, 65534, 'myapp');
    })->throws(ConfigException::class, 'No free host port at or above 65534.');
});

describe('available()', function (): void {
    it('returns true when nothing is listening on the port', function (): void {
        expect(new PortResolver(probe())->available(5173, 'myapp'))->toBeTrue();
    });

    it('returns false when another project is listening on the port', function (): void {
        expect(new PortResolver(probe(listening: [5173]))->available(5173, 'myapp'))->toBeFalse();
    });

    it('returns true when the project itself publishes the port', function (): void {
        expect(new PortResolver(probe(listening: [5173], published: [5173]))->available(5173, 'myapp'))->toBeTrue();
    });
});
