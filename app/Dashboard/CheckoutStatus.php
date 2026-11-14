<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Checkout\Instance;
use App\Docker\Container;
use App\Enums\CheckoutState;

/**
 * One checkout as the dashboard shows it: its instance, branch, containers and state.
 */
final readonly class CheckoutStatus
{
    /**
     * Create a new checkout status instance.
     *
     * @param  Instance  $instance  The checkout bound to its router.
     * @param  string|null  $branch  The checked-out branch, or null when detached.
     * @param  CheckoutState  $state  How far it is from serving.
     * @param  list<Container>  $containers  Its Compose project's containers.
     * @param  GitStatus|null  $git  Its git status, or null until it is read.
     * @param  array<string, string|null>  $env  The .env values rift manages, null where a key is missing.
     */
    public function __construct(
        public Instance $instance,
        public ?string $branch,
        public CheckoutState $state,
        public array $containers = [],
        public ?GitStatus $git = null,
        public array $env = [],
    ) {}

    /**
     * Get the name the dashboard lists the checkout under.
     *
     * @return string The worktree label, or "main" for the main checkout.
     */
    public function label(): string
    {
        return $this->instance->checkout->slugFor($this->instance->config->name) ?? 'main';
    }

    /**
     * Get the checkout's root directory, which identifies it across refreshes.
     *
     * @return string The root directory.
     */
    public function root(): string
    {
        return $this->instance->checkout->root;
    }

    /**
     * Get the container the router serves the checkout from.
     *
     * @return Container|null The app container, or null when it does not exist.
     */
    public function app(): ?Container
    {
        foreach ($this->containers as $container) {
            if ($container->name === $this->instance->container()) {
                return $container;
            }
        }

        return null;
    }
}
