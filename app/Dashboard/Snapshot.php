<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Enums\CheckoutState;

/**
 * Everything the dashboard shows at one moment: the router and every project.
 */
final readonly class Snapshot
{
    /**
     * Create a new snapshot instance.
     *
     * @param  RouterStatus  $router  The shared router.
     * @param  list<ProjectStatus>  $projects  Every project, sorted by name.
     */
    public function __construct(
        public RouterStatus $router,
        public array $projects,
    ) {}

    /**
     * Count every project's checkouts in a state.
     *
     * @param  CheckoutState  $state  The state to count.
     *
     * @return int The number of checkouts.
     */
    public function count(CheckoutState $state): int
    {
        $count = 0;

        foreach ($this->projects as $project) {
            foreach ($project->checkouts as $checkout) {
                $count += $checkout->state === $state ? 1 : 0;
            }
        }

        return $count;
    }
}
