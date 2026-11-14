---
title: Quick start
nextjs:
  metadata:
    title: Quick start
    description: Configure a project for Rift, connect it to the shared router, start it, and run a second branch beside it.
---

Configure a project, connect it to the router, start it, and run a second branch
alongside it. The examples use a project named `myapp`. {% .lead %}

---

## 1. Configure your project

From your project's root directory, run:

```bash
rift init
```

Answer the prompts to choose the project name, base domain and app service. Rift
reads your existing environment and Docker Compose files to suggest defaults,
then creates `rift.yaml`.

Review the generated file, including any setup commands under `hooks`. Every
option is explained in [Project configuration](/docs/project-configuration).

---

## 2. Connect your app to the router

Merge this into your existing Docker Compose file. Replace `app` with the service
that serves your web application (`laravel.test` for a typical Laravel Sail
project), and keep its existing networks and volumes:

```yaml
services:
  app:
    networks: [default, router]
    volumes:
      - '${GIT_COMMON_DIR:?run rift setup first}:${GIT_COMMON_DIR}'

networks:
  default:
  router:
    external: true
    name: '${ROUTER_NAME:?run rift setup first}'
```

The network lets the router reach your app. The volume lets Git work inside the
container when you use a worktree.

Commit `rift.yaml` and your Docker Compose changes, so new worktrees include
them.

---

## 3. Start your app

```bash
rift setup
```

Rift prepares `.env`, starts the containers, and prints your app's URL:

```text
https://myapp.test
```

{% callout title="Your system password" %}
During initial setup, trusting the local HTTPS certificate may require your
system password. The same certificate authority is shared across projects, so
you only do this once per machine.
{% /callout %}

---

## 4. Run another branch alongside it

Create a worktree with a new branch, and set it up:

```bash
git worktree add -b search ../myapp-search
cd ../myapp-search
rift setup
```

The app in your new worktree is now available at `https://search.myapp.test`,
while the original app keeps running at `https://myapp.test`.

For an existing branch, use `git worktree add ../myapp-search search` instead.

Run `rift dash` from either directory to watch both copies in one view.

---

## Next steps

- Learn the commands you will use every day in [Everyday usage](/docs/everyday-usage).
- See how `.env` values are carried between copies in [Worktrees and .env](/docs/worktrees-and-env).
- Hand worktrees to AI agents automatically with an
  [agent development environment](/docs/agent-environments).
