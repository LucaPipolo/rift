<?php

declare(strict_types=1);

namespace App\Commands;

use App\Enums\ContainerState;
use App\Exceptions\ConfigException;
use App\Router\Router;
use App\Support\Ui;

/**
 * Lists every route the shared router is serving, across all projects.
 */
final class StatusCommand extends RiftCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List everything the router is serving right now';

    /**
     * Execute the console command.
     *
     * @return int The exit code, failing when a container is down.
     */
    public function handle(): int
    {
        // Works outside a project too, listing the shared router's routes.
        try {
            $router = $this->router();
            $here = $this->instance()->routeId();
        } catch (ConfigException) {
            $router = $this->laravel->make(Router::class);
            $here = null;
        }

        if (! $router->isRunning()) {
            Ui::blank();
            Ui::muted("○  {$router->config->name} is not running");
            Ui::blank();

            return self::SUCCESS;
        }

        $routes = $router->admin->routes();

        if ($routes === null) {
            return $this->bail("{$router->config->name} is running but its admin API is not answering.");
        }

        $groups = $this->group($routes, $router->admin->certifiedDomains());
        $running = $router->docker->runningNames();
        $reachable = $router->docker->networkMembers($router->config->network());

        Ui::rule($router->config->name, $router->config->binding->listening());

        if ($groups === []) {
            Ui::blank();
            Ui::muted('   Nothing registered.');
            Ui::blank();
            Ui::endRule();

            return self::SUCCESS;
        }

        $checkouts = 0;
        $down = 0;

        foreach ($groups as $group) {
            Ui::heading($group['label']);

            foreach ($group['rows'] as $row) {
                // An alias is another name for a checkout already counted.
                if (! $row['alias']) {
                    $checkouts++;
                }

                // A registered route says nothing about whether its container is reachable.
                $state = ContainerState::of(
                    strstr($row['upstream'], ':', true) ?: $row['upstream'],
                    $running,
                    $reachable,
                );

                if (! $state->isHealthy() && ! $row['alias']) {
                    $down++;
                }

                Ui::row(
                    marker: $row['id'] === $here && ! $row['alias'] ? '❯' : '',
                    left: $row['host'],
                    right: $state->isHealthy() ? $row['upstream'] : $row['upstream'] . "  ({$state->value})",
                    colour: 'green',
                    muted: ! $state->isHealthy(),
                );
            }
        }

        Ui::blank();
        Ui::endRule(sprintf(
            '%d checkout%s · %d project%s%s',
            $checkouts,
            $checkouts === 1 ? '' : 's',
            count($groups),
            count($groups) === 1 ? '' : 's',
            $down === 0 ? '' : sprintf(' · %d down', $down),
        ));
        Ui::blank();

        if ($here !== null) {
            Ui::muted('❯ marks the checkout you are standing in.');
        }

        if ($down > 0) {
            Ui::muted('(down) the container is not running · (unreachable) it is running but not on the router network');
        }

        Ui::blank();

        return $down === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Group the routes under the project domain each belongs to.
     *
     * @param  list<array<array-key, mixed>>  $routes  The routes from the admin API.
     * @param  list<string>  $domains  The base domains the router certifies.
     *
     * @return array<string, array{label: string, rows: list<array{id: string, host: string, owner: string, alias: bool, upstream: string}>}> The labelled rows, keyed by project domain.
     */
    private function group(array $routes, array $domains): array
    {
        $groups = [];

        foreach ($routes as $route) {
            // A route lists the checkout's own domain first, then its aliases.
            $matched = data_get($route, 'match.0.host');
            $hosts = [];

            foreach (is_array($matched) ? $matched : [] as $host) {
                if (is_string($host) && $host !== '') {
                    $hosts[] = $host;
                }
            }

            if ($hosts === []) {
                continue;
            }

            $id = $route['@id'] ?? '';
            $dial = data_get($route, 'handle.0.routes.0.handle.0.upstreams.0.dial');
            $owner = $hosts[0];
            $project = $owner;

            foreach ($domains as $domain) {
                if ($owner === $domain || str_ends_with($owner, '.' . $domain)) {
                    $project = $domain;
                    break;
                }
            }

            foreach ($hosts as $host) {
                $groups[$project][] = [
                    'id' => is_string($id) ? $id : '',
                    'host' => $host,
                    'owner' => $owner,
                    'alias' => $host !== $owner,
                    'upstream' => is_string($dial) ? $dial : '—',
                ];
            }
        }

        ksort($groups);

        $labelled = [];

        foreach ($groups as $project => $rows) {
            // List the main checkout above its worktrees, each followed by its aliases.
            usort($rows, fn (array $a, array $b) => [$a['owner'] !== $project, $a['owner'], $a['alias'], $a['host']]
                <=> [$b['owner'] !== $project, $b['owner'], $b['alias'], $b['host']]);

            // Label the group with the main checkout's route id, which is the
            // project name, or the domain when only worktrees are running.
            $label = $project;

            foreach ($rows as $row) {
                if ($row['host'] === $project && $row['id'] !== '') {
                    $label = $row['id'];
                    break;
                }
            }

            $labelled[$project] = ['label' => $label, 'rows' => $rows];
        }

        return $labelled;
    }
}
