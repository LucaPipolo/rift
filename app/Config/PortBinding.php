<?php

declare(strict_types=1);

namespace App\Config;

/**
 * The host address and ports the router's web traffic is published on.
 */
final readonly class PortBinding
{
    /**
     * Create a new port binding instance.
     *
     * @param  string|null  $address  The host IPv4 address, or null for every address.
     * @param  int  $http  The host port for HTTP.
     * @param  int  $https  The host port for HTTPS.
     */
    public function __construct(
        public ?string $address = null,
        public int $http = 80,
        public int $https = 443,
    ) {}

    /**
     * Get the host ports.
     *
     * @return list<int> The HTTP and HTTPS ports.
     */
    public function ports(): array
    {
        return [$this->http, $this->https];
    }

    /**
     * Describe the binding for messages.
     *
     * @return string The ports, such as "80 and 443" or "8080 and 8443 on 127.0.0.2".
     */
    public function describe(): string
    {
        return "{$this->http} and {$this->https}" . ($this->address === null ? '' : " on {$this->address}");
    }

    /**
     * Get where the router listens, as the status header shows it.
     *
     * @return string The addresses, such as ":80 :443" or "127.0.0.2:8080 127.0.0.2:8443".
     */
    public function listening(): string
    {
        return "{$this->address}:{$this->http} {$this->address}:{$this->https}";
    }

    /**
     * Get the value a router container is labelled with, to tell when it is out of date.
     *
     * @return string The binding, such as ":80:443" or "127.0.0.2:8080:8443".
     */
    public function label(): string
    {
        return "{$this->address}:{$this->http}:{$this->https}";
    }

    /**
     * Determine whether something published on an address competes with this binding.
     *
     * @param  string  $address  The address another listener is published on.
     *
     * @return bool Whether the two overlap.
     */
    public function covers(string $address): bool
    {
        return $this->address === null
            || $address === $this->address
            || in_array($address, ['', '0.0.0.0', '::', '[::]'], true);
    }

    /**
     * Get the host side of a `docker run -p` mapping for a port.
     *
     * @param  int  $port  The host port.
     *
     * @return string The port, prefixed with the address when there is one.
     */
    public function publish(int $port): string
    {
        return ($this->address === null ? '' : $this->address . ':') . $port;
    }
}
