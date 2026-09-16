<?php

declare(strict_types=1);

namespace App\Config;

use App\Enums\HookStage;
use App\Enums\Preset;
use App\Exceptions\ConfigException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * The settings a project declares in its rift.yaml.
 */
final readonly class ProjectConfig
{
    public const string FILENAME = 'rift.yaml';

    /**
     * Create a new project config instance.
     *
     * @param  string  $name  The project name.
     * @param  string  $baseDomain  The main checkout's domain.
     * @param  Preset  $preset  The preset driving the containers.
     * @param  string  $appService  The service the router proxies to.
     * @param  int  $appPort  The port the service listens on.
     * @param  list<ForwardedPort>  $ports  The host ports to keep clear.
     * @param  list<string>  $hosts  Subdomain labels routed to the same container.
     * @param  array<string, string>  $env  The env templates, keyed by env key.
     * @param  array<string, list<Hook>>  $hooks  Hooks keyed by HookStage value.
     * @param  string|null  $router  The router the project pins, or null for the shared one.
     */
    public function __construct(
        public string $name,
        public string $baseDomain,
        public Preset $preset,
        public string $appService,
        public int $appPort,
        public array $ports = [],
        public array $hosts = [],
        public array $env = [],
        public array $hooks = [],
        public ?string $router = null,
    ) {}

    /**
     * Determine whether a checkout has a rift.yaml.
     *
     * @param  string  $root  The checkout's root directory.
     *
     * @return bool Whether rift.yaml exists.
     */
    public static function existsIn(string $root): bool
    {
        return is_file(self::pathIn($root));
    }

    /**
     * Get the path of the rift.yaml in a checkout.
     *
     * @param  string  $root  The checkout's root directory.
     *
     * @return string The rift.yaml path.
     */
    public static function pathIn(string $root): string
    {
        return $root . '/' . self::FILENAME;
    }

    /**
     * Load and validate the rift.yaml in a checkout.
     *
     * @param  string  $root  The checkout's root directory.
     *
     * @return self The validated config.
     *
     * @throws ConfigException
     */
    public static function load(string $root): self
    {
        if (! self::existsIn($root)) {
            throw new ConfigException(
                'No ' . self::FILENAME . ' in this checkout. Run `rift init` to create one.'
            );
        }

        try {
            $data = Yaml::parseFile(self::pathIn($root));
        } catch (ParseException $e) {
            throw new ConfigException(self::FILENAME . ' is not valid YAML: ' . $e->getMessage());
        }

        if (! is_array($data)) {
            throw new ConfigException(self::FILENAME . ' must contain a YAML mapping.');
        }

        return self::fromArray($data);
    }

    /**
     * Create a project config from parsed rift.yaml data.
     *
     * @param  array<array-key, mixed>  $data  The parsed rift.yaml.
     *
     * @return self The validated config.
     *
     * @throws ConfigException
     */
    public static function fromArray(array $data): self
    {
        $file = self::FILENAME;

        $name = $data['name'] ?? null;

        // Validated here because Compose's own error for a bad project name is unclear.
        if (! is_string($name) || ! preg_match('/^[a-z0-9][a-z0-9_-]*$/', $name)) {
            throw new ConfigException(
                "{$file}: `name` must start with a letter or digit and hold only "
                . 'lowercase letters, digits, hyphens and underscores.'
            );
        }

        $domain = $data['domain'] ?? null;

        if (! is_string($domain) || ! preg_match('/^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)+$/', $domain)) {
            throw new ConfigException("{$file}: `domain` must be a hostname such as `{$name}.test`.");
        }

        $presetName = $data['preset'] ?? Preset::Compose->value;

        if (! is_string($presetName)) {
            throw new ConfigException("{$file}: `preset` must be a string.");
        }

        $preset = Preset::parse($presetName);

        $app = $data['app'] ?? [];

        if (! is_array($app)) {
            throw new ConfigException("{$file}: `app` must be a mapping of `service` and `port`.");
        }

        $service = $app['service'] ?? $preset->defaultService();

        if (! is_string($service) || trim($service) === '') {
            throw new ConfigException("{$file}: `app.service` cannot be empty.");
        }

        $port = $app['port'] ?? null;

        if (! is_int($port) || $port < 1 || $port > 65535) {
            throw new ConfigException(
                "{$file}: `app.port` must be the port the app listens on inside its container."
            );
        }

        $ports = [];

        foreach (self::listOf($data['ports'] ?? [], "{$file}: `ports`") as $entry) {
            $ports[] = ForwardedPort::fromArray($entry, "{$file}: `ports`");
        }

        return new self(
            name: $name,
            baseDomain: $domain,
            preset: $preset,
            appService: $service,
            appPort: $port,
            ports: $ports,
            hosts: self::parseHosts($data['hosts'] ?? [], $domain),
            env: self::parseEnv($data['env'] ?? []),
            hooks: self::parseHooks($data['hooks'] ?? []),
            router: self::parseRouter($data['router'] ?? null),
        );
    }

    /**
     * Get the hooks declared for a lifecycle stage.
     *
     * @param  HookStage  $stage  The lifecycle stage.
     *
     * @return list<Hook> The stage's hooks.
     */
    public function hooks(HookStage $stage): array
    {
        return $this->hooks[$stage->value] ?? [];
    }

    /**
     * Validate the host aliases as single DNS labels.
     *
     * A dotted label would sit below the project's wildcard certificate and be
     * served without a valid one.
     *
     * @param  mixed  $value  The raw `hosts` value.
     * @param  string  $domain  The base domain, for error messages.
     *
     * @return list<string> The host labels.
     *
     * @throws ConfigException
     */
    private static function parseHosts(mixed $value, string $domain): array
    {
        $file = self::FILENAME;

        if (! is_array($value) || ! array_is_list($value)) {
            throw new ConfigException("{$file}: `hosts` must be a list of subdomain labels such as `api`.");
        }

        $hosts = [];

        foreach ($value as $host) {
            if (! is_string($host) || ! preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $host)) {
                throw new ConfigException(
                    "{$file}: `hosts` entries must be single lowercase DNS labels such as `api`, "
                    . "served as `api.{$domain}`."
                );
            }

            if (in_array($host, $hosts, true)) {
                throw new ConfigException("{$file}: `hosts` lists `{$host}` twice.");
            }

            $hosts[] = $host;
        }

        return $hosts;
    }

    /**
     * Validate the env templates.
     *
     * @param  mixed  $value  The raw `env` value.
     *
     * @return array<string, string> The templates, keyed by env key.
     *
     * @throws ConfigException
     */
    private static function parseEnv(mixed $value): array
    {
        $env = [];

        foreach ((array) $value as $key => $template) {
            if (! is_string($key) || ! preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                throw new ConfigException(self::FILENAME . ': `env` keys must be in SCREAMING_SNAKE_CASE.');
            }

            if (! is_scalar($template) && $template !== null) {
                throw new ConfigException(self::FILENAME . ': `env` values must be scalar or null.');
            }

            $env[$key] = (string) $template;
        }

        return $env;
    }

    /**
     * Validate the hooks for every lifecycle stage.
     *
     * @param  mixed  $value  The raw `hooks` value.
     *
     * @return array<string, list<Hook>> The hooks, keyed by stage value.
     *
     * @throws ConfigException
     */
    private static function parseHooks(mixed $value): array
    {
        $hooks = [];

        foreach (HookStage::cases() as $stage) {
            if (! is_array($value) || ! isset($value[$stage->value])) {
                continue;
            }

            $context = self::FILENAME . ": `hooks.{$stage->value}`";
            $entries = $value[$stage->value];

            // A single hook may be written as a mapping rather than a list.
            if (is_array($entries) && isset($entries['run'])) {
                $entries = [$entries];
            }

            foreach (self::listOf($entries, $context) as $entry) {
                $hooks[$stage->value][] = Hook::fromArray($entry, $context);
            }
        }

        return $hooks;
    }

    /**
     * Validate the router a project pins for itself.
     *
     * @param  mixed  $value  The raw `router` value.
     *
     * @return string|null The router name, or null.
     *
     * @throws ConfigException
     */
    private static function parseRouter(mixed $value): ?string
    {
        if ($value !== null && (! is_string($value) || trim($value) === '')) {
            throw new ConfigException(self::FILENAME . ': `router`, when given, must name a router.');
        }

        return $value;
    }

    /**
     * Assert that a value is a list of mappings.
     *
     * @param  mixed  $value  The raw value.
     * @param  string  $context  Where the value sits, for error messages.
     *
     * @return list<array<string, mixed>> The mappings.
     *
     * @throws ConfigException
     */
    private static function listOf(mixed $value, string $context): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new ConfigException("{$context} must be a list.");
        }

        foreach ($value as $entry) {
            if (! is_array($entry)) {
                throw new ConfigException("{$context} must be a list of mappings.");
            }
        }

        /** @var list<array<string, mixed>> $value */
        return $value;
    }
}
