---
title: Agent development environments
nextjs:
  metadata:
    title: Agent development environments
    description: Start and stop a Rift app automatically for each AI agent worktree, using the lifecycle scripts of an agent development environment such as Orca.
---

Agent development environments (ADEs) such as [Orca](https://onorca.dev/) create
a worktree for each AI agent. Use their lifecycle scripts to start an app when a
worktree is created, and stop it when the task is archived. {% .lead %}

---

## Before you start

- Complete the [Quick start](/docs/quick-start) for your project, so
  `rift.yaml` and your Docker Compose changes are committed.
- Make sure `rift` is available on the `PATH` the scripts run with.
- Complete the initial certificate trust step interactively, by running
  `rift setup` once yourself. It may ask for your system password, which a
  script cannot type.

---

## Set up Orca

Add these commands to your repository's scripts in
[Orca](https://onorca.dev/docs/model/worktrees).

### Setup script

```bash
cd "$ORCA_WORKTREE_PATH" && rift setup
```

`rift setup` prepares the environment and runs `rift up`, so no extra start
command is needed.

### Archive script

```bash
cd "$ORCA_WORKTREE_PATH" && rift down
```

This stops that app and removes its route, while the other apps and the shared
router keep running.

---

## Other environments

Any tool that runs a command when a worktree is created and when it is removed
works the same way: run `rift setup` from the new worktree's directory, and
`rift down` from it before it goes away.
