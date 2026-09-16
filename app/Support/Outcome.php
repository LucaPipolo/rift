<?php

declare(strict_types=1);

namespace App\Support;

/**
 * How a step ended, and the note printed beside it.
 */
final readonly class Outcome
{
    /**
     * Create a new outcome instance.
     *
     * @param  string  $icon  The status icon.
     * @param  string  $colour  The icon colour.
     * @param  string  $note  The note beside the step.
     * @param  bool  $fatal  Whether the run should stop.
     */
    private function __construct(
        public string $icon,
        public string $colour,
        public string $note,
        public bool $fatal = false,
    ) {}

    /**
     * Create an outcome for a step that did its work.
     *
     * @param  string  $note  The note beside the step.
     *
     * @return self The outcome.
     */
    public static function done(string $note = ''): self
    {
        return new self('✓', 'green', $note);
    }

    /**
     * Create an outcome for a step that had nothing to do.
     *
     * @param  string  $note  The note beside the step.
     *
     * @return self The outcome.
     */
    public static function skipped(string $note = ''): self
    {
        return new self('–', 'gray', $note);
    }

    /**
     * Create an outcome for a step that failed.
     *
     * @param  string  $note  The note beside the step.
     *
     * @return self The outcome.
     */
    public static function failed(string $note = ''): self
    {
        return new self('✗', 'red', $note, fatal: true);
    }

    /**
     * Create a done or skipped outcome depending on whether anything changed.
     *
     * @param  bool  $changed  Whether the step changed anything.
     * @param  string  $done  The note when it did.
     * @param  string  $skipped  The note when it did not.
     *
     * @return self The outcome.
     */
    public static function changed(bool $changed, string $done = '', string $skipped = ''): self
    {
        return $changed ? self::done($done) : self::skipped($skipped);
    }

    /**
     * Create a done or failed outcome depending on whether the work succeeded.
     *
     * @param  bool  $succeeded  Whether the work succeeded.
     * @param  string  $failed  The note when it failed.
     *
     * @return self The outcome.
     */
    public static function succeeded(bool $succeeded, string $failed = ''): self
    {
        return $succeeded ? self::done() : self::failed($failed);
    }
}
