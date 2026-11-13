<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Answers how this machine resolves names and which addresses it holds.
 */
interface Network
{
    /**
     * Get the IPv4 addresses a hostname resolves to, the way the browser would resolve it.
     *
     * @param  string  $host  The hostname.
     *
     * @return list<string> The addresses, empty when it does not resolve to IPv4 in time.
     */
    public function addresses(string $host): array;

    /**
     * Determine whether an address is assigned to this machine.
     *
     * @param  string  $address  The IPv4 address.
     *
     * @return bool Whether this machine can bind it.
     */
    public function hasAddress(string $address): bool;

    /**
     * Get the advice for assigning a missing address to this machine.
     *
     * @param  string  $address  The IPv4 address.
     *
     * @return string The advice, with the command to run when there is one.
     */
    public function howToAssign(string $address): string;
}
