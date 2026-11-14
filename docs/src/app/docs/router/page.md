---
title: The shared router
nextjs:
  metadata:
    title: The shared router
    description: Manage the Caddy router every Rift project routes through, trust its certificate authority, and change its machine-wide settings.
---

Every project on your machine routes through one [Caddy](https://caddyserver.com/)
container, `rift-router`. It serves each copy's domain over HTTPS, with
certificates from its own local certificate authority. {% .lead %}

---

## How it works

- `rift up` starts the router if it is not running, and registers this copy's
  domains with it as a route.
- `rift down` removes only this copy's route. The router keeps running for every
  other copy and project.
- Your app joins the router network, so the router reaches it directly. The
  app's HTTP port is never published on your machine.

---

## Manage the router

```bash
rift router:stop
rift router:start
rift router:restart
```

After a router restart, run `rift up` in each copy you want served, to register
its route again.

---

## Trust its certificate authority

Your browser trusts the router's certificates once its certificate authority is
in your system trust store. `rift up`, and so `rift setup`, does this the first
time, which may ask for your system password. To do it explicitly:

```bash
rift trust
```

The same certificate authority is shared across projects, so this happens once
per machine.

---

## Machine-wide settings

Optional settings live in `~/.config/rift/config.yaml`, or
`$XDG_CONFIG_HOME/rift/config.yaml` when that variable is set. Every key has a
default:

```yaml
router:
  name: rift-router
  image: caddy:latest
  admin: http://localhost:2019
  bind: 127.0.0.2
  http: 80
  https: 443
```

| Key     | Default                 | What it is                                              |
| ------- | ----------------------- | ------------------------------------------------------- |
| `name`  | `rift-router`           | The router's container and network name.                |
| `image` | `caddy:latest`          | The Caddy image the router runs.                        |
| `admin` | `http://localhost:2019` | Caddy's admin API, which Rift registers routes through. |
| `bind`  | every address           | The host address the router publishes its ports on.     |
| `http`  | `80`                    | The host port for HTTP.                                 |
| `https` | `443`                   | The host port for HTTPS.                                |

After changing `bind`, `http` or `https`, run `rift router:restart`. Rift
recreates the router container with the new ports and keeps its certificates.
`rift doctor` flags a running router that still uses the old ones.

The admin API always stays on `127.0.0.1:2019`, whatever `bind` is.

To share ports 80 and 443 with another local server, see
[Running alongside Valet, Herd or Lerd](/docs/valet-herd-lerd).

---

## A private router for one project

A project can request a separate router in its `rift.yaml`:

```yaml
router: myapp-router
```

That router still needs the same host ports, so it cannot run alongside the
shared router on the same machine. `rift down` stops a private router when its
last route is removed.
