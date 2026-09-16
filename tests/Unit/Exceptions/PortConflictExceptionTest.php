<?php

declare(strict_types=1);

use App\Exceptions\PortConflictException;

describe('__construct()', function (): void {
    it('names the container holding the ports in its message', function (): void {
        expect(new PortConflictException('traefik')->getMessage())->toBe('traefik already holds ports 80 and 443.');
    });

    it('exposes the container holding the ports', function (): void {
        expect(new PortConflictException('traefik')->holder)->toBe('traefik');
    });
});
