<?php

declare(strict_types=1);

use App\Exceptions\AddressNotAssignedException;

describe('__construct()', function (): void {
    it('names the address and the fix in its message', function (): void {
        expect(new AddressNotAssignedException('127.0.0.2', 'Assign 127.0.0.2.')->getMessage())
            ->toBe('127.0.0.2 is not assigned to this machine. Assign 127.0.0.2.');
    });

    it('exposes the address and the fix', function (): void {
        expect(new AddressNotAssignedException('127.0.0.2', 'Assign 127.0.0.2.'))
            ->toMatchObject(['address' => '127.0.0.2', 'fix' => 'Assign 127.0.0.2.']);
    });
});
