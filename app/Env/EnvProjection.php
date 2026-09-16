<?php

declare(strict_types=1);

namespace App\Env;

/**
 * The result of writing a checkout's derived values into its .env.
 */
final readonly class EnvProjection
{
    /**
     * Create a new env projection instance.
     *
     * @param  bool  $created  Whether the .env had to be seeded first.
     * @param  array<string, string>  $values  Every key written.
     * @param  array<string, int>  $ports  The host ports resolved, by key.
     */
    public function __construct(
        public bool $created,
        public array $values,
        public array $ports,
    ) {}
}
