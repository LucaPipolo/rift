<?php

declare(strict_types=1);

namespace App\Checkout;

/**
 * One git checkout of a project: the main clone or a worktree beside it.
 */
final readonly class Checkout
{
    /**
     * Create a new checkout instance.
     *
     * @param  string  $root  The checkout's root directory.
     * @param  string  $commonDir  The .git directory shared by every checkout.
     * @param  string  $mainRoot  The main checkout's root directory.
     * @param  string|null  $slug  The worktree's directory slug, or null for the main checkout.
     * @param  list<string>  $siblings  The roots of the project's other checkouts.
     */
    public function __construct(
        public string $root,
        public string $commonDir,
        public string $mainRoot,
        public ?string $slug,
        public array $siblings = [],
    ) {}

    /**
     * Reduce a directory name to a slug that is safe for Docker and DNS.
     *
     * @param  string  $name  The directory name.
     *
     * @return string The lowercase slug.
     */
    public static function slugify(string $name): string
    {
        $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9._-]/', '-', $name));
        $slug = (string) preg_replace('/-{2,}/', '-', $slug);

        return trim($slug, '-.');
    }

    /**
     * Determine whether this is the project's main checkout.
     *
     * @return bool Whether the checkout is not a worktree.
     */
    public function isPrimary(): bool
    {
        return $this->slug === null;
    }

    /**
     * Get the Compose project name that isolates this checkout's containers.
     *
     * @param  string  $projectName  The project name from rift.yaml.
     *
     * @return string The project name, suffixed with the slug for a worktree.
     */
    public function composeName(string $projectName): string
    {
        return $this->isPrimary()
            ? $projectName
            : "{$projectName}-{$this->slugFor($projectName)}";
    }

    /**
     * Get this worktree's label within its project.
     *
     * A worktree directory that repeats the project name, such as `myapp-feat-x`
     * beside `myapp`, has that prefix dropped.
     *
     * @param  string  $projectName  The project name from rift.yaml.
     *
     * @return string|null The label, or null for the main checkout.
     */
    public function slugFor(string $projectName): ?string
    {
        if ($this->slug === null) {
            return null;
        }

        $prefix = self::slugify($projectName) . '-';

        if (! str_starts_with($this->slug, $prefix)) {
            return $this->slug;
        }

        $trimmed = substr($this->slug, strlen($prefix));

        // A worktree named exactly after the project keeps its full slug, so it
        // never collapses into the main checkout's names.
        return $trimmed === '' ? $this->slug : $trimmed;
    }

    /**
     * Get the host this checkout is served at.
     *
     * @param  string  $baseDomain  The project's base domain.
     * @param  string  $projectName  The project name from rift.yaml.
     *
     * @return string The base domain, prefixed with the slug for a worktree.
     */
    public function domain(string $baseDomain, string $projectName): string
    {
        return $this->isPrimary()
            ? $baseDomain
            : "{$this->slugFor($projectName)}.{$baseDomain}";
    }
}
