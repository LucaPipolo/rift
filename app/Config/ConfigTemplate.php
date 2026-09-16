<?php

declare(strict_types=1);

namespace App\Config;

use App\Enums\Preset;

/**
 * A commented rift.yaml for a project, ready to edit by hand.
 */
final readonly class ConfigTemplate
{
    /**
     * Create a new config template instance.
     *
     * @param  string  $name  The project name.
     * @param  string  $domain  The base domain.
     * @param  Preset  $preset  The preset driving the containers.
     * @param  string  $service  The service the router proxies to.
     * @param  int  $port  The port the service listens on.
     * @param  array<string, int>  $ports  The default host port, keyed by env key.
     * @param  array<string, string>  $env  The env templates, keyed by env key.
     * @param  array<string, list<array{run: string, label?: string, on?: string, unless_exists?: string}>>  $hooks  The hooks, keyed by stage.
     * @param  list<string>  $hosts  The host alias labels.
     */
    public function __construct(
        public string $name,
        public string $domain,
        public Preset $preset,
        public string $service,
        public int $port,
        public array $ports = [],
        public array $env = [],
        public array $hooks = [],
        public array $hosts = [],
    ) {}

    /**
     * Render the template as YAML.
     *
     * @return string The commented YAML.
     */
    public function render(): string
    {
        $yaml = <<<YAML
            # How rift runs this project.
            #
            # The main checkout is served at {$this->domain}; every git worktree beside
            # it gets <worktree>.{$this->domain} and its own containers, so several
            # branches — or several agents — run at once without colliding.
            #
            # rift never edits compose files. It writes what it derives into .env,
            # which is where Compose already looks, so `docker compose` and `sail`
            # keep working by hand.

            name: {$this->name}
            domain: {$this->domain}

            preset: {$this->preset->value}

            app:
              service: {$this->service}
              port: {$this->port}

            YAML;

        if ($this->hosts !== []) {
            $yaml .= "\n# Extra hostnames routed to the same container, one subdomain label each:\n"
                . "# `api` is served as api.{$this->domain}, and as api.<worktree>.{$this->domain}\n"
                . "# in a worktree. Hand them to the app through `env` below.\nhosts:\n";

            foreach ($this->hosts as $host) {
                $yaml .= "  - {$host}\n";
            }
        }

        if ($this->ports !== []) {
            $yaml .= "\n# Host ports this checkout publishes. rift keeps the port already in\n"
                . "# .env when it still works, and otherwise scans upwards from the default\n"
                . "# until it finds one no sibling worktree has taken.\nports:\n";

            foreach ($this->ports as $key => $default) {
                $yaml .= "  - { key: {$key}, default: {$default} }\n";
            }
        }

        if ($this->env !== []) {
            $yaml .= "\n# Values rendered per checkout. Available: {name}, {slug}, {domain},\n"
                . "# {base_domain}, {compose_name}, {url}, and any port key above.\nenv:\n";

            foreach ($this->env as $key => $value) {
                $yaml .= "  {$key}: \"{$value}\"\n";
            }
        }

        if ($this->hooks !== []) {
            $yaml .= "\n# Commands rift runs around the lifecycle. `on: app` runs inside the\n"
                . "# app container; `on: host` runs on your machine.\nhooks:\n";

            foreach ($this->hooks as $stage => $entries) {
                $yaml .= "  {$stage}:\n";

                foreach ($entries as $entry) {
                    $yaml .= "    - run: {$entry['run']}\n";

                    foreach (['label', 'on', 'unless_exists'] as $key) {
                        if (isset($entry[$key])) {
                            $yaml .= "      {$key}: {$entry[$key]}\n";
                        }
                    }
                }
            }
        }

        return $yaml;
    }
}
