<?php

declare(strict_types=1);

namespace App\Router;

/**
 * Whatever holds the ports the router needs: a container, or a process on the host.
 */
final readonly class PortHolder
{
    /**
     * Create a new port holder instance.
     *
     * @param  string  $name  The container or process name.
     * @param  bool  $onHost  Whether it is a process on the host rather than a Docker container.
     */
    public function __construct(
        public string $name,
        public bool $onHost = false,
    ) {}
}
