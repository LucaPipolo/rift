# Development environment

## Rules

- Run every project task through `make`, never `php`, `composer`, `bun`, `go` or `vendor/bin/*` on the host. The host
  only needs Docker and GNU Make.
- Never run `docker compose run` by hand without `--user "$(id -u):$(id -g)"`: the Go cache volume is shared, and a
  run as another user leaves files `make` can no longer read. If that happens, `docker volume rm rift_go-cache`.
- Containers start for one command and are removed when it finishes. Never run `docker compose up`; there is nothing
  to keep running.
- `make` on its own lists every target. Pass extra arguments with `ARGS="..."` (`make test:php ARGS="--filter=Instance"`),
  and put `--` before options passed to Composer or Bun (`make composer -- update symfony/yaml -W`).

## What runs freely, and what needs asking

Rift drives the host's Docker, ports, DNS resolver, trust store and sudo. The development container is isolated from
all of that, so anything run through `make` is safe to run without asking:

`make test`, `make test:php`, `make test:go`, `make test:types`, `make test:coverage`, `make sniff`, `make lint`,
`make format`, `make build`, `make demo`, and `make rift ARGS="..."`
for commands that do not touch the host, such as `list` or `--help`.

`rift-dash` can be tried on the host without touching anything: run a darwin build of it under `script` with
`RIFT_COMMAND='["sh","testdata/fake-rift"]'` from `dash/internal/rift`, which answers with the contract fixture.

A built binary (`builds/rift`) run on the host is different: it starts and stops real containers, binds ports 80 and
443, creates the shared router other projects on this machine depend on, and `trust` asks for sudo to change the
system trust store. Never run `builds/rift` against a real project, nor `rift up`, `down`, `setup`, `trust` or any
`router:*` command, without asking first. Prove behaviour with tests instead; that is what the fakes in `tests/` are
for.

## Commands

| Command                  | Use                                                                    |
| ------------------------ | ---------------------------------------------------------------------- |
| `make test`              | The PHP and Go tests, then PHP type coverage. Run after every change.  |
| `make test:php`          | The Pest suite alone, e.g. `ARGS="--filter=Instance"`.                 |
| `make test:go`           | The rift-dash tests alone; `PKG=./internal/ui ARGS=-update` rewrites the golden screens. |
| `make test:coverage`     | Both suites with 100% line coverage, as CI runs them.                  |
| `make test:types`        | 100% PHP type coverage.                                                |
| `make sniff`             | Larastan at level max, and golangci-lint's linters.                    |
| `make lint`              | Check PHP and Go formatting, as CI does. `make format` fixes it.       |
| `make build`             | A native binary for this machine at `builds/rift`, through the PHAR.   |
| `make composer …`        | Composer, e.g. `make composer -- outdated --direct`.                   |
| `make bun …`             | Bun, for the commit tooling only.                                      |
| `make demo`              | Record `.github/dash.gif`, the README demo, with VHS against a fake rift. |
| `make go …`              | Go in `dash/`, e.g. `make go mod tidy`. Make drops a leading `./`, so pass no package paths. |

Locally, Pest's TIA engine re-runs only the tests a change affects. `make test:coverage` and CI always run the whole
suite; pass `ARGS="--no-tia"` to `make test:php` to do the same.

## Git hooks

The only hook is Husky's `commit-msg`, which runs commitlint in the `rift-bun` container, so Docker must be running
to commit. In a fresh worktree, run `make bun install` first, or the hook is not installed there.
