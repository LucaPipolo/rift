<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * The router's bind address is not assigned to this machine, so Docker cannot publish on it.
 */
final class AddressNotAssignedException extends RouterException
{
    /**
     * Create a new address not assigned exception.
     *
     * @param  string  $address  The missing address.
     * @param  string  $fix  The advice for assigning it.
     */
    public function __construct(public readonly string $address, public readonly string $fix)
    {
        parent::__construct("{$address} is not assigned to this machine. {$fix}");
    }
}
