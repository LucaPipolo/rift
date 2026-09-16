<?php

declare(strict_types=1);

use App\Config\RouterConfig;
use App\Docker\Docker;
use App\Router\CaddyAdmin;
use App\Router\CertificateAuthority;
use App\Router\Router;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Build a route as the Caddy admin API returns it for a registered checkout.
 *
 * @param  mixed  $id  The route id, or anything else to test malformed routes.
 * @param  list<mixed>  $hosts  The hostnames the route matches, own domain first.
 * @param  string|null  $dial  The upstream container:port, or null for a route without one.
 *
 * @return array<string, mixed> The route.
 */
function statusRoute(mixed $id, array $hosts, ?string $dial = null): array
{
    $route = ['@id' => $id, 'match' => [['host' => $hosts]]];

    if ($dial !== null) {
        $route['handle'] = [['routes' => [['handle' => [['upstreams' => [['dial' => $dial]]]]]]]];
    }

    return $route;
}

/**
 * Fake the router's admin API serving routes for a set of certified project domains.
 *
 * @param  array<array-key, mixed>  $routes  The registered routes.
 * @param  list<string>  $domains  The project base domains the router certifies.
 *
 * @return void
 */
function routerServing(array $routes, array $domains = ['myapp.test']): void
{
    caddy([
        '/config/apps/http/servers/main' => ['routes' => $routes],
        '/config/apps/http/servers/main/routes' => $routes,
        '/config/apps/tls/automation/policies' => [[
            'subjects' => [...$domains, ...array_map(fn (string $domain) => '*.' . $domain, $domains)],
        ]],
    ]);
}

/**
 * Bind the shared router with a Linux certificate authority trusting from a scratch directory.
 *
 * @return string The trust directory the CA is installed into.
 */
function routerWithScratchTrustStore(): string
{
    $directory = scratch();
    $config = new RouterConfig();
    $docker = new Docker();

    app()->instance(Router::class, new Router(
        $config,
        new CaddyAdmin($config),
        $docker,
        new CertificateAuthority($config, $docker, 'Linux', $directory),
    ));

    return $directory;
}

/**
 * Get the process answer that lets openssl read the CA's fingerprint, for host() overrides.
 *
 * @return array<string, string> The answer keyed by command pattern.
 */
function opensslReadsCaFingerprint(): array
{
    return ['*openssl*' => "sha256 Fingerprint=AB:CD:EF\n"];
}

/**
 * Bind the shared router with a CA whose local copy is already in the system trust store.
 *
 * Call after project() or another XDG_CONFIG_HOME change, since the local copy lives beside
 * the config, and fake openssl with opensslReadsCaFingerprint().
 *
 * @return void
 */
function routerWithTrustedCa(): void
{
    $directory = routerWithScratchTrustStore();
    $local = dirname(RouterConfig::path());

    if (! is_dir($local)) {
        mkdir($local, 0o777, true);
    }

    file_put_contents($local . '/rift-router-ca.crt', "certificate\n");
    file_put_contents($directory . '/rift-router-ca.crt', "certificate\n");
}

/**
 * Run a command and capture everything it printed as one string.
 *
 * @param  string  $command  The command name.
 * @param  array<string, mixed>  $parameters  The command arguments and options.
 *
 * @return string The full output.
 */
function bufferedOutputOf(string $command, array $parameters = []): string
{
    $output = new BufferedOutput();

    Artisan::call($command, $parameters, $output);

    return $output->fetch();
}

/**
 * Set up a checkout that passes every doctor check, apart from what the overrides change.
 *
 * The admin API is left unfaked, so call routerServing() or caddy() after this.
 *
 * @param  array<string, string|ProcessResult>  $overrides  Process results keyed by command pattern, matched first.
 *
 * @return string The checkout root.
 */
function doctorPassingMachine(array $overrides = []): string
{
    $root = project(['.env' => implode("\n", [
        'COMPOSE_PROJECT_NAME=myapp',
        'GIT_COMMON_DIR=/Sites/myapp/.git',
        'BASE_NAME=myapp',
        'BASE_DOMAIN=myapp.test',
        'ROUTER_NAME=rift-router',
        'APP_SERVICE=app',
        'CONTAINER_PORT=80',
    ]) . "\n"]);

    routerWithTrustedCa();

    $defaults = [
        '*ancestor*' => "rift-router\n",
        '*docker*ps*' => "rift-router\nmyapp-app-1\n",
        ...opensslReadsCaFingerprint(),
    ];

    host($root, [...$overrides, ...array_diff_key($defaults, $overrides)]);

    return $root;
}
