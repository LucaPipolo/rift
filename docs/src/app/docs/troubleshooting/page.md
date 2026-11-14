---
title: Troubleshooting
nextjs:
  metadata:
    title: Troubleshooting
    description: Diagnose and fix common Rift problems, from domains that do not resolve to ports already in use.
---

Most problems come from DNS, ports or a Docker Compose file that is not yet
connected to the router. `rift doctor` checks all three. {% .lead %}

---

## Start with rift doctor

```bash
rift doctor
```

It checks Docker, the router and its ports, the certificate authority, and,
inside a project, its `rift.yaml`, its `.env`, its network and that its domain
resolves to the router. Each failed check shows how to fix it. The
[command reference](/docs/commands#rift-doctor) lists every check.

For detailed command output, add `-v` to the command that fails, such as
`rift up -v`. Without it, the output of the commands Rift runs goes to a log
whose path is printed when a step fails.

---

## Common problems

### The browser cannot find your domain

Check that your local DNS resolves `*.test` to `127.0.0.1`, or to your
`router.bind` address when you set one. `rift doctor` checks this for the
current project. See [Local DNS](/docs/installation#local-dns).

### rift status shows (down)

The app container is not running. Run `rift up` in that copy, with `-v` to see
why it stops.

### rift status shows (unreachable)

The container is running but has not joined the router network. Check that your
Docker Compose file connects the app service to the `router` network, as
[step 2 of the Quick start](/docs/quick-start#2-connect-your-app-to-the-router)
shows.

### Ports are already in use

Another server holds port 80, 443 or 2019. Stop the conflicting service before
starting Rift's router, or move the router to other ports or an address of its
own, as [Running alongside Valet, Herd or Lerd](/docs/valet-herd-lerd)
describes.

### A worktree fails to start because of a port

A port hardcoded in your Docker Compose file, such as `'9000:9000'`, cannot be
moved between worktrees. Publish it through a variable and list that variable
under `ports`. See [Managed ports](/docs/project-configuration#managed-ports).

### The browser warns that the certificate is not trusted

The router's certificate authority is not in your system trust store yet. Run
`rift trust`, then restart your browser.

### A route disappeared after restarting the router

Routes are registered by `rift up`. After `rift router:restart`, run `rift up`
again in each copy you want served.

---

## Still stuck?

[Open an issue](https://github.com/LucaPipolo/rift/issues), following
[Reporting bugs](/docs/reporting-bugs) so it can be reproduced.
