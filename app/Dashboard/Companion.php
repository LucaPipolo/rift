<?php

declare(strict_types=1);

namespace App\Dashboard;

/**
 * Finds rift-dash, the Go program that draws `rift dash`.
 *
 * We decided to draw the dashboard in a separate Go program, because PHP has no
 * maintained library for full-screen terminal views. Rift ships both binaries
 * side by side, so the dashboard is looked for beside rift before anywhere else.
 */
final readonly class Companion
{
    public const string NAME = 'rift-dash';

    /**
     * Create a new companion instance.
     *
     * @param  Rift  $rift  How this rift runs, which says where it lives.
     * @param  string  $path  The PATH to search when rift-dash is not beside rift.
     */
    public function __construct(
        private Rift $rift,
        private string $path = '',
    ) {}

    /**
     * Find rift-dash.
     *
     * @return string|null Its path, or null when it is nowhere to be found.
     */
    public function find(): ?string
    {
        $directories = [...$this->rift->directories(), ...array_filter(explode(PATH_SEPARATOR, $this->path))];

        foreach ($directories as $directory) {
            $candidate = $directory . '/' . self::NAME;

            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
