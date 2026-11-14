<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Config\ProjectConfig;

/**
 * One project as the dashboard shows it: its config and every checkout.
 */
final readonly class ProjectStatus
{
    /**
     * Create a new project status instance.
     *
     * @param  string  $root  The main checkout's root.
     * @param  ProjectConfig|null  $config  The main checkout's rift.yaml, or null when it cannot be read.
     * @param  list<CheckoutStatus>  $checkouts  The main checkout first, then its worktrees.
     * @param  string|null  $error  Why the project cannot be shown, if it cannot.
     */
    public function __construct(
        public string $root,
        public ?ProjectConfig $config,
        public array $checkouts = [],
        public ?string $error = null,
    ) {}

    /**
     * Get the name the dashboard lists the project under.
     *
     * @return string The project name, or the directory name when rift.yaml cannot be read.
     */
    public function name(): string
    {
        return $this->config === null ? basename($this->root) : $this->config->name;
    }

    /**
     * Count the checkouts whose app container is running.
     *
     * @return int The number of running checkouts.
     */
    public function upCount(): int
    {
        return count(array_filter($this->checkouts, fn (CheckoutStatus $checkout) => $checkout->state->isUp()));
    }
}
