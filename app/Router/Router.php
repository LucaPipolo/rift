<?php

declare(strict_types=1);

namespace App\Router;

use App\Checkout\Instance;
use App\Config\RouterConfig;
use App\Contracts\PortProbe;
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
     * @param  PortProbe  $probe  The host port probe.
     */
    public function __construct(
        public RouterConfig $config,
        public CaddyAdmin $admin,
        public Docker $docker,
        public CertificateAuthority $ca,
        public PortProbe $probe,
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
            probe: app(PortProbe::class),
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
     * Get the container or process other than the router that holds ports 80 and 443, if any.
     *
     * @return PortHolder|null The blocking container or process, or null.
     */
    public function blocker(): ?PortHolder
    {
        return $this->isRunning() ? null : $this->portHolder();
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

        if (($blocker = $this->portHolder()) !== null) {
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
     * Find what holds ports 80 or 443: a container first, then any process on the host.
     *
     * Docker only knows its own containers, so web servers such as Valet's nginx
     * or a Podman container are only found through the host probe.
     *
     * @return PortHolder|null The holder, or null when the ports are free or held by the router.
     */
    private function portHolder(): ?PortHolder
    {
        $container = $this->docker->portHolder();

        if ($container !== null) {
            return $container === $this->config->container() ? null : new PortHolder($container);
        }

        foreach ([80, 443] as $port) {
            if (($process = $this->probe->listener($port)) !== null) {
                return new PortHolder($process, onHost: true);
            }
        }

        return null;
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
