---
title: How Rift is built
nextjs:
  metadata:
    title: How Rift is built
    description: Rift is two programs shipped as one binary, a Laravel Zero application and a Go dashboard, with a JSON contract between them.
---

Rift is two programs that ship as one binary: `rift`, a PHP console
application, and `rift-dash`, the Go program that draws `rift dash`. {% .lead %}

---

## rift

`rift` is a [Laravel Zero](https://laravel-zero.com/) application. Laravel Zero
is a framework for console applications built from Laravel's components, so
Rift's commands get Laravel's service container, its process and HTTP clients
and their test fakes, without a web layer or a database.

Every command, every rule about checkouts, domains, ports and `.env` values, and
every call to Docker, Git and the [Caddy](https://caddyserver.com/) router lives
here, in `app/`.

---

## rift-dash

`rift-dash` draws `rift dash`. It is a [Go](https://go.dev/) program in `dash/`,
built with [Bubble Tea](https://github.com/charmbracelet/bubbletea) and
[Lip Gloss](https://github.com/charmbracelet/lipgloss), because a terminal
interface needs a fast event loop that PHP has no maintained library for.

It only draws, reads keys and runs commands:

- it keeps one `rift dash:watch` running, which prints a JSON snapshot of every
  project every two seconds,
- it runs `rift` itself for every action.

Rift's rules are never repeated in Go, so the two cannot disagree.

---

## The contract between them

`SnapshotJson` writes that JSON, and `dash/testdata/snapshot.json` is the
contract between the two programs:

- `SnapshotJson` must produce the fixture exactly,
- `rift-dash` is tested against the same file.

A change one side has not caught up with fails a test. Change both, and bump
`SnapshotJson::VERSION` and Go's `snapshot.Version` when a field is renamed or
removed.

---

## One binary, no runtime to install

Users need neither PHP nor Go. A release binary is `rift` packed into a PHAR,
carrying the `rift-dash` built for its platform, and appended to a static PHP
runtime from [static-php-cli](https://static-php.dev/). `rift dash` unpacks
`rift-dash` to `~/.cache/rift` on first use.

[Building binaries](/docs/releasing#building-binaries) explains how a binary is
put together.
