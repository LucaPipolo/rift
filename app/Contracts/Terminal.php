<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * The terminal a command runs in.
 */
interface Terminal
{
    /**
     * Determine whether both input and output are a terminal a person is at.
     *
     * @return bool Whether a full-screen view can run.
     */
    public function interactive(): bool;
}
