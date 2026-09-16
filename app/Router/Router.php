<?php

declare(strict_types=1);

namespace App\Router;

use App\Checkout\Instance;
use App\Config\RouterConfig;
use App\Docker\Docker;
use App\Exceptions\PortConflictException;
use App\Exceptions\RouterException;

/**
 * The Caddy container every project on this machine routes through.
 */
final readonly class Router
{
    /**
     * Create a new router instance.
     *
     * @param  RouterConfig  $config  The router's settings.
     * @param  CaddyAdmin  $admin  The admin API client.
     * @param  Docker  $docker  The Docker client.
     * @param  CertificateAuthority  $ca  The router's certificate authority.
     */
    public function __construct(
        public RouterConfig $config,
        public CaddyAdmin $admin,
        public Docker $docker,
        public CertificateAuthority $ca,
    ) {}

    /**
     * Create a router for a config.
     *
     * @param  RouterConfig  $config  The router's settings.
     *
     * @return self The router.
     */
    public static function for(RouterConfig $config): self
    {
        $docker = new Docker();

        return new self(
            config: $config,
            admin: new CaddyAdmin($config),
            docker: $docker,
            // Resolved from the container so tests can stand in for the OS and trust store.
            ca: app(CertificateAuthority::class, ['router' => $config, 'docker' => $docker]),
        );
    }

    /**
     * Determine whether the router container is running.
     *
     * @return bool Whether it is running.
     */
    public function isRunning(): bool
    {
        return $this->docker->containerRunning($this->config->container());
    }

    /**
     * Get the container other than the router that holds ports 80 and 443, if any.
     *
     * @return string|null The blocking container, or null.
     */
    public function blocker(): ?string
    {
        if ($this->isRunning()) {
            return null;
        }

        $holder = $this->docker->portHolder();

        return $holder === $this->config->container() ? null : $holder;
    }

    /**
     * Create the router network unless it exists.
     *
     * @return bool Whether the network was created.
     *
     * @throws RouterException
     */
    public function ensureNetwork(): bool
    {
        if ($this->docker->networkExists($this->config->network())) {
            return false;
        }

        return $this->docker->createNetwork($this->config->network())
            ?: throw new RouterException("Could not create the {$this->config->network()} network.");
    }

    /**
     * Start the router unless it is already running.
     *
     * @return bool Whether the router was started.
     *
     * @throws PortConflictException
     * @throws RouterException
     */
    public function ensureRunning(): bool
    {
        if ($this->isRunning()) {
            return false;
        }

        if (($blocker = $this->docker->portHolder()) !== null) {
            throw new PortConflictException($blocker);
        }

        $started = $this->docker->containerExists($this->config->container())
            ? $this->docker->startContainer($this->config->container())
            : $this->docker->runContainer($this->runArguments());

        if (! $started) {
            throw new RouterException('Could not start the router.');
        }

        if (! $this->admin->waitUntilReachable()) {
            throw new RouterException('The router started but its admin API never answered.');
        }

        return true;
    }

    /**
     * Stop the router container.
     *
     * @return bool Whether it stopped.
     */
    public function stop(): bool
    {
        return $this->docker->stopContainer($this->config->container());
    }

    /**
     * Register a checkout's route, or update its hosts if they changed.
     *
     * @param  Instance  $instance  The checkout to register.
     *
     * @return bool Whether the router's config changed.
     */
    public function register(Instance $instance): bool
    {
        $id = $instance->routeId();
        $hosts = $instance->hosts();

        if (! $this->admin->hasRoute($id)) {
            $this->admin->registerRoute(id: $id, hosts: $hosts, upstream: $instance->upstream());

            return true;
        }

        if ($this->admin->routeHosts($id) !== $hosts) {
            $this->admin->updateRouteHosts($id, $hosts);

            return true;
        }

        return false;
    }

    /**
     * Remove a checkout's route, leaving every sibling serving.
     *
     * @param  Instance  $instance  The checkout to deregister.
     *
     * @return bool Whether a route was removed.
     */
    public function deregister(Instance $instance): bool
    {
        if (! $this->isRunning() || ! $this->admin->hasRoute($instance->routeId())) {
            return false;
        }

        $this->admin->deregisterRoute($instance->routeId());

        return true;
    }

    /**
     * Get the `docker run` arguments that create the router container.
     *
     * @return list<string> The arguments.
     */
    private function runArguments(): array
    {
        return [
            '--name', $this->config->container(),
            '--network', $this->config->network(),
            '--restart', 'unless-stopped',
            '-p', '80:80',
            '-p', '443:443',
            '-p', '443:443/udp',
            '-p', '127.0.0.1:2019:2019',
            '-v', $this->config->dataVolume() . ':/data',
            '-v', $this->config->configVolume() . ':/config',
            '-e', 'CADDY_ADMIN=0.0.0.0:2019',
            $this->config->image,
        ];
    }
}
