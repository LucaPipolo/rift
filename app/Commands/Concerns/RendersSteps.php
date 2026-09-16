<?php

declare(strict_types=1);

namespace App\Commands\Concerns;

use App\Support\Outcome;
use App\Support\Ui;
use Closure;
use Throwable;

use function Laravel\Prompts\spin;

/**
 * Renders a run as a list of steps, sending subprocess output to a log unless -v is passed.
 */
trait RendersSteps
{
    private ?string $logFile = null;

    private bool $failed = false;

    /**
     * Whether the banner was already shown, shared so `setup` handing over to `up` prints one.
     */
    private static bool $headerShown = false;

    /**
     * Determine whether the user passed -v.
     *
     * @return bool Whether output is verbose.
     */
    protected function verbose(): bool
    {
        return $this->output->isVerbose();
    }

    /**
     * Get the path of this run's log file.
     *
     * @return string The log file path.
     */
    protected function logFile(): string
    {
        return $this->logFile ??= sprintf(
            '%s/rift-%s-%d.log',
            sys_get_temp_dir(),
            str_replace(':', '-', $this->getName() ?? 'run'),
            getmypid(),
        );
    }

    /**
     * Get a callback that sends subprocess output to the terminal or the log.
     *
     * @return Closure(string): void
     */
    protected function sink(): Closure
    {
        return function (string $chunk): void {
            if ($this->verbose()) {
                $this->output->write($chunk);

                return;
            }

            file_put_contents($this->logFile(), $chunk, FILE_APPEND);
        };
    }

    /**
     * Render the run's banner once.
     *
     * @param  string  $name  The project or router name.
     *
     * @return void
     */
    protected function header(string $name): void
    {
        if (self::$headerShown) {
            return;
        }

        self::$headerShown = true;

        Ui::banner($name, 'dev environment', $this->verbose() ? null : '-v for details');
        Ui::blank();
    }

    /**
     * Run a step, showing a spinner while it works and its outcome after.
     *
     * @param  string  $label  The step label.
     * @param  callable(): Outcome  $work
     *
     * @return Outcome How the step ended.
     */
    protected function step(string $label, callable $work): Outcome
    {
        $outcome = $this->verbose()
            ? $this->attemptVerbosely($label, $work)
            : spin(fn () => $this->attempt($work), '  ' . $label);

        $this->settle($label, $outcome);

        return $outcome;
    }

    /**
     * Render a labelled fact rather than the outcome of a step.
     *
     * @param  string  $label  The fact's label.
     * @param  string  $value  The fact's value.
     *
     * @return void
     */
    protected function note(string $label, string $value = ''): void
    {
        Ui::fact($label, $value);
    }

    /**
     * Render the closing line of a successful run.
     *
     * @param  string  $message  The closing message.
     *
     * @return void
     */
    protected function success(string $message): void
    {
        Ui::done($message);
        Ui::blank();
    }

    /**
     * Render a bordered notice for something the user has to act on.
     *
     * @param  list<string>  $lines  The notice lines, as markup.
     * @param  string  $title  The notice title.
     *
     * @return void
     */
    protected function notice(array $lines, string $title = 'notice'): void
    {
        Ui::notice($title, $lines);
        Ui::blank();
    }

    /**
     * Determine whether any step has failed.
     *
     * @return bool Whether a step failed.
     *
     * @phpstan-impure
     */
    protected function hasFailed(): bool
    {
        return $this->failed;
    }

    /**
     * Run a step's work with its output streamed to the terminal.
     *
     * @param  string  $label  The step label.
     * @param  callable(): Outcome  $work
     *
     * @return Outcome How the step ended.
     */
    private function attemptVerbosely(string $label, callable $work): Outcome
    {
        Ui::muted("▸ {$label}");

        return $this->attempt($work);
    }

    /**
     * Run a step's work, turning any exception into a failed outcome.
     *
     * @param  callable(): Outcome  $work
     *
     * @return Outcome How the step ended.
     */
    private function attempt(callable $work): Outcome
    {
        try {
            return $work();
        } catch (Throwable $e) {
            $this->sink()($e->getMessage() . "\n");

            return Outcome::failed($e->getMessage());
        }
    }

    /**
     * Render a step's outcome and record whether it failed.
     *
     * @param  string  $label  The step label.
     * @param  Outcome  $outcome  How the step ended.
     *
     * @return void
     */
    private function settle(string $label, Outcome $outcome): void
    {
        Ui::step($outcome->icon, $outcome->colour, $label, $outcome->note);

        if (! $outcome->fatal) {
            return;
        }

        $this->failed = true;

        if (! $this->verbose()) {
            Ui::blank();
            Ui::muted("Log: {$this->logFile()}");
            Ui::blank();
        }
    }
}
