<?php

declare(strict_types=1);

namespace App\Dashboard;

/**
 * The stream `rift-dash` writes requests to while `dash:watch` runs, such as "refresh".
 */
final readonly class Input
{
    /**
     * Create a new input instance.
     *
     * @param  resource  $stream  The stream, usually standard input.
     */
    public function __construct(private mixed $stream = STDIN) {}

    /**
     * Wait for the next line, up to a time limit.
     *
     * @param  int  $milliseconds  How long to wait.
     *
     * @return string|null The line without its newline, an empty string when none came in time, or null once the stream has closed.
     */
    public function wait(int $milliseconds): ?string
    {
        $read = [$this->stream];
        $write = null;
        $except = null;

        // A signal interrupting the wait reads as false, and counts as nothing having come yet.
        if (! stream_select($read, $write, $except, intdiv($milliseconds, 1000), ($milliseconds % 1000) * 1000)) {
            return '';
        }

        $line = fgets($this->stream);

        return $line === false ? null : rtrim($line, "\r\n");
    }
}
