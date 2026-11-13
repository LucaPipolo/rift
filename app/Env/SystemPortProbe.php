<?php

declare(strict_types=1);

namespace App\Env;

use App\Contracts\PortProbe;
use Illuminate\Support\Facades\Process;

/**
 * Inspects host ports through lsof and Docker.
 */
final class SystemPortProbe implements PortProbe
{
    /** @var array<string, list<int>> */
    private array $published = [];

    /**
     * Determine whether anything on this host is listening on the port.
     *
     * @param  int  $port  The host port.
     *
     * @return bool Whether the port is in use.
     */
    public function isListening(int $port): bool
    {
        return Process::run(['lsof', '-nP', '-iTCP:' . $port, '-sTCP:LISTEN'])->successful();
    }

    /**
     * Get the name of the process listening on the port, if any.
     *
     * lsof only sees what it can: without root it misses other users' processes,
     * such as a web server running as root, and when it is not installed it finds
     * nothing, leaving Docker's own "port is already allocated" error.
     *
     * @param  int  $port  The host port.
     *
     * @return string|null The process name, or null when nothing listens or the listener is hidden.
     */
    public function listener(int $port): ?string
    {
        $result = Process::run(['lsof', '-nP', '-iTCP:' . $port, '-sTCP:LISTEN', '-Fc']);

        // With -Fc each process is a "p<pid>" line followed by a "c<command>" line.
        if (! $result->successful() || ! preg_match('/^c(.+)$/m', $result->output(), $match)) {
            return null;
        }

        return $match[1];
    }

    /**
     * Get the host ports published by one Compose project's containers, once per run.
     *
     * @param  string  $composeProject  The Compose project name.
     *
     * @return list<int> The published host ports.
     */
    public function publishedBy(string $composeProject): array
    {
        if (isset($this->published[$composeProject])) {
            return $this->published[$composeProject];
        }

        $result = Process::run([
            'docker', 'ps',
            '--filter', 'label=com.docker.compose.project=' . $composeProject,
            '--format', '{{.Ports}}',
        ]);

        $ports = [];

        if ($result->successful()) {
            // In "0.0.0.0:5173->5173/tcp" the host port is the number before the arrow.
            preg_match_all('/:(\d+)->/', $result->output(), $matches);

            $ports = array_values(array_unique(array_map('intval', $matches[1])));
        }

        return $this->published[$composeProject] = $ports;
    }
}
