<?php

declare(strict_types=1);

namespace App\Checkout;

use App\Config\ProjectConfig;

/**
 * Where a new worktree for a branch would go, how it would be served, or why it cannot be made.
 */
final readonly class WorktreePlan
{
    /**
     * Create a new worktree plan instance.
     *
     * @param  string  $branch  The branch, trimmed.
     * @param  string  $path  The worktree directory, beside the main checkout.
     * @param  string  $domain  The domain it would be served at.
     * @param  string  $composeName  The Compose project name it would get.
     * @param  bool  $exists  Whether the branch exists already, so the worktree checks it out rather than creating it.
     * @param  string|null  $error  Why it cannot be made, or null when it can.
     */
    public function __construct(
        public string $branch,
        public string $path,
        public string $domain,
        public string $composeName,
        public bool $exists = false,
        public ?string $error = null,
    ) {}

    /**
     * Plan a worktree of a project for a branch, named after the branch beside the main checkout.
     *
     * @param  string  $mainRoot  The main checkout's root.
     * @param  ProjectConfig  $config  The project's rift.yaml.
     * @param  string  $branch  The branch to check out, new or existing.
     * @param  list<string>  $branches  The repository's local branches.
     * @param  array<string, string>  $checkedOut  The checkout each branch is checked out in, keyed by branch.
     *
     * @return self The plan.
     */
    public static function for(string $mainRoot, ProjectConfig $config, string $branch, array $branches = [], array $checkedOut = []): self
    {
        $branch = trim($branch);
        $path = dirname($mainRoot) . '/' . basename($mainRoot) . '-' . Checkout::slugify(str_replace('/', '-', $branch));
        $checkout = new Checkout($path, $mainRoot . '/.git', $mainRoot, Checkout::slugify(basename($path)));
        $slug = $checkout->slugFor($config->name);

        $error = match (true) {
            $branch === '' => 'Type a branch name.',
            // The parts of git's check-ref-format rules a typed branch name can break.
            preg_match('#^(?!-|/|HEAD$)(?!.*(\.\.|//|@\{|\.lock(/|$)|/$|\.$|(^|/)\.))[A-Za-z0-9._/-]+$#', $branch) !== 1 => 'That is not a valid branch name.',
            isset($checkedOut[$branch]) => "{$branch} is already checked out at {$checkedOut[$branch]}.",
            in_array($slug, $config->hosts, true) => "A worktree named {$slug} would answer at a host alias of {$config->name}.",
            file_exists($path) => "{$path} already exists.",
            default => null,
        };

        return new self(
            $branch,
            $path,
            $checkout->domain($config->baseDomain, $config->name),
            $checkout->composeName($config->name),
            in_array($branch, $branches, true),
            $error,
        );
    }
}
