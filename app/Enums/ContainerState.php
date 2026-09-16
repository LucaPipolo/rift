<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether the router can actually reach the container behind a route.
 */
enum ContainerState: string
{
    case Running = 'running';
    case Down = 'down';
    case Unreachable = 'unreachable';

    /**
     * Determine a container's state from the running and router-network containers.
     *
     * A running container that never joined the router network is unreachable:
     * it looks healthy, yet every request to it fails with a 502.
     *
     * @param  string  $container  The container name.
     * @param  list<string>  $running  The running container names.
     * @param  list<string>  $reachable  The containers on the router network.
     *
     * @return self The container's state.
     */
    public static function of(string $container, array $running, array $reachable): self
    {
        return match (true) {
            ! in_array($container, $running, true) => self::Down,
            ! in_array($container, $reachable, true) => self::Unreachable,
            default => self::Running,
        };
    }

    /**
     * Determine whether the router can serve the container.
     *
     * @return bool Whether the container is running and reachable.
     */
    public function isHealthy(): bool
    {
        return $this === self::Running;
    }
}
