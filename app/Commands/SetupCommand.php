<?php

declare(strict_types=1);

namespace App\Commands;

use App\Env\EnvProjector;
use App\Support\Outcome;
use App\Support\Ui;

/**
 * Writes the current checkout's .env, then starts it.
 */
final class SetupCommand extends RiftCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Write this checkout\'s .env and bring it up';

    /**
     * Execute the console command.
     *
     * @param  EnvProjector  $projector  The env projector.
     *
     * @return int The exit code.
     */
    public function handle(EnvProjector $projector): int
    {
        $instance = $this->instance();

        if ($this->refuseHostCollision($instance)) {
            return self::FAILURE;
        }

        $this->header($instance->config->name);

        $projection = null;

        $this->step("Writing .env for {$instance->composeName()}", function () use ($projector, $instance, &$projection) {
            $projection = $projector->project($instance);

            return Outcome::done($projection->created ? 'created' : 'updated');
        });

        if ($this->hasFailed()) {
            return self::FAILURE;
        }

        $this->note('Project', "{$instance->composeName()} ({$instance->domain()})");

        if ($projection !== null && $projection->ports !== []) {
            $this->note('Ports', collect($projection->ports)
                ->map(fn (int $port, string $key) => $this->shorten($key) . ' ' . $port)
                ->implode(' · '));
        }

        Ui::blank();

        return $this->call('up');
    }

    /**
     * Shorten an env key into a readable port label.
     *
     * @param  string  $key  The env key, such as FORWARD_DB_PORT.
     *
     * @return string The label, such as "Db".
     */
    private function shorten(string $key): string
    {
        $label = preg_replace('/^FORWARD_|_PORT$|_DASHBOARD_PORT$/', '', $key) ?: $key;

        return ucfirst(strtolower(str_replace('_', ' ', $label)));
    }
}
