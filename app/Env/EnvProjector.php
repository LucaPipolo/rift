<?php

declare(strict_types=1);

namespace App\Env;

use App\Checkout\Instance;
use App\Config\ForwardedPort;
use App\Exceptions\ConfigException;

/**
 * Writes the values rift derives for a checkout into its .env.
 *
 * Compose already interpolates .env, so the project's compose file reads these
 * values without rift ever editing it.
 */
final readonly class EnvProjector
{
    /**
     * Create a new env projector instance.
     *
     * @param  PortResolver  $ports  The host port resolver.
     */
    public function __construct(private PortResolver $ports) {}

    /**
     * Write every derived value into the checkout's .env.
     *
     * @param  Instance  $instance  The checkout to write.
     *
     * @return EnvProjection What was written.
     *
     * @throws ConfigException
     */
    public function project(Instance $instance): EnvProjection
    {
        $checkout = $instance->checkout;
        $config = $instance->config;

        $env = EnvFile::at($instance->envPath());

        // A worktree inherits the main checkout's .env and its real credentials;
        // a fresh clone falls back to the example.
        $created = $env->seedFrom([
            ...($checkout->isPrimary() ? [] : [$checkout->mainRoot . '/.env']),
            $checkout->root . '/.env.example',
        ]);

        $composeName = $instance->composeName();

        $values = [
            'COMPOSE_PROJECT_NAME' => $composeName,
            'GIT_COMMON_DIR' => $checkout->commonDir,
            'BASE_NAME' => $config->name,
            'BASE_DOMAIN' => $config->baseDomain,
            'ROUTER_NAME' => $instance->router->network(),
            'APP_SERVICE' => $config->appService,
            'CONTAINER_PORT' => (string) $config->appPort,
        ];

        $ports = [];

        foreach ($config->ports as $port) {
            $current = $env->get($port->key);

            $resolved = $this->ports->resolve(
                current: is_numeric($current) ? (int) $current : null,
                default: $port->default,
                composeProject: $composeName,
                reserved: $this->reservedBySiblings($checkout->siblings, $port->key),
            );

            $ports[$port->key] = $resolved;
            $values[$port->key] = (string) $resolved;
        }

        // Templates render against everything already derived, so a project can
        // write APP_URL: "{url}" or reference {VITE_PORT}.
        $context = $values + [
            'name' => $config->name,
            'compose_name' => $composeName,
            'domain' => $instance->domain(),
            'base_domain' => $config->baseDomain,
            'slug' => $checkout->slug ?? '',
            'url' => $instance->url(),
        ];

        foreach ($config->env as $key => $template) {
            $values[$key] = $this->render($template, $context, $key);
        }

        foreach ($values as $key => $value) {
            $env->set($key, $value);
        }

        $env->write();

        return new EnvProjection($created, $values, $ports);
    }

    /**
     * Get the keys .env must carry before the router or containers are touched.
     *
     * @param  Instance  $instance  The checkout to inspect.
     *
     * @return list<string> The env keys.
     */
    public function requiredKeys(Instance $instance): array
    {
        return [
            'COMPOSE_PROJECT_NAME',
            'GIT_COMMON_DIR',
            'BASE_NAME',
            'BASE_DOMAIN',
            'ROUTER_NAME',
            'APP_SERVICE',
            'CONTAINER_PORT',
            ...array_map(fn (ForwardedPort $port) => $port->key, $instance->config->ports),
        ];
    }

    /**
     * Get the required keys the checkout's .env is missing.
     *
     * @param  Instance  $instance  The checkout to inspect.
     *
     * @return list<string> The missing env keys.
     */
    public function missingKeys(Instance $instance): array
    {
        $env = EnvFile::at($instance->envPath());

        return array_values(array_filter(
            $this->requiredKeys($instance),
            fn (string $key) => $env->get($key) === null,
        ));
    }

    /**
     * Get the ports sibling worktrees have already written into their .env.
     *
     * A sibling that is set up but not started holds no listening port, so its
     * .env is the only sign the port is taken.
     *
     * @param  list<string>  $siblings  The sibling root directories.
     * @param  string  $key  The env key carrying the port.
     *
     * @return list<int> The reserved ports.
     */
    private function reservedBySiblings(array $siblings, string $key): array
    {
        $reserved = [];

        foreach ($siblings as $sibling) {
            $value = EnvFile::at($sibling . '/.env')->get($key);

            if (is_numeric($value)) {
                $reserved[] = (int) $value;
            }
        }

        return array_values(array_unique($reserved));
    }

    /**
     * Replace the {placeholders} in an env template.
     *
     * @param  string  $template  The template.
     * @param  array<string, string>  $context  The placeholder values.
     * @param  string  $key  The env key, for error messages.
     *
     * @return string The rendered value.
     *
     * @throws ConfigException
     */
    private function render(string $template, array $context, string $key): string
    {
        return (string) preg_replace_callback(
            '/\{([A-Za-z][A-Za-z0-9_]*)\}/',
            function (array $match) use ($context, $key): string {
                if (! array_key_exists($match[1], $context)) {
                    throw new ConfigException(
                        "rift.yaml: `env.{$key}` uses `{$match[0]}`, which rift does not derive. "
                        . 'Available: ' . implode(', ', array_keys($context)) . '.'
                    );
                }

                return $context[$match[1]];
            },
            $template,
        );
    }
}
