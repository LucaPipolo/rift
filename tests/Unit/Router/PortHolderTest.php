<?php

declare(strict_types=1);

use App\Router\PortHolder;

describe('__construct()', function (): void {
    it('exposes the name of the holder', function (): void {
        expect(new PortHolder('traefik')->name)->toBe('traefik');
    });

    it('treats the holder as a container by default', function (): void {
        expect(new PortHolder('traefik')->onHost)->toBeFalse();
    });

    it('marks a process on the host', function (): void {
        expect(new PortHolder('nginx', onHost: true)->onHost)->toBeTrue();
    });
});
