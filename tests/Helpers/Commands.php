<?php

declare(strict_types=1);

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use Illuminate\Testing\PendingCommand;
use Pest\TestSuite;
use Tests\TestCase;

/**
 * Create a project checkout to run commands in, and move into it.
 *
 * The checkout gets a rift.yaml, a compose file joined to the router network
 * and an .env.example. Its global config lives in the scratch directory too,
 * so no test reads or writes the real ~/.config/rift.
 *
 * @param  array<string, string>  $files  Extra or replacement files, keyed by path relative to the root.
 * @param  string|null  $config  The rift.yaml contents, or null for a minimal Compose project.
 *
 * @return string The checkout root.
 */
function project(array $files = [], ?string $config = null): string
{
    $root = scratch();

    $defaults = [
        'rift.yaml' => $config ?? "name: myapp\ndomain: myapp.test\napp:\n  port: 80\n",
        'compose.yaml' => "services:\n  app:\n    networks: [default, router]\nnetworks:\n  router:\n    external: true\n    name: \"\${ROUTER_NAME}\"\n",
        '.env.example' => "APP_ENV=local\n",
    ];

    foreach ([...$defaults, ...$files] as $path => $contents) {
        if (! is_dir(dirname($root . '/' . $path))) {
            mkdir(dirname($root . '/' . $path), 0o777, true);
        }

        file_put_contents($root . '/' . $path, $contents);
    }

    putenv('XDG_CONFIG_HOME=' . $root . '/.config');
    chdir($root);

    return $root;
}

/**
 * Start a command run in the current test, ready for output and exit code expectations.
 *
 * Narrows `$this->artisan()`, which can also return an exit code when console
 * output is not mocked, so expectations chain without static analysis errors.
 *
 * @param  string  $command  The command name, without `rift`.
 * @param  array<string, mixed>  $parameters  The command arguments and options.
 *
 * @return PendingCommand The pending command.
 */
function artisan(string $command, array $parameters = []): PendingCommand
{
    $test = TestSuite::getInstance()->test;

    assert($test instanceof TestCase);

    $pending = $test->artisan($command, $parameters);

    assert($pending instanceof PendingCommand);

    return $pending;
}

/**
 * Let artisan expectations answer the next command's prompts.
 *
 * Rift runs as `development`, where a prompt without a terminal returns its
 * default, as `rift init -n` does. Laravel only routes prompts through the
 * console, where expectsQuestion() and expectsConfirmation() can reply, in the
 * testing environment.
 *
 * @return void
 */
function answerPrompts(): void
{
    app()->instance('env', 'testing');
}

/**
 * Fake every process a command runs, as a healthy machine would answer.
 *
 * The defaults describe the main checkout of `myapp` at $root with Docker up,
 * the router running and the app container on the router network. Pass
 * overrides, matched first, to change any answer. Patterns match the quoted
 * command line, so `*network*inspect*` matches `'docker' 'network' 'inspect' …`.
 *
 * @param  string  $root  The checkout root git reports.
 * @param  array<string, string|ProcessResult>  $overrides  Results keyed by command pattern.
 *
 * @return void
 */
function host(string $root, array $overrides = []): void
{
    $defaults = [
        '*--show-toplevel*' => $root,
        '*--git-common-dir*' => $root . '/.git',
        '*worktree*list*' => "worktree {$root}\n",
        '*docker*network*inspect*' => "myapp-app-1\n",
        '*docker*network*ls*' => "rift-router\n",
        '*docker*ps*' => "rift-router\n",
        '*' => '',
    ];

    // Overrides go first, because the first matching pattern answers. A default
    // with the same pattern is dropped, or spreading would let it win.
    Process::fake([...$overrides, ...array_diff_key($defaults, $overrides)]);
}
