<?php

declare(strict_types=1);

namespace App\Env;

use App\Contracts\PortProbe;
use App\Exceptions\ConfigException;

/**
 * Picks host ports no other checkout has taken.
 */
final readonly class PortResolver
{
    /**
     * Create a new port resolver instance.
     *
     * @param  PortProbe  $probe  The probe that inspects host ports.
     */
    public function __construct(private PortProbe $probe) {}

    /**
     * Resolve a port, keeping the current one when it is still free.
     *
     * @param  int|null  $current  The port already in .env, if any.
     * @param  int  $default  The port to start scanning from.
     * @param  string  $composeProject  The checkout's Compose project name.
     * @param  list<int>  $reserved  Ports sibling worktrees have claimed but may not be serving yet.
     *
     * @return int The free port.
     *
     * @throws ConfigException
     */
    public function resolve(?int $current, int $default, string $composeProject, array $reserved = []): int
    {
        // A worktree's .env is seeded from the main checkout's, so its current
        // port is often one a sibling already claimed.
        $keepable = $current !== null && ! in_array($current, $reserved, true);

        if ($keepable && $this->available($current, $composeProject)) {
            return $current;
        }

        for ($port = $default; $port < 65536; $port++) {
            if (in_array($port, $reserved, true)) {
                continue;
            }

            if ($this->available($port, $composeProject)) {
                return $port;
            }
        }

        throw new ConfigException("No free host port at or above {$default}.");
    }

    /**
     * Determine whether a port is free for a Compose project.
     *
     * A port the project's own containers publish counts as free, since it is
     * about to be handed back to them.
     *
     * @param  int  $port  The host port.
     * @param  string  $composeProject  The checkout's Compose project name.
     *
     * @return bool Whether the port is free.
     */
    public function available(int $port, string $composeProject): bool
    {
        if (! $this->probe->isListening($port)) {
            return true;
        }

        return in_array($port, $this->probe->publishedBy($composeProject), true);
    }
}
