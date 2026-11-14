<?php

declare(strict_types=1);

namespace App\Enums;

use App\Docker\Container;

/**
 * How far a checkout is from serving at its own domain.
 */
enum CheckoutState: string
{
    case Serving = 'serving';
    case Unrouted = 'unrouted';
    case Unreachable = 'unreachable';
    case Failed = 'failed';
    case Stopped = 'stopped';

    /**
     * Determine a checkout's state from its app container and the router.
     *
     * Exit code 143 is a container stopped with SIGTERM, which is how Compose
     * stops one, so it counts as stopped rather than failed.
     *
     * @param  Container|null  $app  The app container, or null when it does not exist.
     * @param  bool  $onNetwork  Whether the app container is on the router network.
     * @param  bool  $routed  Whether the router has a route for the checkout.
     *
     * @return self The checkout's state.
     */
    public static function of(?Container $app, bool $onNetwork, bool $routed): self
    {
        return match (true) {
            $app === null => self::Stopped,
            ! $app->isRunning() => in_array($app->exitCode() ?? 0, [0, 143], true) ? self::Stopped : self::Failed,
            ! $onNetwork => self::Unreachable,
            ! $routed => self::Unrouted,
            default => self::Serving,
        };
    }

    /**
     * Determine whether the checkout's app container is running.
     *
     * @return bool Whether it is up, served or not.
     */
    public function isUp(): bool
    {
        return in_array($this, [self::Serving, self::Unrouted, self::Unreachable], true);
    }
}
