<?php

declare(strict_types=1);

namespace App\Docker;

use App\Config\PortBinding;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;

/**
 * Runs the Docker CLI commands rift needs.
 */
final class Docker
{
    /** @var list<string>|null */
    private ?array $running = null;

    /**
     * Determine whether a Docker daemon is answering.
     *
     * @return bool Whether the daemon is reachable.
     */
    public function available(): bool
    {
        return Process::run(['docker', 'info'])->successful();
    }

    /**
     * Determine whether a container is running.
     *
     * @param  string  $name  The container name.
     *
     * @return bool Whether it is running.
     */
    public function containerRunning(string $name): bool
    {
        return in_array($name, $this->runningNames(), true);
    }

    /**
     * Get the names of every running container, cached until a container is started or stopped.
     *
     * @return list<string> The container names.
     */
    public function runningNames(): array
    {
        return $this->running ??= array_values($this->names(
            ['docker', 'ps', '--format', '{{.Names}}']
        )->all());
    }

    /**
     * Get every Compose container on this machine, running or not.
     *
     * @return list<Container> The containers, in the order Docker lists them.
     */
    public function containers(): array
    {
        $result = Process::run(['docker', 'ps', '-a', '--no-trunc', '--format', '{{json .}}']);

        if (! $result->successful()) {
            return [];
        }

        $containers = [];

        foreach (explode("\n", trim($result->output())) as $line) {
            $container = Container::fromJson($line);

            if ($container !== null) {
                $containers[] = $container;
            }
        }

        return $containers;
    }

    /**
     * Determine whether a container exists, running or not.
     *
     * @param  string  $name  The container name.
     *
     * @return bool Whether it exists.
     */
    public function containerExists(string $name): bool
    {
        return $this->names(['docker', 'ps', '-a', '--format', '{{.Names}}'])->contains($name);
    }

    /**
     * Create and start a detached container.
     *
     * @param  list<string>  $arguments  The `docker run` options followed by the image.
     *
     * @return bool Whether the container started.
     */
    public function runContainer(array $arguments): bool
    {
        return $this->changesContainers(['docker', 'run', '-d', ...$arguments]);
    }

    /**
     * Start an existing container.
     *
     * @param  string  $name  The container name.
     *
     * @return bool Whether it started.
     */
    public function startContainer(string $name): bool
    {
        return $this->changesContainers(['docker', 'start', $name]);
    }

    /**
     * Stop a running container.
     *
     * @param  string  $name  The container name.
     *
     * @return bool Whether it stopped.
     */
    public function stopContainer(string $name): bool
    {
        return $this->changesContainers(['docker', 'stop', $name]);
    }

    /**
     * Force-remove a container.
     *
     * @param  string  $name  The container name.
     *
     * @return bool Whether it was removed.
     */
    public function removeContainer(string $name): bool
    {
        return $this->changesContainers(['docker', 'rm', '-f', $name]);
    }

    /**
     * Determine whether a file exists inside a running container.
     *
     * @param  string  $container  The container name.
     * @param  string  $path  The path inside the container.
     *
     * @return bool Whether the file exists.
     */
    public function fileExistsIn(string $container, string $path): bool
    {
        return $this->succeeds(['docker', 'exec', $container, 'test', '-f', $path]);
    }

    /**
     * Copy a file out of a container.
     *
     * @param  string  $container  The container name.
     * @param  string  $source  The path inside the container.
     * @param  string  $destination  The path on the host.
     *
     * @return bool Whether the copy succeeded.
     */
    public function copyFrom(string $container, string $source, string $destination): bool
    {
        return $this->succeeds(['docker', 'cp', $container . ':' . $source, $destination]);
    }

    /**
     * Determine whether a network exists.
     *
     * @param  string  $name  The network name.
     *
     * @return bool Whether it exists.
     */
    public function networkExists(string $name): bool
    {
        return $this->names(['docker', 'network', 'ls', '--format', '{{.Name}}'])->contains($name);
    }

    /**
     * Create a network.
     *
     * @param  string  $name  The network name.
     *
     * @return bool Whether it was created.
     */
    public function createNetwork(string $name): bool
    {
        return $this->succeeds(['docker', 'network', 'create', $name]);
    }

    /**
     * Get the names of the containers attached to a network.
     *
     * @param  string  $network  The network name.
     *
     * @return list<string> The container names.
     */
    public function networkMembers(string $network): array
    {
        $result = Process::run([
            'docker', 'network', 'inspect', $network,
            '--format', '{{range .Containers}}{{.Name}}{{"\n"}}{{end}}',
        ]);

        if (! $result->successful()) {
            return [];
        }

        return array_values(collect(explode("\n", trim($result->output())))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->all());
    }

    /**
     * Get the container publishing a web port the router needs, if any.
     *
     * @param  PortBinding  $binding  The address and ports the router needs.
     *
     * @return string|null The container name, or null.
     */
    public function portHolder(PortBinding $binding): ?string
    {
        $result = Process::run(['docker', 'ps', '--format', '{{.Names}}\t{{.Ports}}']);

        if (! $result->successful()) {
            return null;
        }

        foreach (explode("\n", trim($result->output())) as $line) {
            // In "0.0.0.0:443->443/tcp, [::]:443->443/tcp" each address and host port precede an arrow.
            preg_match_all('/([^\s,]*):(\d+)->/', $line, $mappings, PREG_SET_ORDER);

            foreach ($mappings as [, $address, $port]) {
                if (in_array((int) $port, $binding->ports(), true) && $binding->covers($address)) {
                    return explode("\t", $line)[0];
                }
            }
        }

        return null;
    }

    /**
     * Get the value of a label on a container, running or not.
     *
     * @param  string  $container  The container name.
     * @param  string  $label  The label key.
     *
     * @return string|null The value, or null when the label or the container is missing.
     */
    public function containerLabel(string $container, string $label): ?string
    {
        $result = Process::run([
            'docker', 'inspect',
            '--format', '{{index .Config.Labels "' . $label . '"}}',
            $container,
        ]);

        $value = trim($result->output());

        // Go templates print "<no value>" for a missing map key.
        return $result->successful() && $value !== '' && $value !== '<no value>' ? $value : null;
    }

    /**
     * Get the names of the containers built from an image, running or not.
     *
     * @param  string  $image  The image name.
     *
     * @return list<string> The container names.
     */
    public function containersFromImage(string $image): array
    {
        return array_values($this->names([
            'docker', 'ps', '-a', '--filter', 'ancestor=' . $image, '--format', '{{.Names}}',
        ])->all());
    }

    /**
     * Run a command and report whether it succeeded.
     *
     * @param  list<string>  $command  The command arguments.
     *
     * @return bool Whether it succeeded.
     */
    private function succeeds(array $command): bool
    {
        return Process::run($command)->successful();
    }

    /**
     * Run a command that changes which containers are running, clearing the cached list.
     *
     * @param  list<string>  $command  The command arguments.
     *
     * @return bool Whether it succeeded.
     */
    private function changesContainers(array $command): bool
    {
        $this->running = null;

        return $this->succeeds($command);
    }

    /**
     * Run a command and collect the non-empty lines it prints.
     *
     * @param  list<string>  $command  The command arguments.
     *
     * @return Collection<int, non-falsy-string> The trimmed lines, empty when the command fails.
     */
    private function names(array $command): Collection
    {
        $result = Process::run($command);

        return collect($result->successful() ? explode("\n", trim($result->output())) : [])
            ->map(fn (string $line) => trim($line))
            ->filter();
    }
}
