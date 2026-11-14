---
title: Commands
nextjs:
  metadata:
    title: Commands
    description: Every Rift command, what it does and the options it takes.
---

Every Rift command, grouped by what it acts on. Commands that act on a project
use the checkout of the directory you run them in. {% .lead %}

| Command               | What it does                                                       |
| --------------------- | ------------------------------------------------------------------ |
| `rift init`           | Create a `rift.yaml` for this project.                             |
| `rift setup`          | Write this checkout's `.env` and bring it up.                      |
| `rift up`             | Start this checkout and serve it at its own HTTPS domain.          |
| `rift down`           | Stop this checkout and remove its route.                           |
| `rift status`         | List everything the router is serving right now.                   |
| `rift dash`           | Watch every project and worktree on this machine in one live view. |
| `rift doctor`         | Diagnose this machine's Rift setup.                                |
| `rift trust`          | Trust the shared router's certificate authority.                   |
| `rift router:start`   | Start the shared router.                                           |
| `rift router:stop`    | Stop the shared router.                                            |
| `rift router:restart` | Restart the shared router.                                         |

---

## Project commands

### rift init

Create a `rift.yaml` for this project. Rift asks for the project name, base
domain and app service, suggesting defaults from your environment and Docker
Compose files, and detects the configurable ports your Compose file publishes.

| Option    | What it does                       |
| --------- | ---------------------------------- |
| `--force` | Overwrite an existing `rift.yaml`. |

See [Project configuration](/docs/project-configuration).

### rift setup

Write this checkout's `.env`, then run `rift up`. Use it for a new worktree, or
after changing settings that need to be written to `.env`.

See [Worktrees and .env](/docs/worktrees-and-env).

### rift up

Start this checkout and serve it at its own HTTPS domain. Use it for a copy that
is already set up. In order, it:

1. checks that the router's ports are free, and stops with the conflict if not,
2. trusts the router's certificate authority if it is not trusted yet, asking
   for your system password up front,
3. starts the shared router if it is not running, and issues the certificates
   for this copy's domains,
4. runs the `before_up` [hooks](/docs/hooks), then starts the containers,
5. checks that the app container joined the router network, showing the Docker
   Compose change to make if not,
6. registers the copy's route, runs the `after_up` hooks, and prints its URLs.

### rift down

Stop this checkout and remove its route, running the `before_down` and
`after_down` [hooks](/docs/hooks). Every other copy, and the shared router, keep
running.

---

## Machine commands

### rift status

List everything the router is serving right now. A route can show:

| Marker          | Meaning                                                         |
| --------------- | --------------------------------------------------------------- |
| `(down)`        | The app container is not running.                               |
| `(unreachable)` | The container is running but has not joined the router network. |

### rift dash

Watch every project and worktree on this machine in one live view, and act on
any of them. Outside a terminal, it prints `rift status` instead.

See [The dashboard](/docs/dashboard).

### rift doctor

Diagnose this machine's Rift setup, and the current project's when run inside
one. Each failed check shows how to fix it.

| Area    | What it checks                                                                                                                                       |
| ------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| Docker  | Docker is running.                                                                                                                                   |
| Project | `rift.yaml` is valid, `.env` carries every variable Docker Compose interpolates, and the app container is on the router network.                     |
| Network | The project's domain resolves to the router, and the `bind` address, when set, is assigned to this machine.                                          |
| Router  | The router is running on its configured ports, its admin API answers, its certificate authority is trusted, and no other router runs the same image. |

### rift trust

Trust the shared router's certificate authority, adding it to your system trust
store. This usually asks for your system password. `rift up` does it for you
when the certificate authority is not trusted yet.

---

## Router commands

### rift router:start

Start the shared router. If its `bind` address is not assigned to your machine,
it stops and shows the command to add it.

### rift router:stop

Stop the shared router. Every copy stops being served until it starts again.

### rift router:restart

Restart the shared router, recreating its container when its ports or address
have changed in the [machine-wide settings](/docs/router#machine-wide-settings).
Its certificates are kept. Run `rift up` afterwards in each copy you want
served.

---

## Global options

Every command accepts these options:

| Option                   | What it does                                                              |
| ------------------------ | ------------------------------------------------------------------------- |
| `-v`, `-vv`, `-vvv`      | Show the output of the commands Rift runs, such as Docker, as it happens. |
| `-h`, `--help`           | Show help for the command.                                                |
| `-q`, `--quiet`          | Only show errors.                                                         |
| `--silent`               | Show nothing.                                                             |
| `-n`, `--no-interaction` | Never ask a question.                                                     |
| `--ansi`, `--no-ansi`    | Force or disable coloured output.                                         |
| `-V`, `--version`        | Show Rift's version.                                                      |

Run `rift list` to see every command, or `rift <command> --help` for one.
