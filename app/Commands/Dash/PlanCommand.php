<?php

declare(strict_types=1);

namespace App\Commands\Dash;

use App\Checkout\CheckoutLocator;
use App\Checkout\WorktreePlan;
use App\Commands\RiftCommand;
use App\Config\ProjectConfig;
use App\Exceptions\ConfigException;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Prints, as JSON, where a new worktree for a branch would go, or why it cannot be made.
 */
final class PlanCommand extends RiftCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dash:plan {project : The main checkout root} {branch : The branch, new or existing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Print where a new worktree for a branch would go, for rift dash';

    /**
     * Whether the command is hidden from the command list.
     *
     * @var bool
     */
    protected $hidden = true;

    /**
     * Execute the console command.
     *
     * @param  CheckoutLocator  $locator  Finds the project's checkouts and their branches.
     *
     * @return int The exit code.
     */
    public function handle(CheckoutLocator $locator): int
    {
        $root = (string) $this->argument('project');
        $branch = (string) $this->argument('branch');

        try {
            $config = ProjectConfig::load($root);
        } catch (ConfigException $e) {
            return $this->print(new WorktreePlan(trim($branch), '', '', '', error: $e->getMessage()));
        }

        $checkedOut = [];

        foreach ($locator->all($root) as ['checkout' => $checkout, 'branch' => $name]) {
            if ($name !== null) {
                $checkedOut[$name] = $checkout->root;
            }
        }

        $branches = Process::path($root)->run(['git', 'for-each-ref', '--format=%(refname:short)', 'refs/heads']);

        return $this->print(WorktreePlan::for($root, $config, $branch, array_values(array_filter(explode("\n", $branches->output()))), $checkedOut));
    }

    /**
     * Print a plan as one line of JSON.
     *
     * @param  WorktreePlan  $plan  The plan.
     *
     * @return int The exit code.
     */
    private function print(WorktreePlan $plan): int
    {
        $this->output->writeln(json_encode([
            'branch' => $plan->branch,
            'path' => $plan->path,
            'domain' => $plan->domain,
            'composeName' => $plan->composeName,
            'exists' => $plan->exists,
            'error' => $plan->error,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);

        return self::SUCCESS;
    }
}
