<?php

declare(strict_types=1);

namespace App\Router;

use App\Config\RouterConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use stdClass;

/**
 * A client for the router's Caddy admin API.
 *
 * Other projects may already use the router, so after the base server is loaded
 * each project only appends its own route and TLS subjects.
 */
final readonly class CaddyAdmin
{
    private const string SERVER = '/config/apps/http/servers/main';

    private const string POLICIES = '/config/apps/tls/automation/policies';

    private const string AUTOMATE = '/config/apps/tls/certificates/automate';

    /**
     * Create a new Caddy admin client instance.
     *
     * @param  RouterConfig  $router  The router whose admin API to call.
     */
    public function __construct(private RouterConfig $router) {}

    /**
     * Determine whether the admin API is answering.
     *
     * @return bool Whether it answers.
     */
    public function reachable(): bool
    {
        try {
            return $this->request()->get('/config/')->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    /**
     * Wait for the admin API to answer after a cold start.
     *
     * @param  int  $seconds  How long to wait.
     *
     * @return bool Whether it answered in time.
     */
    public function waitUntilReachable(int $seconds = 30): bool
    {
        for ($attempt = 0; $attempt < $seconds; $attempt++) {
            if ($this->reachable()) {
                return true;
            }

            Sleep::sleep(1);
        }

        return false;
    }

    /**
     * Load the base server unless one is already loaded.
     *
     * POST /load replaces the whole config, so it is never sent to a router that
     * already serves other projects.
     *
     * @return bool Whether the server was loaded.
     */
    public function ensureServer(): bool
    {
        if ($this->get(self::SERVER) !== null) {
            return false;
        }

        $this->request()->post('/load', [
            'apps' => [
                'http' => [
                    'servers' => [
                        'main' => [
                            'listen' => [':80', ':443'],
                            'automatic_https' => ['disable' => true],
                            'tls_connection_policies' => [new stdClass()],
                            'routes' => [],
                        ],
                    ],
                ],
                'tls' => [
                    'automation' => ['policies' => []],
                    'certificates' => ['automate' => []],
                ],
            ],
        ])->throw();

        return true;
    }

    /**
     * Add a project's domain and its wildcard to the router's internal CA.
     *
     * @param  string  $domain  The project's base domain.
     *
     * @return bool Whether the TLS config changed.
     */
    public function ensureCertificateFor(string $domain): bool
    {
        return $this->ensureSubjects([$domain, '*.' . $domain]);
    }

    /**
     * Add a wildcard covering one checkout's own subdomains.
     *
     * Only the wildcard is added: a bare feat-x.myapp.test subject would be read
     * by certifiedDomains() as a project of its own.
     *
     * @param  string  $domain  The checkout's domain.
     *
     * @return bool Whether the TLS config changed.
     */
    public function ensureWildcardFor(string $domain): bool
    {
        return $this->ensureSubjects(['*.' . $domain]);
    }

    /**
     * Determine whether a route is registered under an id.
     *
     * @param  string  $id  The route id.
     *
     * @return bool Whether the route exists.
     */
    public function hasRoute(string $id): bool
    {
        try {
            return $this->request()->get('/id/' . $id)->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    /**
     * Register one route proxying every host of a checkout to its upstream.
     *
     * @param  string  $id  The route id.
     * @param  list<string>  $hosts  The hostnames to match.
     * @param  string  $upstream  The container:port to proxy to.
     *
     * @return void
     */
    public function registerRoute(string $id, array $hosts, string $upstream): void
    {
        $this->request()->post(self::SERVER . '/routes', [
            '@id' => $id,
            'match' => [['host' => $hosts]],
            'handle' => [[
                'handler' => 'subroute',
                'routes' => [[
                    'handle' => [[
                        'handler' => 'reverse_proxy',
                        'upstreams' => [['dial' => $upstream]],
                    ]],
                ]],
            ]],
            'terminal' => true,
        ])->throw();
    }

    /**
     * Remove the route registered under an id.
     *
     * @param  string  $id  The route id.
     *
     * @return void
     */
    public function deregisterRoute(string $id): void
    {
        $this->request()->delete('/id/' . $id)->throw();
    }

    /**
     * Get the hostnames a route matches.
     *
     * @param  string  $id  The route id.
     *
     * @return list<string>|null Null when there is no such route or the router is unreachable.
     */
    public function routeHosts(string $id): ?array
    {
        $hosts = $this->get('/id/' . $id . '/match/0/host');

        return is_array($hosts) ? self::strings($hosts) : null;
    }

    /**
     * Replace a route's hostnames, leaving its upstream unchanged.
     *
     * @param  string  $id  The route id.
     * @param  list<string>  $hosts  The hostnames to match.
     *
     * @return void
     */
    public function updateRouteHosts(string $id, array $hosts): void
    {
        $this->request()->patch('/id/' . $id . '/match/0/host', $hosts)->throw();
    }

    /**
     * Get every registered route.
     *
     * @return list<array<array-key, mixed>>|null Null when the router is unreachable, so it is never mistaken for an idle one.
     */
    public function routes(): ?array
    {
        $routes = $this->get(self::SERVER . '/routes');

        return is_array($routes) ? array_values(array_filter($routes, is_array(...))) : null;
    }

    /**
     * Get the ids of the registered routes, in order.
     *
     * @return list<string>|null Null when the router is unreachable.
     */
    public function routeIds(): ?array
    {
        $routes = $this->routes();

        if ($routes === null) {
            return null;
        }

        $ids = [];

        foreach ($routes as $route) {
            if (isset($route['@id']) && is_string($route['@id']) && $route['@id'] !== '') {
                $ids[] = $route['@id'];
            }
        }

        return $ids;
    }

    /**
     * Get the base domain of every project the router holds certificates for, longest first.
     *
     * @return list<string> The base domains.
     */
    public function certifiedDomains(): array
    {
        $policies = $this->get(self::POLICIES);

        if (! is_array($policies)) {
            return [];
        }

        $domains = [];

        foreach ($policies as $policy) {
            $subjects = is_array($policy) && is_array($policy['subjects'] ?? null) ? $policy['subjects'] : [];

            foreach (self::strings($subjects) as $subject) {
                if (! str_starts_with($subject, '*.')) {
                    $domains[] = $subject;
                }
            }
        }

        // Longest first, so a deeper base domain wins over one it sits under.
        usort($domains, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        return array_values(array_unique($domains));
    }

    /**
     * Keep only the strings in a decoded JSON array.
     *
     * @param  array<array-key, mixed>  $values  The decoded values.
     *
     * @return list<string> The strings, in order.
     */
    private static function strings(array $values): array
    {
        return array_values(array_filter($values, is_string(...)));
    }

    /**
     * Append any missing subjects to the TLS policy and automation list.
     *
     * @param  list<string>  $subjects  The hostnames to certify.
     *
     * @return bool Whether the TLS config changed.
     */
    private function ensureSubjects(array $subjects): bool
    {
        $policies = $this->get(self::POLICIES);

        if (! is_array($policies) || $policies === []) {
            $this->request()->post(self::POLICIES, [
                'subjects' => $subjects,
                'issuers' => [['module' => 'internal']],
            ])->throw();

            $changed = true;
        } else {
            $first = $policies[0] ?? null;
            $existing = is_array($first) && is_array($first['subjects'] ?? null) ? $first['subjects'] : [];
            $changed = false;

            foreach ($subjects as $subject) {
                if (! in_array($subject, $existing, true)) {
                    $this->append(self::POLICIES . '/0/subjects', $subject);
                    $changed = true;
                }
            }
        }

        $automate = $this->get(self::AUTOMATE);

        if (! is_array($automate)) {
            $this->request()->patch('/config/apps/tls/certificates', ['automate' => $subjects])->throw();

            return true;
        }

        foreach ($subjects as $subject) {
            if (! in_array($subject, $automate, true)) {
                $this->append(self::AUTOMATE, $subject);
                $changed = true;
            }
        }

        return $changed;
    }

    /**
     * Append one string to an array in the config.
     *
     * @param  string  $path  The config path of the array.
     * @param  string  $value  The value to append.
     *
     * @return void
     */
    private function append(string $path, string $value): void
    {
        $this->request()
            ->withBody(json_encode($value, JSON_THROW_ON_ERROR))
            ->post($path)
            ->throw();
    }

    /**
     * Read a config path, returning null when it is unset or unreachable.
     *
     * @param  string  $path  The config path.
     *
     * @return mixed The decoded JSON, or null.
     */
    private function get(string $path): mixed
    {
        try {
            $response = $this->request()->get($path);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        // Caddy answers "null" for a path that is valid but unset.
        return $response->json();
    }

    /**
     * Create a JSON request to the admin API.
     *
     * @return PendingRequest The pending request.
     */
    private function request(): PendingRequest
    {
        return Http::baseUrl($this->router->admin)
            ->timeout(10)
            ->connectTimeout(3)
            ->asJson()
            ->acceptJson();
    }
}
