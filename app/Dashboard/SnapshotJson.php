<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Config\ForwardedPort;
use App\Config\Hook;
use App\Config\RouterConfig;
use App\Docker\Container;
use App\Enums\HookStage;

/**
 * Turns a snapshot into the JSON `rift-dash` reads.
 *
 * We decided to work out everything here, down to domains, URLs and where each
 * .env value comes from, because `rift-dash` only draws: anything it derived
 * itself would be a second copy of Rift's rules, free to drift from the first.
 * VERSION changes whenever a field is renamed or removed, so an older
 * `rift-dash` refuses a snapshot it would misread.
 */
final readonly class SnapshotJson
{
    public const int VERSION = 1;

    /**
     * Encode a snapshot as one line of JSON.
     *
     * @param  Snapshot  $snapshot  The snapshot.
     *
     * @return string The JSON, without a trailing newline.
     */
    public static function encode(Snapshot $snapshot): string
    {
        // A stray byte in a branch or .env value must not cost the dashboard its whole snapshot.
        return json_encode(self::toArray($snapshot), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }

    /**
     * Turn a snapshot into the data its JSON holds.
     *
     * @param  Snapshot  $snapshot  The snapshot.
     *
     * @return array<string, mixed> The data.
     */
    public static function toArray(Snapshot $snapshot): array
    {
        return [
            'version' => self::VERSION,
            'router' => self::router($snapshot->router),
            'projects' => array_map(self::project(...), $snapshot->projects),
        ];
    }

    /**
     * Describe the router.
     *
     * @param  RouterStatus  $router  The router.
     *
     * @return array<string, mixed> The router's data.
     */
    private static function router(RouterStatus $router): array
    {
        return [
            'name' => $router->config->name,
            'image' => $router->config->image,
            'admin' => $router->config->admin,
            'listening' => $router->config->binding->listening(),
            'binding' => $router->config->binding->describe(),
            'network' => $router->config->network(),
            'configPath' => RouterConfig::path(),
            'running' => $router->running,
            'answering' => $router->routes !== null,
            'trusted' => $router->trusted,
            'members' => $router->members,
            'routes' => array_map(fn (array $route) => [
                ...$route,
                'reachable' => in_array((string) strstr($route['upstream'] . ':', ':', true), $router->members, true),
            ], $router->table),
        ];
    }

    /**
     * Describe a project.
     *
     * @param  ProjectStatus  $project  The project.
     *
     * @return array<string, mixed> The project's data.
     */
    private static function project(ProjectStatus $project): array
    {
        return [
            'name' => $project->name(),
            'root' => $project->root,
            'domain' => $project->config?->baseDomain,
            'error' => $project->error,
            'checkouts' => array_map(self::checkout(...), $project->checkouts),
        ];
    }

    /**
     * Describe a checkout.
     *
     * @param  CheckoutStatus  $checkout  The checkout.
     *
     * @return array<string, mixed> The checkout's data.
     */
    private static function checkout(CheckoutStatus $checkout): array
    {
        $instance = $checkout->instance;
        $config = $instance->config;
        $git = $checkout->git ?? new GitStatus();

        return [
            'label' => $checkout->label(),
            'root' => $checkout->root(),
            'main' => $instance->checkout->isPrimary(),
            'branch' => $checkout->branch,
            'state' => $checkout->state->value,
            'up' => $checkout->state->isUp(),
            'domain' => $instance->domain(),
            'urls' => $instance->urls(),
            'composeName' => $instance->composeName(),
            'preset' => $config->preset->value,
            'appService' => $config->appService,
            'appPort' => $config->appPort,
            'exitCode' => $checkout->app()?->exitCode(),
            'network' => $instance->router->network(),
            'containers' => array_map(self::container(...), $checkout->containers),
            'git' => [
                'ahead' => $git->ahead,
                'behind' => $git->behind,
                'changes' => $git->changes,
                'changedAt' => $git->changedAt,
            ],
            'ports' => array_map(fn (ForwardedPort $port) => [
                'key' => $port->key,
                'value' => $checkout->env[$port->key] ?? null,
                'default' => $port->default,
            ], $config->ports),
            'env' => self::env($checkout),
            'hooks' => self::hooks($checkout),
            'shell' => $config->preset->shellCommand($config->appService),
            'logs' => ['docker', 'compose', '-p', $instance->composeName(), 'logs', '--no-color', '--tail', '200', '--follow'],
        ];
    }

    /**
     * Describe a container.
     *
     * @param  Container  $container  The container.
     *
     * @return array<string, mixed> The container's data.
     */
    private static function container(Container $container): array
    {
        return [
            'name' => $container->name,
            'service' => $container->service,
            'state' => $container->state,
            'status' => $container->status,
            'running' => $container->isRunning(),
            'exitCode' => $container->exitCode(),
            'health' => $container->health(),
        ];
    }

    /**
     * Describe each .env value rift manages, and where it comes from.
     *
     * @param  CheckoutStatus  $checkout  The checkout.
     *
     * @return list<array{key: string, value: string|null, source: string}> The values, in the order rift writes them.
     */
    private static function env(CheckoutStatus $checkout): array
    {
        $config = $checkout->instance->config;
        $ports = array_map(fn (ForwardedPort $port) => $port->key, $config->ports);
        $values = [];

        foreach ($checkout->env as $key => $value) {
            $values[] = [
                'key' => $key,
                'value' => $value,
                'source' => match (true) {
                    in_array($key, $ports, true) => 'free host port',
                    isset($config->env[$key]) => 'rift.yaml: ' . $config->env[$key],
                    default => 'rift',
                },
            ];
        }

        return $values;
    }

    /**
     * Describe every hook, in the order the stages run, the preset's bootstrap first.
     *
     * @param  CheckoutStatus  $checkout  The checkout.
     *
     * @return list<array{stage: string, label: string|null, run: string, onHost: bool, unlessExists: string|null}> The hooks.
     */
    private static function hooks(CheckoutStatus $checkout): array
    {
        $config = $checkout->instance->config;
        $hooks = [];

        foreach (HookStage::cases() as $stage) {
            $bootstrap = $stage === HookStage::BeforeUp ? $config->preset->bootstrap() : null;

            foreach ($bootstrap === null ? $config->hooks($stage) : [$bootstrap, ...$config->hooks($stage)] as $hook) {
                $hooks[] = self::hook($stage, $hook);
            }
        }

        return $hooks;
    }

    /**
     * Describe one hook.
     *
     * @param  HookStage  $stage  The stage it runs at.
     * @param  Hook  $hook  The hook.
     *
     * @return array{stage: string, label: string|null, run: string, onHost: bool, unlessExists: string|null} The hook's data.
     */
    private static function hook(HookStage $stage, Hook $hook): array
    {
        return [
            'stage' => $stage->value,
            'label' => $hook->label,
            'run' => $hook->run,
            'onHost' => $hook->onHost,
            'unlessExists' => $hook->unlessExists,
        ];
    }
}
