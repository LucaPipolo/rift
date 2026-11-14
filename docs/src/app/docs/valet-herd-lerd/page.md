---
title: Running alongside Valet, Herd or Lerd
nextjs:
  metadata:
    title: Running alongside Valet, Herd or Lerd
    description: Share ports 80 and 443 with Valet, Herd or Lerd by giving Rift's router other ports or an address of its own.
---

Valet, Herd and Lerd also need ports 80 and 443. Instead of stopping one to use
the other, give Rift's router other ports, or an address of its own. {% .lead %}

Both options are set in the router's
[machine-wide settings](/docs/router#machine-wide-settings), in
`~/.config/rift/config.yaml`.

| Option                                          | Setup                    | URLs                      |
| ----------------------------------------------- | ------------------------ | ------------------------- |
| [Other ports](#other-ports)                     | None                     | `https://myapp.test:8443` |
| [An address of its own](#an-address-of-its-own) | A loopback alias and DNS | `https://myapp.rift`      |

---

## Other ports

No extra setup, but URLs carry the port:

```yaml
router:
  http: 8080
  https: 8443
```

Then run `rift router:restart`.

Use `{url}` rather than `https://{domain}` in your `rift.yaml` `env` section, so
`APP_URL` includes the port. `rift init` writes `{url}`. See
[Templates for .env](/docs/urls-and-hostnames#templates-for-env).

---

## An address of its own

URLs stay clean, but Rift's projects need a domain of their own, such as
`myapp.rift`, that resolves to that address:

```yaml
router:
  bind: 127.0.0.2
```

### On macOS

Add the loopback address, and point the `rift` domain at it with
[dnsmasq](https://thekelleys.org.uk/dnsmasq/doc.html):

```bash
sudo ifconfig lo0 alias 127.0.0.2
echo 'address=/rift/127.0.0.2' >> "$(brew --prefix)/etc/dnsmasq.d/rift.conf"
sudo sh -c 'echo "nameserver 127.0.0.1" > /etc/resolver/rift'
sudo brew services restart dnsmasq
```

{% callout type="warning" title="The alias is lost on reboot" %}
macOS drops the `lo0` alias when it restarts, unless a LaunchDaemon adds it
again at boot.
{% /callout %}

Then run `rift router:restart`, and set each project's `domain` in `rift.yaml`
to one under `rift`, such as `myapp.rift`.

If the address is missing, `rift router:start` stops and shows the command to
add it. `rift doctor` also checks that your project's domain resolves to it.

Sharing ports 80 and 443 this way has been checked on macOS with
[OrbStack](https://orbstack.dev/).

### On Linux

`127.0.0.0/8` needs no alias, but a server listening on every address may still
block the router.
