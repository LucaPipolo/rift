<?php

declare(strict_types=1);

namespace App\Config;

use App\Exceptions\ConfigException;

/**
 * A command a project runs at a lifecycle stage.
 */
final readonly class Hook
{
    /**
     * Create a new hook instance.
     *
     * @param  string  $run  The command to run.
     * @param  bool  $onHost  Whether it runs on the host rather than in the app container.
     * @param  string|null  $unlessExists  A path whose existence skips the hook.
     * @param  string|null  $label  The step label.
     */
    public function __construct(
        public string $run,
        public bool $onHost = false,
        public ?string $unlessExists = null,
        public ?string $label = null,
    ) {}

    /**
     * Create a hook from its rift.yaml entry.
     *
     * @param  array<string, mixed>  $data  The entry from rift.yaml.
     * @param  string  $context  Where the entry sits, for error messages.
     *
     * @return self The hook.
     *
     * @throws ConfigException
     */
    public static function fromArray(array $data, string $context): self
    {
        if (! isset($data['run']) || ! is_string($data['run']) || trim($data['run']) === '') {
            throw new ConfigException("{$context}: every hook needs a non-empty `run`.");
        }

        $on = $data['on'] ?? 'app';

        if (! in_array($on, ['app', 'host'], true)) {
            throw new ConfigException("{$context}: `on` must be either `app` or `host`.");
        }

        $unlessExists = $data['unless_exists'] ?? null;
        $label = $data['label'] ?? null;

        if ($unlessExists !== null && ! is_string($unlessExists)) {
            throw new ConfigException("{$context}: `unless_exists` must be a string.");
        }

        if ($label !== null && ! is_string($label)) {
            throw new ConfigException("{$context}: `label` must be a string.");
        }

        return new self(
            run: trim($data['run']),
            onHost: $on === 'host',
            unlessExists: $unlessExists,
            label: $label,
        );
    }

    /**
     * Determine whether the hook still has work to do in the checkout.
     *
     * @param  string  $root  The checkout's root directory.
     *
     * @return bool Whether the hook should run.
     */
    public function applies(string $root): bool
    {
        if ($this->unlessExists === null) {
            return true;
        }

        return ! file_exists($root . '/' . ltrim($this->unlessExists, '/'));
    }
}
