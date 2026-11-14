<?php

declare(strict_types=1);

namespace App\Dashboard;

/**
 * Builds the command that runs this same rift again, as a child process.
 *
 * We decided to run every action as a child process rather than call the
 * commands in-process, because they draw spinners straight to the terminal,
 * resolve the checkout from the working directory and can ask for sudo, none
 * of which works inside a running full-screen view.
 */
final readonly class Rift
{
    /**
     * Create a new rift invocation instance.
     *
     * @param  string  $sapi  The PHP SAPI, "micro" inside the native binary.
     * @param  string  $binary  The PHP binary, or the native binary's own path, as PHP_BINARY is empty inside it.
     * @param  string  $script  The absolute path of the script PHP was started with, the PHAR or `rift` from source.
     */
    public function __construct(
        private string $sapi = PHP_SAPI,
        private string $binary = PHP_BINARY,
        private string $script = 'rift',
    ) {}

    /**
     * Get the command that runs a rift command without prompts or colours.
     *
     * @param  string  ...$arguments  The command and its arguments, such as "up".
     *
     * @return list<string> The command line.
     */
    public function command(string ...$arguments): array
    {
        return [...$this->interactive(...$arguments), '--no-interaction', '--no-ansi'];
    }

    /**
     * Get the command that runs a rift command in the foreground, where it may ask questions.
     *
     * @param  string  ...$arguments  The command and its arguments, such as "trust".
     *
     * @return list<string> The command line.
     */
    public function interactive(string ...$arguments): array
    {
        $self = $this->sapi === 'micro'
            ? [$this->binary]
            : [$this->binary, $this->script];

        return [...$self, ...array_values($arguments)];
    }

    /**
     * Get the directories rift-dash may sit in: beside this binary, or in builds/ from source.
     *
     * @return list<string> The directories, most likely first.
     */
    public function directories(): array
    {
        return $this->sapi === 'micro'
            ? [dirname($this->binary)]
            : [dirname($this->script) . '/builds', dirname($this->script)];
    }
}
