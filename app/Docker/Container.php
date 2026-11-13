<?php

declare(strict_types=1);

namespace App\Docker;

use JsonException;

/**
 * One container of a Compose project, as `docker ps` describes it.
 */
final readonly class Container
{
    /**
     * Create a new container instance.
     *
     * @param  string  $name  The container name.
     * @param  string  $project  The Compose project it belongs to.
     * @param  string  $service  The Compose service it runs.
     * @param  string  $state  Docker's state, such as running or exited.
     * @param  string  $status  Docker's status line, such as "Up 2 hours (healthy)".
     * @param  string  $image  The image it runs.
     */
    public function __construct(
        public string $name,
        public string $project,
        public string $service,
        public string $state,
        public string $status,
        public string $image,
    ) {}

    /**
     * Create a container from one line of `docker ps --format '{{json .}}'`.
     *
     * @param  string  $line  The JSON line.
     *
     * @return self|null Null for a line that is not a Compose container.
     */
    public static function fromJson(string $line): ?self
    {
        try {
            $data = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($data)) {
            return null;
        }

        $field = fn (string $key): string => is_string($data[$key] ?? null) ? $data[$key] : '';
        $project = self::label($field('Labels'), 'com.docker.compose.project');
        $service = self::label($field('Labels'), 'com.docker.compose.service');

        if ($project === null || $service === null) {
            return null;
        }

        return new self(
            name: $field('Names'),
            project: $project,
            service: $service,
            state: $field('State'),
            status: $field('Status'),
            image: $field('Image'),
        );
    }

    /**
     * Determine whether the container is running.
     *
     * @return bool Whether it is running.
     */
    public function isRunning(): bool
    {
        return $this->state === 'running';
    }

    /**
     * Get the code the container exited with.
     *
     * @return int|null The exit code, or null when it has not exited.
     */
    public function exitCode(): ?int
    {
        return preg_match('/^Exited \((\d+)\)/', $this->status, $matches) === 1 ? (int) $matches[1] : null;
    }

    /**
     * Get the result of the container's health check.
     *
     * @return string|null healthy, unhealthy or starting, or null without a health check.
     */
    public function health(): ?string
    {
        if (preg_match('/\((healthy|unhealthy|health: starting)\)/', $this->status, $matches) !== 1) {
            return null;
        }

        return $matches[1] === 'health: starting' ? 'starting' : $matches[1];
    }

    /**
     * Read one label from Docker's comma-separated label list.
     *
     * A value can itself hold commas, such as a list of Compose files, so the
     * key is matched where a label starts rather than by splitting on commas.
     *
     * @param  string  $labels  The label list.
     * @param  string  $key  The label key.
     *
     * @return string|null The value, or null when the label is missing.
     */
    private static function label(string $labels, string $key): ?string
    {
        $pattern = '/(?:^|,)' . preg_quote($key, '/') . '=([^,]*)/';

        return preg_match($pattern, $labels, $matches) === 1 && $matches[1] !== '' ? $matches[1] : null;
    }
}
