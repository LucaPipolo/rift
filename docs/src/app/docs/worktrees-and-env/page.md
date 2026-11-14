---
title: Worktrees and .env
nextjs:
  metadata:
    title: Worktrees and .env
    description: How Rift names each worktree, keeps their containers apart, and writes the values each copy needs into its .env.
---

Every copy of your project gets its own name, domain, containers and ports. Rift
works all of them out from the worktree's directory and writes them into that
copy's `.env`. {% .lead %}

---

## How a worktree is named

The main checkout is served at the base domain from `rift.yaml`. A worktree is
served at a subdomain named after its directory:

| Directory       | Domain                   | Docker Compose project |
| --------------- | ------------------------ | ---------------------- |
| `myapp/`        | `myapp.test`             | `myapp`                |
| `myapp-search/` | `search.myapp.test`      | `myapp-search`         |
| `checkout-v2/`  | `checkout-v2.myapp.test` | `myapp-checkout-v2`    |

A directory that starts with the project name, such as `myapp-search` beside
`myapp`, has that prefix dropped, so its domain stays short. Any other name is
used as it is, lowercased, with characters other than letters, digits, `.`, `_`
and `-` replaced by `-`.

Each copy runs as its own Docker Compose project, so its containers, networks
and volumes never collide with a sibling's.

---

## What Rift writes to `.env`

Rift does not template your Docker Compose files. It writes the values it
derives for each copy into that copy's `.env`, which Docker Compose already
reads:

| Key                    | Value                                                                       |
| ---------------------- | --------------------------------------------------------------------------- |
| `COMPOSE_PROJECT_NAME` | This copy's Docker Compose project, such as `myapp-search`.                 |
| `GIT_COMMON_DIR`       | The Git directory every worktree shares, so Git works inside the container. |
| `BASE_NAME`            | The project `name` from `rift.yaml`.                                        |
| `BASE_DOMAIN`          | The base `domain` from `rift.yaml`.                                         |
| `ROUTER_NAME`          | The router network your app joins.                                          |
| `APP_SERVICE`          | The app service from `rift.yaml`.                                           |
| `CONTAINER_PORT`       | The port the app listens on inside its container.                           |

It also writes every [managed port](/docs/project-configuration#managed-ports),
and every value from the [`env` section](/docs/urls-and-hostnames) of
`rift.yaml`.

Rift updates only the values it manages. Unrelated keys, comments and ordering
are preserved.

---

## Where a new worktree's `.env` comes from

When `rift setup` runs in a worktree with no `.env`, it starts from:

1. the main copy's `.env`, when there is one, so your local secrets and settings
   carry over,
2. otherwise, the project's `.env.example`.

Rift then writes its own values on top.

---

## How ports are chosen

For each managed port, Rift keeps the port a copy already has while it is still
free. Otherwise, it scans upward for the next free one, skipping ports a sibling
worktree has already claimed in its own `.env`, even when that sibling is not
running yet. Two copies never end up with the same port.

Only ports published through a variable can move. See
[Managed ports](/docs/project-configuration#managed-ports).

---

## Removing a worktree

Stop its app first, so its containers and route go away, then remove the
worktree with Git:

```bash
cd ../myapp-search
rift down
cd ../myapp
git worktree remove ../myapp-search
```

The [dashboard](/docs/dashboard) can also create and remove worktrees for you.
