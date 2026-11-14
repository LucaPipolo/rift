---
title: Project configuration
nextjs:
  metadata:
    title: Project configuration
    description: Configure a project for Rift in rift.yaml, choose a preset, and let Rift manage the host ports your app publishes.
---

Each project has one `rift.yaml` at its root, committed so every worktree shares
it. `rift init` generates a commented one; this page explains what it holds.
{% .lead %}

---

## Create or regenerate the file

```bash
rift init
```

`rift init` asks for the project name, base domain and app service, reading your
environment and Docker Compose files to suggest defaults. It also detects the
configurable ports in your Docker Compose file.

To regenerate an existing `rift.yaml`, overwriting it:

```bash
rift init --force
```

---

## A minimal configuration

For Docker Compose:

```yaml
name: myapp
domain: myapp.test
preset: compose

app:
  service: app
  port: 3000
```

| Key           | Required | What it is                                                                                                                                                  |
| ------------- | -------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `name`        | Yes      | The project name. Lowercase letters, digits, hyphens and underscores, starting with a letter or digit. It prefixes every worktree's Docker Compose project. |
| `domain`      | Yes      | The base domain, such as `myapp.test`. The main checkout is served here, and each worktree at a subdomain of it.                                            |
| `preset`      | No       | `compose` (default) or `sail`. See [Presets](#presets).                                                                                                     |
| `app.service` | No       | The Docker Compose service that serves your web app. Defaults to `app` for `compose` and `laravel.test` for `sail`.                                         |
| `app.port`    | Yes      | The port your app listens on inside the container, not on your machine.                                                                                     |

The other keys are optional and covered on their own pages:

- `ports`: [managed ports](#managed-ports), below.
- `hosts` and `env`: [URLs and hostnames](/docs/urls-and-hostnames).
- `hooks`: [Hooks](/docs/hooks).
- `router`: a [private router](/docs/router#a-private-router-for-one-project).

---

## Presets

A preset decides how Rift starts and stops your containers.

| Preset    | Starts with                       | Stops with               |
| --------- | --------------------------------- | ------------------------ |
| `compose` | `docker compose up --build -d`    | `docker compose down`    |
| `sail`    | `./vendor/bin/sail up --build -d` | `./vendor/bin/sail down` |

For [Laravel Sail](https://laravel.com/docs/sail), use `preset: sail`, usually
with service `laravel.test` and port `80`:

```yaml
name: myapp
domain: myapp.test
preset: sail

app:
  service: laravel.test
  port: 80
```

Sail is itself a Composer dependency, so a fresh worktree has no
`vendor/bin/sail` yet. With the `sail` preset, Rift installs your PHP
dependencies first, in a throwaway Composer container, whenever
`vendor/bin/sail` is missing.

---

## Managed ports

Ports your containers publish on your machine, such as a Vite dev server or a
database, would collide between worktrees. List their environment variable names
under `ports`, and Rift gives each worktree free ones:

```yaml
ports:
  - { key: VITE_PORT, default: 5173 }
  - { key: FORWARD_DB_PORT, default: 5432 }
```

Your Docker Compose file must publish those ports through the variables, for
example:

```yaml
services:
  app:
    ports:
      - '${VITE_PORT:-5173}:5173'
```

Rift keeps a port while it is available, and otherwise selects another one,
writing it to the worktree's `.env`. [Worktrees and .env](/docs/worktrees-and-env#how-ports-are-chosen)
explains how.

{% callout type="warning" title="Hardcoded ports cannot move" %}
A port written directly in the Docker Compose file, such as `'9000:9000'`,
cannot be moved by Rift, so a second worktree publishing it fails to start.
Replace it with a variable and add that variable to `ports`.
{% /callout %}

The port your app serves HTTP on, `app.port`, does not need publishing or
listing here: the router reaches it over the router network.
