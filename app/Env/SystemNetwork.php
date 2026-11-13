<?php

declare(strict_types=1);

namespace App\Env;

use App\Contracts\Network;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;

/**
 * Asks the operating system how names resolve and which addresses it holds.
 */
final readonly class SystemNetwork implements Network
{
    private const string MACOS = 'Darwin';

    private const int LOOKUP_TIMEOUT = 3;

    /**
     * Create a new system network instance.
     *
     * @param  string  $os  The operating system family, as PHP_OS_FAMILY reports it.
     */
    public function __construct(private string $os = PHP_OS_FAMILY) {}

    /**
     * Get the IPv4 addresses a hostname resolves to, the way the browser would resolve it.
     *
     * On macOS dscacheutil honours /etc/resolver, which DNS tools such as dig skip.
     * The lookup is bounded, since a resolver that stopped answering would otherwise
     * hang the check that exists to report it.
     *
     * @param  string  $host  The hostname.
     *
     * @return list<string> The addresses, empty when it does not resolve to IPv4 in time.
     */
    public function addresses(string $host): array
    {
        $command = $this->isMacOs()
            ? ['dscacheutil', '-q', 'host', '-a', 'name', $host]
            : ['getent', 'ahostsv4', $host];

        try {
            $result = Process::timeout(self::LOOKUP_TIMEOUT)->run($command);
        } catch (ProcessTimedOutException) {
            return [];
        }

        // dscacheutil prints "ip_address: 127.0.0.1"; getent prints "127.0.0.1  STREAM myapp.test".
        $pattern = $this->isMacOs() ? '/^ip_address: (\S+)$/m' : '/^(\d+(?:\.\d+){3})\s/m';

        if (! $result->successful() || preg_match_all($pattern, $result->output(), $matches) === 0) {
            return [];
        }

        return array_values(array_unique($matches[1]));
    }

    /**
     * Determine whether an address is assigned to this machine.
     *
     * Linux routes all of 127.0.0.0/8 to the loopback interface, while macOS
     * only answers on loopback addresses added with `ifconfig lo0 alias`.
     *
     * @param  string  $address  The IPv4 address.
     *
     * @return bool Whether this machine can bind it.
     */
    public function hasAddress(string $address): bool
    {
        if (! $this->isMacOs() && self::isLoopback($address)) {
            return true;
        }

        $result = Process::run($this->isMacOs() ? ['ifconfig'] : ['ip', '-4', '-o', 'addr', 'show']);

        // "inet 127.0.0.2 netmask" on macOS, "inet 192.168.1.5/24" on Linux.
        return $result->successful()
            && preg_match('/\binet ' . preg_quote($address, '/') . '[\s\/]/', $result->output()) === 1;
    }

    /**
     * Get the advice for assigning a missing address to this machine.
     *
     * @param  string  $address  The IPv4 address.
     *
     * @return string The advice, with the command to run when there is one.
     */
    public function howToAssign(string $address): string
    {
        return $this->isMacOs() && self::isLoopback($address)
            ? "Add it as a loopback alias: `sudo ifconfig lo0 alias {$address}`."
            : "Assign {$address} to a network interface, or change `router.bind`.";
    }

    /**
     * Determine whether an address is in 127.0.0.0/8.
     *
     * @param  string  $address  The IPv4 address.
     *
     * @return bool Whether it is a loopback address.
     */
    private static function isLoopback(string $address): bool
    {
        return str_starts_with($address, '127.');
    }

    /**
     * Determine whether this machine runs macOS.
     *
     * @return bool Whether it does.
     */
    private function isMacOs(): bool
    {
        return $this->os === self::MACOS;
    }
}
