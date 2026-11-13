<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Router\PortHolder;

/**
 * Ports 80 and 443 are held by a container or process other than the router.
 */
final class PortConflictException extends RouterException
{
    /**
     * Create a new port conflict exception.
     *
     * @param  PortHolder  $holder  The container or process holding the ports.
     */
    public function __construct(public readonly PortHolder $holder)
    {
        parent::__construct("{$holder->name} already holds ports 80 and 443.");
    }
}
