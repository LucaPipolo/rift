<?php

declare(strict_types=1);

use App\Config\PortBinding;

describe('__construct()', function (): void {
    it('binds every address on ports 80 and 443 by default', function (): void {
        expect(new PortBinding())->toMatchObject(['address' => null, 'http' => 80, 'https' => 443]);
    });
});

describe('ports()', function (): void {
    it('returns the HTTP and HTTPS ports', function (): void {
        expect(new PortBinding(http: 8080, https: 8443)->ports())->toBe([8080, 8443]);
    });
});

describe('describe()', function (): void {
    it('names the ports', function (): void {
        expect(new PortBinding()->describe())->toBe('80 and 443');
    });

    it('names the ports and the address', function (): void {
        expect(new PortBinding('127.0.0.2', 8080, 8443)->describe())->toBe('8080 and 8443 on 127.0.0.2');
    });
});

describe('listening()', function (): void {
    it('returns the ports on every address', function (): void {
        expect(new PortBinding()->listening())->toBe(':80 :443');
    });

    it('returns the ports on the address', function (): void {
        expect(new PortBinding('127.0.0.2', 8080, 8443)->listening())->toBe('127.0.0.2:8080 127.0.0.2:8443');
    });
});

describe('label()', function (): void {
    it('returns the default binding', function (): void {
        expect(new PortBinding()->label())->toBe(':80:443');
    });

    it('returns the address and ports', function (): void {
        expect(new PortBinding('127.0.0.2', 8080, 8443)->label())->toBe('127.0.0.2:8080:8443');
    });
});

describe('covers()', function (): void {
    it('covers any address when bound to every address', function (string $address): void {
        expect(new PortBinding()->covers($address))->toBeTrue();
    })->with(['127.0.0.1', '192.168.1.5', '0.0.0.0']);

    it('covers its own address and every-address listeners', function (string $address): void {
        expect(new PortBinding('127.0.0.2')->covers($address))->toBeTrue();
    })->with([
        'its own address' => '127.0.0.2',
        'every IPv4 address' => '0.0.0.0',
        'every IPv6 address' => '[::]',
        'every address in the old format' => '::',
        'no address' => '',
    ]);

    it('does not cover another specific address', function (): void {
        expect(new PortBinding('127.0.0.2')->covers('127.0.0.1'))->toBeFalse();
    });
});

describe('publish()', function (): void {
    it('returns the port alone when bound to every address', function (): void {
        expect(new PortBinding()->publish(8443))->toBe('8443');
    });

    it('prefixes the port with the address', function (): void {
        expect(new PortBinding('127.0.0.2')->publish(8443))->toBe('127.0.0.2:8443');
    });
});
