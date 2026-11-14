---
title: Installation
nextjs:
  metadata:
    title: Installation
    description: Install Rift with Homebrew or from a release binary, and prepare your machine to run it.
---

Install Rift with Homebrew or from a release binary. No local PHP installation
is needed: the binary includes its own runtime. {% .lead %}

---

## Requirements

Before starting a project, you need:

- **macOS or Linux**, with [Git](https://git-scm.com/) installed and
  [Docker](https://www.docker.com/) running.
  [Docker Desktop](https://www.docker.com/products/docker-desktop/),
  [OrbStack](https://orbstack.dev/) and [Colima](https://github.com/abiosoft/colima)
  are supported ways to run Docker.
- **A project** using [Docker Compose](https://docs.docker.com/compose/) or
  [Laravel Sail](https://laravel.com/docs/sail), with an `.env` or `.env.example`
  file.
- **Local DNS for `*.test`**, pointing to `127.0.0.1`, or to the `router.bind`
  address when you set one. See [Local DNS](#local-dns) below.
- **Ports 80, 443 and 2019** available for Rift's shared router.

{% callout title="Already running Valet, Herd or Lerd?" %}
They also need ports 80 and 443. You can keep them running: see
[Running alongside Valet, Herd or Lerd](/docs/valet-herd-lerd).
{% /callout %}

---

## Install with Homebrew

Add and trust Rift's tap, then install it with [Homebrew](https://brew.sh/):

```bash
brew tap lucapipolo/rift https://github.com/LucaPipolo/rift
brew trust lucapipolo/rift
brew install rift
```

To update Rift from then on:

```bash
brew upgrade rift
```

---

## Install from a release binary

Download the binary for your machine from the
[latest release](https://github.com/LucaPipolo/rift/releases/latest).

| Platform             | File                 |
| -------------------- | -------------------- |
| macOS, Apple Silicon | `rift-darwin-arm64`  |
| macOS, Intel         | `rift-darwin-x86_64` |
| Linux, ARM64         | `rift-linux-arm64`   |
| Linux, x86-64        | `rift-linux-x86_64`  |

For example, on an Apple Silicon Mac:

```bash
curl -fsSL -o rift https://github.com/LucaPipolo/rift/releases/latest/download/rift-darwin-arm64
chmod +x rift
sudo mv rift /usr/local/bin/rift
```

To update, download the new release's binary the same way.

---

## Local DNS

Your browser needs to resolve every `*.test` domain to the router. Rift does not
configure DNS for you, so set it up once with a tool such as
[dnsmasq](https://thekelleys.org.uk/dnsmasq/doc.html), pointing `*.test` at
`127.0.0.1`, or at your `router.bind` address when you set one.

Once a project is set up, `rift doctor` checks that its domain resolves to the
right address.

---

## Next steps

With Rift installed, follow the [Quick start](/docs/quick-start) to serve your
first project.
