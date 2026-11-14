<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Checkout\Checkout;
use App\Checkout\CheckoutLocator;
use App\Config\ProjectConfig;
use App\Config\ProjectRegistry;
use App\Exceptions\ConfigException;

/**
 * The projects the dashboard shows: every one Rift has started, and the one it runs in.
 */
final readonly class Workspace
{
    /**
     * Create a new workspace instance.
     *
     * @param  ProjectRegistry  $registry  The projects Rift has started.
     * @param  CheckoutLocator  $locator  Finds the checkout the dashboard runs in.
     */
    public function __construct(
        private ProjectRegistry $registry,
        private CheckoutLocator $locator,
    ) {}

    /**
     * Get the checkout the dashboard runs in, if it is a checkout of a Rift project.
     *
     * @param  string|null  $from  The directory, or null for the working directory.
     *
     * @return Checkout|null The checkout, or null outside one.
     */
    public function here(?string $from = null): ?Checkout
    {
        try {
            $checkout = $this->locator->locate($from);
        } catch (ConfigException) {
            return null;
        }

        return ProjectConfig::existsIn($checkout->mainRoot) ? $checkout : null;
    }

    /**
     * Get the main checkout root of every project to show.
     *
     * @param  Checkout|null  $here  The checkout the dashboard runs in, whose project is listed even before its first `rift up`.
     *
     * @return list<string> The roots, each once.
     *
     * @throws ConfigException
     */
    public function roots(?Checkout $here): array
    {
        $roots = $this->registry->roots();

        if ($here !== null) {
            $roots[] = $here->mainRoot;
        }

        return array_values(array_unique($roots));
    }
}
