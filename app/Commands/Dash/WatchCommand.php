<?php

declare(strict_types=1);

namespace App\Commands\Dash;

use App\Commands\RiftCommand;
use App\Dashboard\Input;
use App\Dashboard\SnapshotCollector;
use App\Dashboard\SnapshotJson;
use App\Dashboard\Workspace;
use App\Exceptions\ConfigException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Prints a snapshot of every project as a line of JSON, again and again, for `rift-dash`.
 *
 * We decided to keep one process running rather than start one per snapshot,
 * because the collector keeps git status and certificate trust between
 * snapshots, and starting PHP alone costs a fair part of each one.
 */
final class WatchCommand extends RiftCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dash:watch {--interval=2000 : Milliseconds between snapshots}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Print a snapshot of every project as JSON until standard input closes';

    /**
     * Whether the command is hidden from the command list.
     *
     * @var bool
     */
    protected $hidden = true;

    /**
     * Execute the console command.
     *
     * @param  Workspace  $workspace  The projects to show.
     * @param  SnapshotCollector  $collector  Reads the machine's state.
     * @param  Input  $input  Where `rift-dash` asks for a fresh snapshot.
     *
     * @return int The exit code.
     */
    public function handle(Workspace $workspace, SnapshotCollector $collector, Input $input): int
    {
        $here = $workspace->here();
        $interval = max(100, (int) $this->option('interval'));

        do {
            try {
                $roots = $workspace->roots($here);
            } catch (ConfigException $e) {
                $this->getOutput()->getErrorStyle()->writeln($e->getMessage());

                return self::FAILURE;
            }

            $this->output->writeln(SnapshotJson::encode($collector->collect($roots)), OutputInterface::OUTPUT_RAW);

            $request = $input->wait($interval);

            // An action has just changed the machine, so read what is cached again too.
            if ($request === 'refresh') {
                $collector->forget();
            }
        } while ($request !== null);

        return self::SUCCESS;
    }
}
