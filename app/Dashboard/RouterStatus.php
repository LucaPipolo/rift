<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Config\RouterConfig;

/**
 * The shared router as the dashboard shows it.
 */
final readonly class RouterStatus
{
    /**
     * Create a new router status instance.
     *
     * @param  RouterConfig  $config  The router's settings.
     * @param  bool  $running  Whether its container is running.
     * @param  list<string>|null  $routes  The registered route ids, or null when the admin API is not answering.
     * @param  list<string>  $members  The containers on the router network.
     * @param  bool  $trusted  Whether this machine trusts its certificate authority.
     * @param  list<array{id: string, hosts: list<string>, upstream: string}>  $table  Each route's hosts and upstream.
     */
    public function __construct(
        public RouterConfig $config,
        public bool $running,
        public ?array $routes,
        public array $members,
        public bool $trusted,
        public array $table = [],
    ) {}
}
