# CLAUDE.md

Rift is a command-line tool that runs every Git worktree of a Docker Compose or Laravel Sail project side by side,
each with its own Compose project, host ports and local HTTPS domain, behind one shared Caddy router. It is built
for running several AI agents in parallel, one per worktree. It ships as a native binary (a PHAR appended to a static
PHP runtime) through GitHub releases and a Homebrew tap that lives in this repository.

## Foundational Context

This is a Laravel Zero console application. It has no web layer, no database and no runtime configuration of its
own beyond `rift.yaml` in a project and the global router config.

- php — 8.5
- laravel-zero/framework — 13
- illuminate/http — 13 (the HTTP client for Caddy's admin API)
- symfony/yaml — 8
- pestphp/pest — 5 (with the PHPStan and type-coverage plugins)
- larastan/larastan — 3, at level max
- laravel/pint — 1
- mockery/mockery — 1
- husky — 9
- commitlint — 21

## Project structure

```
app/
├── Checkout/      a Git checkout (Checkout), its locator, and the Instance it is served as
├── Commands/      the CLI; router:* lives in Commands/Router, shared steps in Commands/Concerns
├── Config/        rift.yaml (ProjectConfig), the global router config (RouterConfig, PortBinding), the init template
├── Contracts/     interfaces for what touches the host, so tests can swap in fakes
├── Docker/        the Docker CLI, Compose files, and the runner for containers and hooks
├── Enums/         presets, hook stages, container states
├── Env/           projecting derived values into .env, free host ports, name resolution and addresses
├── Exceptions/    errors the user can act on
├── Providers/     container bindings (AppServiceProvider)
├── Router/        the shared Caddy router, its admin API and its local CA
└── Support/       terminal output (Ui, Outcome)
bootstrap/          Laravel Zero bootstrap and provider list
config/             app and command registration
docker/             development images, and build.sh for the release binaries
Formula/rift.rb     the Homebrew formula; the release workflow rewrites it
tests/
├── Feature/       commands, and classes that use a Laravel facade
├── Unit/          plain classes, mirroring app/
└── Helpers/       shared test helpers, loaded by tests/Pest.php
Makefile            every development task, each run in a throwaway container
```

## Development environment

Every task runs in a container through `make`, never PHP, Composer or Bun on the host. What an agent may run on its
own, and what touches the host and needs asking first: @.claude/guidelines/dev-env.md

## Architecture

How a checkout becomes an instance, how the router, `.env` projection and container runner fit around it, and where
the seams for testing are: @.claude/guidelines/architecture.md

## Git

When to commit, what makes a commit atomic, and how fixups work: @.claude/guidelines/git.md

## Docs

What `README.md` and `CONTRIBUTING.md` must cover, and when to update or add to them: @.claude/guidelines/docs.md
