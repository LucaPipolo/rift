---
title: Hooks
nextjs:
  metadata:
    title: Hooks
    description: Run project-specific commands, such as migrations or dependency installs, before or after Rift starts or stops an app.
---

Hooks run project-specific commands at fixed points in an app's lifecycle, such
as installing dependencies or running migrations once the containers are up.
{% .lead %}

---

## Add a hook

```yaml
hooks:
  after_up:
    - run: php artisan migrate
      label: Running migrations
```

Each stage holds a list of hooks, run in order.

---

## Stages

| Stage         | Runs                                                     |
| ------------- | -------------------------------------------------------- |
| `before_up`   | Before Rift starts the containers.                       |
| `after_up`    | After the containers are up and the route is registered. |
| `before_down` | Before Rift stops the containers.                        |
| `after_down`  | After the containers are stopped.                        |

---

## Hook keys

| Key             | Purpose                                                                       |
| --------------- | ----------------------------------------------------------------------------- |
| `run`           | The command to execute. Required.                                             |
| `label`         | A description displayed while it runs.                                        |
| `on`            | `app` (default) runs it in the app container; `host` runs it on your machine. |
| `unless_exists` | Skip the hook if this path exists, relative to the project root.              |

`unless_exists` makes a setup step run once per worktree. For example, install
JavaScript dependencies only when a worktree has none yet:

```yaml
hooks:
  after_up:
    - run: npm install
      label: Installing JavaScript dependencies
      unless_exists: node_modules
```

---

## How failures are handled

- A stage stops at its first failing hook.
- In `rift up`, a failing hook stops the command too, so a failed `before_up`
  hook means the containers never start.
- In `rift down`, the app is stopped anyway: a failing `before_down` hook never
  leaves a copy running.
- Hooks that run inside the app container (`on: app`) need that container to be
  running, so they belong in `after_up` or `before_down`. Use `on: host` for
  anything that has to run before the containers start or after they stop.

To see a hook's full output as it runs, add `-v` to the command, such as
`rift up -v`. The dashboard's hooks tab also shows each copy's hooks.
