# Development environment

## Rules

- Run every project task through `make`, never `php`, `composer`, `bun` or `vendor/bin/*` on the host. The host only
  needs Docker and GNU Make.
- Containers start for one command and are removed when it finishes. Never run `docker compose up`; there is nothing
  to keep running.
- `make` on its own lists every target. Pass extra arguments with `ARGS="..."` (`make test:unit ARGS="--filter=Instance"`),
  and put `--` before options passed to Composer or Bun (`make composer -- update symfony/yaml -W`).

## What runs freely, and what needs asking

Rift drives the host's Docker, ports, DNS resolver, trust store and sudo. The development container is isolated from
all of that, so anything run through `make` is safe to run without asking:

`make test`, `make test:unit`, `make test:types`, `make test:coverage`, `make sniff`, `make lint`, `make format`,
`make build`, and `make rift ARGS="..."` for commands that do not touch the host, such as `list` or `--help`.

A built binary (`builds/rift`) run on the host is different: it starts and stops real containers, binds ports 80 and
443, creates the shared router other projects on this machine depend on, and `trust` asks for sudo to change the
system trust store. Never run `builds/rift` against a real project, nor `rift up`, `down`, `setup`, `trust` or any
`router:*` command, without asking first. Prove behaviour with tests instead; that is what the fakes in `tests/` are
for.

## Commands

| Command                  | Use                                                                    |
| ------------------------ | ---------------------------------------------------------------------- |
| `make test`              | The Pest suite, then type coverage. Run after every change.            |
| `make test:coverage`     | The whole suite with 100% line coverage, as CI runs it.                |
| `make test:types`        | 100% type coverage.                                                    |
| `make sniff`             | Larastan at level max.                                                 |
| `make lint`              | Check formatting with Pint, as CI does. `make format` fixes it.        |
| `make build`             | A native binary for this machine at `builds/rift`, through the PHAR.   |
| `make composer …`        | Composer, e.g. `make composer -- outdated --direct`.                   |
| `make bun …`             | Bun, for the commit tooling only.                                      |

Locally, Pest's TIA engine re-runs only the tests a change affects. `make test:coverage` and CI always run the whole
suite; pass `ARGS="--no-tia"` to `make test:unit` to do the same.

## Git hooks

The only hook is Husky's `commit-msg`, which runs commitlint in the `rift-bun` container, so Docker must be running
to commit. In a fresh worktree, run `make bun install` first, or the hook is not installed there.
