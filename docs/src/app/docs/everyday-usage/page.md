---
title: Everyday usage
nextjs:
  metadata:
    title: Everyday usage
    description: The Rift commands you run every day to start, stop and check each copy of your app.
---

Run these commands from the project or worktree you want to manage. Each one
acts on that copy only, and leaves every other copy running. {% .lead %}

---

## The commands you will use most

```bash
rift up
rift down
rift status
rift doctor
rift dash
```

| Command       | What it does                                                     |
| ------------- | ---------------------------------------------------------------- |
| `rift up`     | Start this copy of the app and serve it at its own HTTPS domain. |
| `rift down`   | Stop this copy and remove its route. Other copies keep running.  |
| `rift status` | List every app the router is serving right now.                  |
| `rift doctor` | Diagnose problems with this machine's setup and this project.    |
| `rift dash`   | Watch and manage every copy of every project in one view.        |

Every command is described in full in the [command reference](/docs/commands).

---

## `rift setup` or `rift up`?

Both start your app. The difference is whether `.env` is written first:

- **`rift setup`** writes this copy's `.env`, then runs `rift up`. Use it for a
  new worktree, or after changing settings in `rift.yaml` that need to be
  written to `.env`.
- **`rift up`** starts a copy that is already prepared.

---

## Seeing more output

By default, Rift shows each step it takes and sends the output of the commands
it runs, such as Docker, to a log file. When a step fails, Rift prints that
log's path.

To see that output as it happens, add `-v` to any command:

```bash
rift up -v
```

---

## Next steps

- Learn what Rift writes to `.env` in [Worktrees and .env](/docs/worktrees-and-env).
- Watch every copy at once with [the dashboard](/docs/dashboard).
