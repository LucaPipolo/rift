<?php

declare(strict_types=1);

use App\Checkout\Checkout;
use App\Checkout\Instance;
use App\Config\ProjectConfig;
use App\Config\RouterConfig;
use App\Contracts\PortProbe;
use App\Router\CaddyAdmin;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

// A process with no matching fake throws instead of running on the real machine.
// Laravel only checks while it records, so record from the start with no fakes.
uses(TestCase::class)
    ->beforeEach(fn () => Process::fake([])->preventStrayProcesses())
    ->in('Feature');

// Only re-run tests affected by local changes. CI always runs the full suite.
// The graph lives in the project because the container discards its home.
pest()->tia()->locally()->directory('.pest/tia');

foreach (glob(__DIR__ . '/Helpers/*.php') ?: [] as $helpers) {
    require_once $helpers;
}

/**
 * Create a scratch directory that is removed when the test run ends.
 *
 * @return string The directory path.
 */
function scratch(): string
{
    $path = sys_get_temp_dir() . '/rift-test-' . bin2hex(random_bytes(6));

    mkdir($path, 0o777, true);

    register_shutdown_function(static function () use ($path): void {
        exec('rm -rf ' . escapeshellarg($path));
    });

    return $path;
}

/**
 * Create a port probe with a fixed set of busy ports.
 *
 * @param  list<int>  $listening  The ports reported as listening.
 * @param  list<int>  $published  The ports reported as published.
 *
 * @return PortProbe The fake probe.
 */
function probe(array $listening = [], array $published = []): PortProbe
{
    return new class($listening, $published) implements PortProbe
    {
        /**
         * Create a new fake port probe instance.
         *
         * @param  list<int>  $listening  The ports reported as listening.
         * @param  list<int>  $published  The ports reported as published.
         */
        public function __construct(private array $listening, private array $published) {}

        /**
         * Determine whether the port is in the listening list.
         *
         * @param  int  $port  The host port.
         *
         * @return bool Whether the port is listed.
         */
        public function isListening(int $port): bool
        {
            return in_array($port, $this->listening, true);
        }

        /**
         * Get a fixed process name for a port in the listening list.
         *
         * @param  int  $port  The host port.
         *
         * @return string|null The process name, or null when the port is not listed.
         */
        public function listener(int $port): ?string
        {
            return $this->isListening($port) ? 'node' : null;
        }

        /**
         * Get the fixed list of published ports.
         *
         * @param  string  $composeProject  The Compose project name, ignored.
         *
         * @return list<int> The published ports.
         */
        public function publishedBy(string $composeProject): array
        {
            return $this->published;
        }
    };
}

/**
 * Create an instance of the `myapp` project without touching the filesystem.
 *
 * @param  array<string, mixed>  $config  Overrides for the rift.yaml data.
 * @param  string|null  $slug  The worktree slug, or null for the main checkout.
 *
 * @return Instance The instance.
 */
function myapp(array $config = [], ?string $slug = null): Instance
{
    return Instance::make(
        new Checkout(
            root: $slug === null ? '/Sites/myapp' : '/Sites/trees/' . $slug,
            commonDir: '/Sites/myapp/.git',
            mainRoot: '/Sites/myapp',
            slug: $slug,
        ),
        ProjectConfig::fromArray([
            'name' => 'myapp',
            'domain' => 'myapp.test',
            'app' => ['port' => 80],
            ...$config,
        ]),
        new RouterConfig(),
    );
}

/**
 * Create a Caddy admin client whose API answers GET requests from a fixed config.
 *
 * @param  array<string, mixed>  $state  Responses keyed by config path.
 *
 * @return CaddyAdmin The admin API client.
 */
function caddy(array $state): CaddyAdmin
{
    Http::fake(function (Request $request) use ($state) {
        $path = '/' . ltrim(parse_url($request->url(), PHP_URL_PATH) ?: '', '/');

        if ($request->method() !== 'GET') {
            return Http::response('', 200);
        }

        // Caddy answers 404 for a route id it does not know.
        if (str_starts_with($path, '/id/') && ! array_key_exists($path, $state)) {
            return Http::response('', 404);
        }

        return Http::response(
            json_encode(array_key_exists($path, $state) ? $state[$path] : null, JSON_THROW_ON_ERROR),
            200,
            ['Content-Type' => 'application/json'],
        );
    });

    return new CaddyAdmin(new RouterConfig());
}

/**
 * Get every request that changed the faked Caddy config.
 *
 * @return list<array{method: string, path: string, body: mixed}> The method, path and decoded body of each request.
 */
function caddyWrites(): array
{
    $sent = [];

    foreach (Http::recorded() as [$request]) {
        if ($request->method() === 'GET') {
            continue;
        }

        $sent[] = [
            'method' => $request->method(),
            'path' => (string) parse_url($request->url(), PHP_URL_PATH),
            'body' => json_decode($request->body(), true),
        ];
    }

    return $sent;
}
