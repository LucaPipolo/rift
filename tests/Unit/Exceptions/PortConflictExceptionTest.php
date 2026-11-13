<?php

declare(strict_types=1);

use App\Config\PortBinding;
use App\Exceptions\PortConflictException;
use App\Router\PortHolder;

describe('__construct()', function (): void {
    it('names the holder of the ports in its message', function (): void {
        expect(new PortConflictException(new PortHolder('traefik'))->getMessage())->toBe('traefik already holds ports 80 and 443.');
    });

    it('names the ports it holds in its message', function (): void {
        expect(new PortConflictException(new PortHolder('nginx', onHost: true), new PortBinding(http: 8080, https: 8443))->getMessage())
            ->toBe('nginx already holds ports 8080 and 8443.');
    });

    it('exposes the holder of the ports', function (): void {
        $holder = new PortHolder('traefik');

        expect(new PortConflictException($holder)->holder)->toBe($holder);
    });
});
