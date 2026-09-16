<?php

declare(strict_types=1);

namespace App\Checkout;

use App\Config\ProjectConfig;
use App\Config\RouterConfig;

/**
 * One checkout of a project, bound to the router that serves it.
 */
final readonly class Instance
{
    /**
     * Create a new instance.
     *
     * @param  Checkout  $checkout  The checkout being served.
     * @param  ProjectConfig  $config  The project's rift.yaml settings.
     * @param  RouterConfig  $router  The router that serves the checkout.
     * @param  bool  $sharesRouter  Whether the router is the shared one.
     */
    public function __construct(
        public Checkout $checkout,
        public ProjectConfig $config,
        public RouterConfig $router,
        public bool $sharesRouter = true,
    ) {}

    /**
     * Bind a checkout to the shared router, or to the one its project pins.
     *
     * @param  Checkout  $checkout  The checkout being served.
     * @param  ProjectConfig  $config  The project's rift.yaml settings.
     * @param  RouterConfig  $router  The shared router's config.
     *
     * @return self The bound instance.
     */
    public static function make(Checkout $checkout, ProjectConfig $config, RouterConfig $router): self
    {
        $effective = $router->named($config->router);

        return new self(
            checkout: $checkout,
            config: $config,
            router: $effective,
            sharesRouter: $effective->name === $router->name,
        );
    }

    /**
     * Get the Compose project name of this checkout.
     *
     * @return string The Compose project name.
     */
    public function composeName(): string
    {
        return $this->checkout->composeName($this->config->name);
    }

    /**
     * Get the domain this checkout is served at.
     *
     * @return string The checkout's own domain.
     */
    public function domain(): string
    {
        return $this->checkout->domain($this->config->baseDomain, $this->config->name);
    }

    /**
     * Get every hostname the router answers for this checkout, its own domain first.
     *
     * Aliases hang off the checkout's domain, so a worktree gets api.feat-x.myapp.test
     * and never shadows the main checkout's api.myapp.test.
     *
     * @return list<string> The hostnames, own domain first.
     */
    public function hosts(): array
    {
        $domain = $this->domain();

        return [
            $domain,
            ...array_map(fn (string $host) => "{$host}.{$domain}", $this->config->hosts),
        ];
    }

    /**
     * Get the HTTPS URL of every hostname.
     *
     * @return list<string> The URLs, own domain first.
     */
    public function urls(): array
    {
        return array_map(fn (string $host) => 'https://' . $host, $this->hosts());
    }

    /**
     * Get the HTTPS URL of this checkout's own domain.
     *
     * @return string The URL.
     */
    public function url(): string
    {
        return 'https://' . $this->domain();
    }

    /**
     * Determine whether this checkout's aliases fall outside the project's wildcard.
     *
     * The base certificate covers *.myapp.test, which reaches api.myapp.test but
     * not a worktree's api.feat-x.myapp.test.
     *
     * @return bool Whether a worktree wildcard certificate is needed.
     */
    public function needsOwnWildcard(): bool
    {
        return ! $this->checkout->isPrimary() && $this->config->hosts !== [];
    }

    /**
     * Get the host alias this worktree's own name collides with, if any.
     *
     * A worktree named `myapp-api` would be served at api.myapp.test, the main
     * checkout's `api` alias.
     *
     * @return string|null The colliding alias, or null.
     */
    public function hostCollision(): ?string
    {
        $slug = $this->checkout->slugFor($this->config->name);

        return $slug !== null && in_array($slug, $this->config->hosts, true) ? $slug : null;
    }

    /**
     * Get the name of the app container the router dials.
     *
     * @return string The container name.
     */
    public function container(): string
    {
        return sprintf('%s-%s-1', $this->composeName(), $this->config->appService);
    }

    /**
     * Get the container and port the router forwards this checkout's hosts to.
     *
     * @return string The upstream as container:port.
     */
    public function upstream(): string
    {
        return $this->container() . ':' . $this->config->appPort;
    }

    /**
     * Get the id this checkout's route is registered under.
     *
     * @return string The route id.
     */
    public function routeId(): string
    {
        return $this->composeName();
    }

    /**
     * Get the path of this checkout's .env file.
     *
     * @return string The absolute .env path.
     */
    public function envPath(): string
    {
        return $this->checkout->root . '/.env';
    }
}
