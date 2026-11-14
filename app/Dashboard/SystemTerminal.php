<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Contracts\Terminal;

/**
 * The terminal rift runs in.
 */
final readonly class SystemTerminal implements Terminal
{
    /**
     * Create a new system terminal instance.
     *
     * @param  resource  $input  The stream keys arrive on.
     * @param  resource  $output  The stream the screen is drawn on.
     */
    public function __construct(
        private mixed $input = STDIN,
        private mixed $output = STDOUT,
    ) {}

    /**
     * Determine whether both input and output are a terminal.
     *
     * @return bool Whether a full-screen view can run.
     */
    public function interactive(): bool
    {
        return stream_isatty($this->input) && stream_isatty($this->output);
    }
}
