<?php

declare(strict_types=1);

namespace App\Commands;

use App\Contracts\Terminal;
use App\Dashboard\Companion;
use App\Dashboard\Rift;
use App\Exceptions\ConfigException;
use App\Support\Ui;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Process as SymfonyProcess;

/**
 * Opens a live dashboard of every project and worktree on this machine.
 */
final class DashCommand extends RiftCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dash';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Watch every project and worktree on this machine in one live view';

    /**
     * Execute the console command.
     *
     * @param  Terminal  $terminal  The terminal the command runs in.
     * @param  Companion  $companion  Finds rift-dash.
     * @param  Rift  $rift  How rift-dash runs this rift again.
     *
     * @return int The exit code.
     */
    public function handle(Terminal $terminal, Companion $companion, Rift $rift): int
    {
        if (! $terminal->interactive()) {
            Ui::muted('rift dash needs a terminal, so here is `rift status` instead.');

            return $this->call('status');
        }

        if (! $this->requireDocker()) {
            return self::FAILURE;
        }

        try {
            $dashboard = $companion->find();
        } catch (ConfigException $e) {
            return $this->bail($e->getMessage());
        }

        if ($dashboard === null) {
            return $this->bail(
                'rift dash is drawn by rift-dash, which is not installed beside rift. '
                . 'Reinstall with `brew reinstall rift`, or run `make build` from source.'
            );
        }

        // rift-dash runs this same rift for its snapshots and actions, so it is told how.
        return Process::forever()
            ->tty(SymfonyProcess::isTtySupported())
            ->env(['RIFT_COMMAND' => json_encode($rift->interactive(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)])
            ->run([$dashboard])
            ->exitCode() ?? self::FAILURE;
    }
}
