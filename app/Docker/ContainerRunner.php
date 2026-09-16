<?php

declare(strict_types=1);

namespace App\Docker;

use App\Checkout\Instance;
use App\Config\Hook;
use App\Enums\HookStage;
use Closure;
use Illuminate\Support\Facades\Process;

/**
 * Drives a checkout's containers and hooks through its preset.
 */
final readonly class ContainerRunner
{
    /**
     * Create a new container runner instance.
     *
     * @param  (Closure(string): void)|null  $output  Receives every chunk of subprocess output.
     */
    public function __construct(private ?Closure $output = null) {}

    /**
     * Build and start the checkout's containers.
     *
     * @param  Instance  $instance  The checkout to start.
     *
     * @return bool Whether the command succeeded.
     */
    public function up(Instance $instance): bool
    {
        return $this->exec($instance->config->preset->upCommand(), $instance->checkout->root);
    }

    /**
     * Stop the checkout's containers.
     *
     * @param  Instance  $instance  The checkout to stop.
     *
     * @return bool Whether the command succeeded.
     */
    public function down(Instance $instance): bool
    {
        return $this->exec($instance->config->preset->downCommand(), $instance->checkout->root);
    }

    /**
     * Determine whether the preset's own commands can run in the checkout yet.
     *
     * @param  Instance  $instance  The checkout to inspect.
     *
     * @return bool Whether the preset is installed.
     */
    public function isBootstrapped(Instance $instance): bool
    {
        $required = $instance->config->preset->requires();

        return $required === null || file_exists($instance->checkout->root . '/' . $required);
    }

    /**
     * Get the hooks still to run for a stage, the preset's bootstrap first.
     *
     * @param  Instance  $instance  The checkout the hooks run in.
     * @param  HookStage  $stage  The lifecycle stage.
     *
     * @return list<Hook> The hooks that still apply.
     */
    public function hooksFor(Instance $instance, HookStage $stage): array
    {
        $hooks = $instance->config->hooks($stage);

        if ($stage === HookStage::BeforeUp && ($bootstrap = $instance->config->preset->bootstrap()) !== null) {
            array_unshift($hooks, $bootstrap);
        }

        return array_values(array_filter(
            $hooks,
            fn (Hook $hook) => $hook->applies($instance->checkout->root),
        ));
    }

    /**
     * Run a hook on the host or inside the app container.
     *
     * @param  Hook  $hook  The hook to run.
     * @param  Instance  $instance  The checkout the hook runs in.
     *
     * @return bool Whether the hook succeeded.
     */
    public function runHook(Hook $hook, Instance $instance): bool
    {
        $root = $instance->checkout->root;

        if ($hook->onHost) {
            // Host hooks run as shell strings so they can interpolate $(id -u) and $PWD.
            return $this->exec($hook->run, $root);
        }

        return $this->exec([
            ...$instance->config->preset->execPrefix($instance->config->appService),
            'sh', '-c', $hook->run,
        ], $root);
    }

    /**
     * Run a command without a timeout, streaming its output.
     *
     * @param  list<string>|string  $command  The command, as arguments or a shell string.
     * @param  string  $path  The working directory.
     *
     * @return bool Whether the command succeeded.
     */
    private function exec(array|string $command, string $path): bool
    {
        $pending = Process::path($path)->forever();

        $result = $this->output === null
            ? $pending->run($command)
            : $pending->run($command, fn (string $type, string $chunk) => ($this->output)($chunk));

        return $result->successful();
    }
}
