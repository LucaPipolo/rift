<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Ports 80 and 443 are held by a container other than the router.
 */
final class PortConflictException extends RouterException
{
    /**
     * Create a new port conflict exception.
     *
     * @param  string  $holder  The container holding the ports.
     */
    public function __construct(public readonly string $holder)
    {
        parent::__construct("{$holder} already holds ports 80 and 443.");
    }
}
