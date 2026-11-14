---
title: Development commands
nextjs:
  metadata:
    title: Development commands
    description: Every make target for working on Rift, and how to pass arguments to Composer, Bun and Go.
---

Every development task is a `make` target that runs in a container. Run `make`
on its own to list them all. {% .lead %}

---

## Checks and tests

| Command              | What it does                                                                                               |
| -------------------- | ---------------------------------------------------------------------------------------------------------- |
| `make test`          | Run the PHP and Go tests, then the PHP type-coverage check.                                                |
| `make test:php`      | Run the PHP tests only. Narrow them with `ARGS="--filter=Instance"`.                                       |
| `make test:go`       | Run the Go tests only. Narrow them with `PKG=./internal/ui`.                                               |
| `make test:types`    | Check PHP type coverage. 100% is required.                                                                 |
| `make test:coverage` | Run both test suites with code coverage. 100% is required.                                                 |
| `make sniff`         | Run static analysis: [Larastan](https://github.com/larastan/larastan) at the max level, and golangci-lint. |
| `make lint`          | Check PHP and Go formatting, with [Pint](https://laravel.com/docs/pint) and golangci-lint, as CI does.     |
| `make format`        | Fix PHP and Go formatting.                                                                                 |

---

## Running and building Rift

| Command                 | What it does                                                                                     |
| ----------------------- | ------------------------------------------------------------------------------------------------ |
| `make rift ARGS="list"` | Run Rift from source inside the container.                                                       |
| `make build`            | Build native `rift` and `rift-dash` binaries for this machine in `builds/`.                      |
| `make demo`             | Record `.github/dash.gif`, the dashboard demo, with [VHS](https://github.com/charmbracelet/vhs). |
| `make checksums`        | Re-pin the PHP runtime checksums. See [Updating PHP](/docs/releasing#updating-php).              |

---

## This documentation site

| Command             | What it does                                                                 |
| ------------------- | ---------------------------------------------------------------------------- |
| `make docs:install` | Install the site's dependencies in `docs/`.                                  |
| `make docs:dev`     | Serve the site with live reload at `http://localhost:3000`.                  |
| `make docs:build`   | Build the static site, and its Vercel output with security headers.          |
| `make docs:preview` | Serve the built site with its production headers at `http://localhost:3001`. |
| `make docs:lint`    | Check the site with ESLint and Prettier.                                     |
| `make docs:format`  | Fix the site's formatting with Prettier.                                     |
| `make docs:bun`     | Run Bun in `docs/`, through `ARGS`: `make docs:bun ARGS="outdated"`.         |

[Editing the docs](/docs/project-structure#the-documentation-site) explains
where each page lives.

---

## Containers and images

| Command      | What it does                                                        |
| ------------ | ------------------------------------------------------------------- |
| `make shell` | Open a shell in the `rift-dev` container.                           |
| `make image` | Rebuild the images, for example after changing `docker/Dockerfile`. |
| `make clean` | Remove the build output, the images and their caches.               |

---

## Composer, Bun and Go

`make composer`, `make bun` and `make go` pass everything after them to the
tool, so they read like the tools themselves:

```bash
make composer update
make composer update laravel-zero/framework
make composer require vendor/package
make bun install
make go mod tidy
```

Make drops a leading `./` from the words it passes on, so give `make go` no
package paths: `make test:go PKG=./internal/ui` runs one package instead.

Add `--` before any option, so Make passes it on instead of reading it itself:

```bash
make composer -- update laravel-zero/framework -W
make bun -- add --dev package
```
