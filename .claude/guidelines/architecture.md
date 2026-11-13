# Architecture

## Checkouts and instances

`CheckoutLocator` finds the Git checkout the current directory belongs to and returns a `Checkout`: either the main
clone or a worktree, with the slug derived from its directory name. Everything per-worktree comes from that slug: the
Compose project name (`Checkout::composeName()`), which keeps each worktree's containers apart, and the domain
(`Checkout::domain()`), `myapp.test` for the main checkout and `<slug>.myapp.test` for a worktree.

`Instance` binds a checkout to its `ProjectConfig` (the project's `rift.yaml`) and the `RouterConfig` that serves it:
the shared router, or the one the project pins. Almost every class takes an `Instance` rather than the parts it is
made of. `RiftCommand`, the base of every command, resolves it once per run.

## The router

`Router` is one Caddy container every project on the machine routes through. It owns the router network that app
containers join, and registers each instance as a route through Caddy's admin API (`CaddyAdmin`, on Laravel's HTTP
client). A route is per checkout, so `down` in one worktree removes only its route and every sibling keeps serving.
Caddy issues certificates from its own local CA; `CertificateAuthority` extracts it and adds it to the macOS or Linux
system trust store, matched by fingerprint, because every Caddy CA has the same name.

The host address and ports the router publishes come from the global config as a `PortBinding`. Docker cannot change a
container's published ports, so the container is labelled with its binding and recreated when the config differs.

## `.env` projection

Rift does not template Compose files. `EnvProjector` writes the values it derives for a checkout (Compose project
name, domain, free host ports) into that checkout's `.env`, which Compose already interpolates. `PortResolver` keeps a
port while it is still free and otherwise scans upward, stepping over ports sibling worktrees have written into their
own `.env` but are not serving yet.

## Commands as steps

Commands render their work as a list of steps (`Commands/Concerns/RendersSteps`): each step returns an `Outcome`
(done, skipped, failed), subprocess output goes to a log file unless `-v` is passed, and a failed outcome stops the
run. Every line printed goes through `Support/Ui`; nothing writes to the terminal directly.

## Seams for testing

Commands receive dependencies through `handle()` method injection, bound in `AppServiceProvider`. What touches the
host goes through something tests can replace:

- Processes (Docker, Git, `security`, `openssl`) run through Laravel's `Process` facade, faked with `Process::fake()`.
- Caddy's admin API runs through the `Http` facade, faked with `Http::fake()`.
- Waits use `Sleep`, faked with `Sleep::fake()`.
- Anything else the host answers (which ports are listening, how names resolve, which addresses it holds) sits
  behind an interface in `app/Contracts/`, such as `PortProbe` and `Network`, with the real implementation bound in
  `AppServiceProvider` and a fake in `tests/Pest.php`.

A new host interaction follows one of these patterns, so no test ever reaches the real machine. `tests/Pest.php`
enforces it for processes: a feature test that runs one with no matching fake fails instead.

## rift dash

`rift dash` (`DashCommand`) checks for a terminal, finds `rift-dash` (`Companion`: unpacked from the PHAR to
`~/.cache/rift`, else beside the binary, in `builds/`, or on PATH) and hands the terminal to it, passing
`RIFT_COMMAND`, the JSON command line that runs this same rift (`Rift`). Inside the native binary `PHP_BINARY` is
empty; the micro runtime's `micro_get_self_filename()` gives the path instead.

`rift-dash` keeps one `rift dash:watch` running, which prints a `SnapshotJson` line every two seconds and on a
`refresh` line from its input. Everything Rift knows (states, domains, URLs, ports, .env sources, worktree paths
through `rift dash:plan`) is worked out in PHP; Go only draws, reads keys and runs commands: rift itself for
actions, `git` and `docker compose logs` for the logs and git tabs. `dash/testdata/snapshot.json` is the contract:
`SnapshotJson` must produce it exactly and the Go decoder is tested against it. Bump `SnapshotJson::VERSION` (and Go's
`snapshot.Version`) when a field is renamed or removed.

## Errors

`app/Exceptions/` holds the errors a user can act on: `ConfigException` (fix `rift.yaml`, `.env` or the checkout),
`RouterException`, `PortConflictException` and `AddressNotAssignedException`. Commands turn them into a failed step,
a notice with the fix, or a `bail()` message rather than a stack trace.

## Distribution

`docker/build.sh` builds `rift.phar` with Laravel Zero's `app:build` from a copy of the tree with production
dependencies only, then appends it to a static PHP runtime pinned by `PHP_VERSION` and checked against
`docker/micro.sha256`. `box.json` decides what goes in the PHAR. A dependency that works under Pest can still break
the PHAR, which is why `make build` is part of verifying a dependency change.
