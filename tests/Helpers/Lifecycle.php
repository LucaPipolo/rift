<?php

declare(strict_types=1);

use App\Config\RouterConfig;
use App\Docker\Docker;
use App\Router\CertificateAuthority;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\PendingCommand;

/**
 * Get the .env contents `rift setup` writes for the main checkout of `myapp`.
 *
 * @param  string  $root  The checkout root.
 *
 * @return string The .env contents.
 */
function lifecycleEnv(string $root): string
{
    return implode("\n", [
        'COMPOSE_PROJECT_NAME=myapp',
        "GIT_COMMON_DIR={$root}/.git",
        'BASE_NAME=myapp',
        'BASE_DOMAIN=myapp.test',
        'ROUTER_NAME=rift-router',
        'APP_SERVICE=app',
        'CONTAINER_PORT=80',
    ]) . "\n";
}

/**
 * Create a checkout of `myapp` that `rift setup` already wrote an .env for.
 *
 * @param  array<string, string>  $files  Extra or replacement files, keyed by path relative to the root.
 * @param  string|null  $config  The rift.yaml contents, or null for a minimal Compose project.
 *
 * @return string The checkout root.
 */
function lifecycleProject(array $files = [], ?string $config = null): string
{
    $root = project($files, $config);

    if (! array_key_exists('.env', $files)) {
        file_put_contents($root . '/.env', lifecycleEnv($root));
    }

    return $root;
}

/**
 * Create a set-up worktree of `myapp` beside its main checkout, fake git for it, and move into it.
 *
 * The healthy-machine fakes from host() apply, with the worktree's own app
 * container on the router network.
 *
 * @param  string  $directory  The worktree directory name.
 * @param  string  $config  The rift.yaml contents.
 * @param  array<string, string|ProcessResult>  $overrides  Process results keyed by command pattern, matched first.
 *
 * @return string The worktree root.
 */
function lifecycleWorktree(string $directory, string $config, array $overrides = []): string
{
    $main = project();
    $root = $main . '/' . $directory;

    mkdir($root);
    file_put_contents($root . '/rift.yaml', $config);
    file_put_contents($root . '/.env', lifecycleEnv($main));
    chdir($root);

    // Patterns differ from host()'s own, which would otherwise overwrite them.
    host($root, [
        ...$overrides,
        '*--git-common-dir*' => $main . '/.git',
        '*docker*network*inspect*' => "myapp-{$directory}-app-1\n",
    ]);

    return $root;
}

/**
 * Fake the router's admin API, answering "null" to anything not listed.
 *
 * @param  array<string, PromiseInterface>  $responses  Responses keyed by URL pattern, matched first.
 *
 * @return void
 */
function lifecycleCaddy(array $responses = []): void
{
    Http::fake([...$responses, '*' => Http::response('null', 200)]);
}

/**
 * Get the log file a command writes subprocess output to in this process, starting it empty.
 *
 * @param  string  $command  The command name.
 *
 * @return string The log file path.
 */
function lifecycleLog(string $command): string
{
    $path = sprintf('%s/rift-%s-%d.log', sys_get_temp_dir(), $command, getmypid());

    if (is_file($path)) {
        unlink($path);
    }

    return $path;
}

/**
 * Make commands see a host OS and system trust store of the test's choosing.
 *
 * @param  string  $os  The operating system family, as PHP_OS_FAMILY reports it.
 *
 * @return string The Linux trust directory, inside a scratch directory.
 */
function lifecycleHostOs(string $os): string
{
    $directory = scratch();

    app()->bind(
        CertificateAuthority::class,
        fn (mixed $app, array $parameters): CertificateAuthority => lifecycleCertificateAuthority($parameters, $os, $directory),
    );

    return $directory;
}

/**
 * Build a certificate authority from the arguments a command resolves it with.
 *
 * @param  array<array-key, mixed>  $parameters  The `router` and `docker` arguments.
 * @param  string  $os  The operating system family.
 * @param  string  $directory  The Linux trust directory.
 *
 * @return CertificateAuthority The certificate authority.
 */
function lifecycleCertificateAuthority(array $parameters, string $os, string $directory): CertificateAuthority
{
    $router = $parameters['router'] ?? null;
    $docker = $parameters['docker'] ?? null;

    if (! $router instanceof RouterConfig || ! $docker instanceof Docker) {
        throw new InvalidArgumentException('A certificate authority needs a router config and a Docker client.');
    }

    return new CertificateAuthority($router, $docker, $os, $directory);
}

/**
 * Write rift's local copy of the router's CA certificate for the checkout at $root.
 *
 * @param  string  $root  The checkout root project() created.
 * @param  string  $contents  The certificate contents.
 *
 * @return string The certificate path.
 */
function lifecycleCaCopy(string $root, string $contents = 'CERTIFICATE'): string
{
    $path = $root . '/.config/rift/rift-router-ca.crt';

    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0o777, true);
    }

    file_put_contents($path, $contents);

    return $path;
}

/**
 * Match a faked process whose command line contains a fragment.
 *
 * @param  string  $fragment  The text to look for, arguments joined by spaces.
 *
 * @return Closure(PendingProcess): bool The matcher.
 */
function lifecycleRan(string $fragment): Closure
{
    return function (PendingProcess $process) use ($fragment): bool {
        $command = is_array($process->command) ? implode(' ', $process->command) : (string) $process->command;

        return str_contains($command, $fragment);
    };
}

/**
 * Create a checkout of `myapp` without a rift.yaml, fake git for it, and move into it.
 *
 * @param  array<string, string>  $files  Extra or replacement files, keyed by path relative to the root.
 *
 * @return string The checkout root.
 */
function lifecycleInitProject(array $files = []): string
{
    $root = project($files);
    unlink($root . '/rift.yaml');
    host($root);

    return $root;
}

/**
 * Run `rift init`, accepting every default as a non-interactive run does.
 *
 * @return PendingCommand The pending command.
 */
function lifecycleInitWithDefaults(): PendingCommand
{
    return artisan('init', ['--no-interaction' => true]);
}

/**
 * Run `rift init` and answer its prompts.
 *
 * @param  string  $preset  The preset to pick.
 * @param  string  $port  The container port to type.
 * @param  bool|null  $write  Whether to confirm writing rift.yaml, or null when the run never gets that far.
 *
 * @return PendingCommand The pending command.
 */
function lifecycleInitAnswering(string $preset = 'compose', string $port = '8080', ?bool $write = true): PendingCommand
{
    answerPrompts();

    $command = artisan('init')
        ->expectsChoice(
            'How are this project\'s containers driven?',
            $preset,
            ['compose' => 'docker compose', 'sail' => 'Laravel Sail'],
        )
        ->expectsQuestion('Project name', 'shop')
        ->expectsQuestion('Base domain', 'shop.test')
        ->expectsQuestion('Service the router should proxy to', 'web')
        ->expectsQuestion('Port that service listens on inside its container', $port);

    return $write === null ? $command : $command->expectsConfirmation('Write rift.yaml?', $write ? 'yes' : 'no');
}
