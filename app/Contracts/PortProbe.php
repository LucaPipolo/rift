<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Inspects which host ports are in use.
 */
interface PortProbe
{
    /**
     * Determine whether anything on this host is listening on the port.
     *
     * @param  int  $port  The host port.
     *
     * @return bool Whether the port is in use.
     */
    public function isListening(int $port): bool;

    /**
     * Get the host ports published by one Compose project's containers.
     *
     * @param  string  $composeProject  The Compose project name.
     *
     * @return list<int> The published host ports.
     */
    public function publishedBy(string $composeProject): array;
}
