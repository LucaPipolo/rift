<?php

declare(strict_types=1);

namespace App\Checkout;

use App\Exceptions\ConfigException;
use Illuminate\Support\Facades\Process;

/**
 * Finds the git checkout a directory belongs to.
 */
final class CheckoutLocator
{
    /**
     * Locate the checkout containing a directory.
     *
     * @param  string|null  $from  Defaults to the working directory.
     *
     * @return Checkout The checkout and its siblings.
     *
     * @throws ConfigException
     */
    public function locate(?string $from = null): Checkout
    {
        $from ??= getcwd() ?: '.';

        $root = $this->git($from, ['rev-parse', '--show-toplevel']);

        if ($root === null) {
            throw new ConfigException('Not inside a git checkout.');
        }

        $commonDir = $this->git($from, [
            'rev-parse', '--path-format=absolute', '--git-common-dir',
        ]);

        if ($commonDir === null) {
            throw new ConfigException('Could not read this checkout\'s git directory.');
        }

        // Every worktree shares the main checkout's .git, so its parent is the main checkout.
        $mainRoot = dirname($commonDir);

        return new Checkout(
            root: $root,
            commonDir: $commonDir,
            mainRoot: $mainRoot,
            slug: $root === $mainRoot ? null : Checkout::slugify(basename($root)),
            siblings: $this->siblings($from, $root),
        );
    }

    /**
     * Get the roots of every other checkout of the same repository.
     *
     * @param  string  $directory  A directory inside the checkout.
     * @param  string  $root  The checkout's own root, which is excluded.
     *
     * @return list<string> The sibling root directories.
     */
    private function siblings(string $directory, string $root): array
    {
        $listing = $this->git($directory, ['worktree', 'list', '--porcelain']);

        if ($listing === null) {
            return [];
        }

        $roots = [];

        foreach (explode("\n", $listing) as $line) {
            if (str_starts_with($line, 'worktree ')) {
                $roots[] = substr($line, strlen('worktree '));
            }
        }

        return array_values(array_filter($roots, fn (string $other) => $other !== $root));
    }

    /**
     * Run a git command and return its trimmed output.
     *
     * @param  string  $directory  The directory to run git in.
     * @param  list<string>  $arguments  The git arguments.
     *
     * @return string|null Null when git fails or prints nothing.
     */
    private function git(string $directory, array $arguments): ?string
    {
        $result = Process::path($directory)->run(['git', ...$arguments]);

        if (! $result->successful()) {
            return null;
        }

        $output = trim($result->output());

        return $output === '' ? null : $output;
    }
}
