<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Config\PortBinding;
use App\Router\PortHolder;

/**
 * The router's web ports are held by a container or process other than the router.
 */
final class PortConflictException extends RouterException
{
    /**
     * Create a new port conflict exception.
     *
     * @param  PortHolder  $holder  The container or process holding the ports.
     * @param  PortBinding  $binding  The binding the router needs.
     */
    public function __construct(public readonly PortHolder $holder, PortBinding $binding = new PortBinding())
    {
        parent::__construct("{$holder->name} already holds ports {$binding->describe()}.");
    }
}
