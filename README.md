# Rift

[![Tests][badge-tests]][workflow-tests]
[![Static analysis][badge-sniff]][workflow-sniff]
[![Type coverage][badge-types]][workflow-types]
[![Lint][badge-lint]][workflow-lint]
[![Homebrew][badge-homebrew]][releases]
[![Buy me a coffee][badge-coffee]][coffee]

Run multiple copies of your project at once, each with its own local HTTPS
address.

Rift is a command-line tool for projects using [Docker Compose] or
[Laravel Sail]. It gives each [Git worktree][worktrees] its own containers,
manages configurable host ports, and serves your apps through one shared HTTPS
router.

Let multiple AI agents work on different features or versions of your project in
parallel, with a running app for each one that you can open and review.

## Why use Rift?

A branch tracks a line of development. A [Git worktree][worktrees] is a separate
working directory that can have its own branch checked out, so you can work on
several branches without interrupting your current work. Running those copies of
an app often means conflicting ports and manual environment changes.

Rift handles that setup: give each AI agent a [worktree][worktrees] to develop a
feature, fix a bug, or try an alternative implementation, then run their apps
side by side. It works just as well for your own development and pull request
reviews:

| Working directory | Local address                |
| ----------------- | ---------------------------- |
| `myapp/`          | `https://myapp.test`          |
| `myapp-search/`   | `https://search.myapp.test`   |
| `myapp-checkout/` | `https://checkout.myapp.test` |

- Run AI agents in parallel. Give each one its own app environment to work with.
- Run project versions side by side. Start or stop one without stopping the
  others.
- Keep your existing workflow. Rift works with your [Docker Compose] or
  [Laravel Sail] setup.
- Use HTTPS locally. One [Caddy] router serves all your projects and
  [worktrees].

## Install

With [Homebrew], add and trust Rift's tap, then install it:

```bash
brew tap lucapipolo/rift https://github.com/LucaPipolo/rift
brew trust lucapipolo/rift
brew install rift
```

`brew upgrade rift` updates it from then on.

No local [PHP] installation is needed. The binary includes its own runtime.

Before starting a project, you need:

- macOS or Linux, with [Git] installed and [Docker] running. [Docker Desktop],
  [OrbStack], and [Colima] are supported ways to run [Docker].
- A project using [Docker Compose] or [Laravel Sail], with an `.env` or
  `.env.example` file.
- Local DNS for `*.test`, pointing to `127.0.0.1`. You can configure this
  with a tool such as [dnsmasq]; Rift does not configure DNS for you.
- Ports 80, 443, and 2019 available for Rift's shared router.

<details>
<summary>Install from a release binary</summary>

Download the binary for your machine from the
[latest release](https://github.com/LucaPipolo/rift/releases/latest).

| Platform             | File                |
| -------------------- | ------------------- |
| macOS, Apple Silicon | `rift-darwin-arm64`  |
| macOS, Intel         | `rift-darwin-x86_64` |
| Linux, ARM64         | `rift-linux-arm64`   |
| Linux, x86-64        | `rift-linux-x86_64`  |

For example, on an Apple Silicon Mac:

```bash
curl -fsSL -o rift https://github.com/LucaPipolo/rift/releases/latest/download/rift-darwin-arm64
chmod +x rift
sudo mv rift /usr/local/bin/rift
```

</details>

## Get started

The example below uses a project named `myapp`.

### 1. Configure your project

From your project's root directory, run:

```bash
rift init
```

Answer the prompts to choose the project name, base domain, and app service.
Rift reads your existing environment and [Docker Compose] files to suggest
defaults, then creates `rift.yaml`.

Review the generated file, including any setup commands under `hooks`.

### 2. Connect your app to the router

Merge this into your existing [Docker Compose] file. Replace `app` with the
service that serves your web application (`laravel.test` for a typical
[Laravel Sail] project), and keep its existing networks and volumes:

```yaml
services:
  app:
    networks: [default, router]
    volumes:
      - "${GIT_COMMON_DIR:?run rift setup first}:${GIT_COMMON_DIR}"

networks:
  default:
  router:
    external: true
    name: "${ROUTER_NAME:?run rift setup first}"
```

The network lets the router reach your app. The volume lets [Git] work inside
the container when you use a [worktree][worktrees].

Commit `rift.yaml` and your [Docker Compose] changes so new [worktrees] include
them.

### 3. Start your app

```bash
rift setup
```

Rift prepares `.env`, starts the containers, and prints your app's URL:

```text
https://myapp.test
```

During initial setup, trusting the local HTTPS certificate may require your
system password. The same certificate authority is shared across projects.

### 4. Run another branch alongside it

Create a [worktree][worktrees] with a new branch:

```bash
git worktree add -b search ../myapp-search
cd ../myapp-search
rift setup
```

The app in your new [worktree][worktrees] is now available at
`https://search.myapp.test`, while the original app continues running at
`https://myapp.test`.

For an existing branch, use `git worktree add ../myapp-search search` instead.

## Everyday usage

Run these commands from the project or [worktree][worktrees] you want to manage:

```bash
rift up        # Start this copy of the app
rift down      # Stop this copy; leave the others running
rift status    # See the apps registered with the router
rift doctor    # Diagnose setup problems
```

Use `rift setup` for a new [worktree][worktrees], or after changing settings
that need to be written to `.env`. Use `rift up` to start an already prepared
one.

Rift updates its managed `.env` values while preserving unrelated keys,
comments, and ordering. A new [worktree][worktrees] inherits the main copy's
`.env` when available, otherwise it starts from `.env.example`.

## Integrate with an agent development environment

Rift fits into agent development environments (ADEs) such as [Orca]. Use their
lifecycle scripts to start an app when an agent's [worktree][worktrees] is
created and stop it when the task is archived.

After completing the project setup above, add these commands to your
repository's scripts in [Orca][Orca scripts]. Make sure `rift` is available on
the scripts' `PATH`.

Setup Script:

```bash
cd "$ORCA_WORKTREE_PATH" && rift setup
```

`rift setup` prepares the environment and runs `rift up`, so no extra start
command is needed. Complete the initial certificate trust step interactively
before relying on automated setup.

Archive Script:

```bash
cd "$ORCA_WORKTREE_PATH" && rift down
```

This stops that app and removes its route while leaving the other apps and
shared router running.

## Configuration and advanced usage

`rift init` generates a commented `rift.yaml`. These options let you adapt it
to your project.

<details>
<summary>Project settings and managed ports</summary>

A minimal configuration for [Docker Compose]:

```yaml
name: myapp
domain: myapp.test
preset: compose

app:
  service: app
  port: 3000
```

`app.port` is the port your app listens on inside the container.
For [Laravel Sail], use `preset: sail`, usually with service `laravel.test` and
port `80`.

To manage ports published on your machine, add their environment variable names:

```yaml
ports:
  - { key: VITE_PORT, default: 5173 }
  - { key: FORWARD_DB_PORT, default: 5432 }
```

Your [Docker Compose] file must use those variables, for example
`'${VITE_PORT:-5173}:5173'`. Rift keeps a port when it is available and
otherwise selects another. Hardcoded ports such as `'9000:9000'` cannot be
moved by Rift.

`rift init` detects configurable ports from your [Docker Compose] file. To
regenerate an existing configuration, use `rift init --force`.

</details>

<details>
<summary>App URLs and additional hostnames</summary>

Use templates to write values for each [worktree][worktrees] into `.env`:

```yaml
env:
  APP_URL: "https://{domain}"
```

Available placeholders are `{name}`, `{slug}`, `{domain}`, `{base_domain}`,
`{compose_name}`, `{url}`, and any managed port key, such as `{VITE_PORT}`.

For an app with an API or admin hostname:

```yaml
hosts:
  - api
  - admin

env:
  APP_URL: "https://{domain}"
  API_DOMAIN: "api.{domain}"
  ADMIN_DOMAIN: "admin.{domain}"
```

| App copy                     | Additional hostnames                             |
| ---------------------------- | ------------------------------------------------ |
| Main                         | `api.myapp.test`, `admin.myapp.test`               |
| `search` [worktree][worktrees] | `api.search.myapp.test`, `admin.search.myapp.test` |

All hostnames route to the same app container. Rift handles their certificates;
your app decides how to respond to each hostname.

Each alias must be a single lowercase DNS label. Rift refuses a
[worktree][worktrees] whose name would collide with one, such as `myapp-api`
when `api` is an alias.

</details>

<details>
<summary>Run commands before or after starting an app</summary>

Hooks let you run project-specific commands:

```yaml
hooks:
  after_up:
    - run: php artisan migrate
      label: Running migrations
```

The stages are `before_up`, `after_up`, `before_down`, and `after_down`.

| Key             | Purpose                                                              |
| --------------- | -------------------------------------------------------------------- |
| `run`           | Command to execute. Required.                                        |
| `label`         | Description displayed while it runs.                                 |
| `on`            | `app` (default) runs in the app container; `host` runs on your machine. |
| `unless_exists` | Skip the hook if this path exists, relative to the project root.      |

A stage stops at its first failing hook. Hooks that run inside the app container
require that container to be running.

</details>

<details>
<summary>Manage the shared router</summary>

Rift uses a [Caddy] container named `rift-router`. Stopping an app with
`rift down` leaves this shared router running for other apps.

```bash
rift router:stop
rift router:start
rift router:restart
rift trust             # Trust the router's certificate authority explicitly
```

After a router restart, run `rift up` in each app copy you want served to
register its route again.

Optional machine-wide settings live in `~/.config/rift/config.yaml`, or
`$XDG_CONFIG_HOME/rift/config.yaml`:

```yaml
router:
  name: rift-router
  image: caddy:latest
  admin: http://localhost:2019
```

A project can request a separate router with `router: myapp-router` in its
`rift.yaml`. That router still needs the same host ports, so it cannot run
alongside the shared router on the same machine. `rift down` stops a private
router when its last route is removed.

</details>

## Need help?

Start with:

```bash
rift doctor
```

For detailed command output, add `-v`, for example `rift up -v`. Without it,
subprocess output goes to a log whose path is printed when a step fails.

- The browser cannot find your domain: check that your local DNS resolves
  `*.test` to `127.0.0.1`.
- `rift status` shows `(down)`: the app container is not running.
- `rift status` shows `(unreachable)`: the container is running but has not
  joined the router network. Check the [Docker Compose] configuration in step 2.
- Ports are already in use: stop the conflicting service before starting
  Rift's router.

Still stuck? [Open an issue](https://github.com/LucaPipolo/rift/issues).

## Contributing

For development setup and contribution guidelines, see
[CONTRIBUTING.md](CONTRIBUTING.md).

## Licence

MIT

[Docker Compose]: https://docs.docker.com/compose/
[Laravel Sail]: https://laravel.com/docs/sail
[worktrees]: https://git-scm.com/docs/git-worktree
[Caddy]: https://caddyserver.com/
[Homebrew]: https://brew.sh/
[PHP]: https://www.php.net/
[Git]: https://git-scm.com/
[Docker]: https://www.docker.com/
[Docker Desktop]: https://www.docker.com/products/docker-desktop/
[OrbStack]: https://orbstack.dev/
[Colima]: https://github.com/abiosoft/colima
[dnsmasq]: https://thekelleys.org.uk/dnsmasq/doc.html
[Orca]: https://onorca.dev/
[Orca scripts]: https://onorca.dev/docs/model/worktrees

[badge-tests]: https://img.shields.io/github/actions/workflow/status/LucaPipolo/rift/tests.yml?branch=main&label=tests&style=flat-square&logo=githubactions&logoColor=white
[badge-sniff]: https://img.shields.io/github/actions/workflow/status/LucaPipolo/rift/sniff.yml?branch=main&label=static%20analysis&style=flat-square&logo=githubactions&logoColor=white
[badge-types]: https://img.shields.io/github/actions/workflow/status/LucaPipolo/rift/types.yml?branch=main&label=type%20coverage&style=flat-square&logo=githubactions&logoColor=white
[badge-lint]: https://img.shields.io/github/actions/workflow/status/LucaPipolo/rift/lint.yml?branch=main&label=lint&style=flat-square&logo=githubactions&logoColor=white
[badge-homebrew]: https://img.shields.io/github/v/release/LucaPipolo/rift?sort=semver&label=homebrew&style=flat-square&logo=homebrew&logoColor=white
[badge-coffee]: https://img.shields.io/badge/buy%20me%20a%20coffee-FFDD00?style=flat-square&logo=buymeacoffee&logoColor=black
[workflow-tests]: https://github.com/LucaPipolo/rift/actions/workflows/tests.yml
[workflow-sniff]: https://github.com/LucaPipolo/rift/actions/workflows/sniff.yml
[workflow-types]: https://github.com/LucaPipolo/rift/actions/workflows/types.yml
[workflow-lint]: https://github.com/LucaPipolo/rift/actions/workflows/lint.yml
[releases]: https://github.com/LucaPipolo/rift/releases/latest
[coffee]: https://buymeacoffee.com/lucapipolo
