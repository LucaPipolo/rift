# Contributing to Rift

Everything you need to work on Rift runs in containers, so you only need
[Docker] and [GNU Make] on your machine: no [PHP], [Composer], [Go] or [Bun].

This guide is also published, with search, at [getrift.sh][contributing guide],
next to the documentation for using Rift.

## How Rift is built

Rift is two programs that ship as one binary.

`rift` is a [Laravel Zero] application. Laravel Zero is a framework for
console applications built from Laravel's components, so Rift's commands get
Laravel's service container, its process and HTTP clients and their test fakes,
without a web layer or a database. Every command, every rule about checkouts,
domains, ports and `.env` values, and every call to Docker, Git and the
[Caddy] router lives here, in `app/`.

`rift-dash` draws `rift dash`. It is a [Go] program in `dash/`, built with
[Bubble Tea] and [Lip Gloss], because a terminal interface needs a fast event
loop that PHP has no maintained library for. It only draws, reads keys and runs
commands: it keeps one `rift dash:watch` running, which prints a JSON snapshot
of every project every two seconds, and it runs `rift` itself for every action.
Rift's rules are never repeated in Go, so the two cannot disagree.

`SnapshotJson` writes that JSON, and `dash/testdata/snapshot.json` is the
contract between the two: `SnapshotJson` must produce it exactly, and
`rift-dash` is tested against the same file. A change one side has not caught
up with fails a test. Change both, and bump `SnapshotJson::VERSION` and Go's
`snapshot.Version` when a field is renamed or removed.

Users need neither PHP nor Go. A release binary is `rift` packed into a PHAR,
carrying the `rift-dash` built for its platform, and appended to a static PHP
runtime from [static-php-cli]. `rift dash` unpacks `rift-dash` to
`~/.cache/rift` on first use.

## Getting started

```bash
git clone https://github.com/LucaPipolo/rift.git
cd rift
make composer install
make bun install
make test
```

The first `make` command builds the images it needs: `rift-dev`, with PHP 8.5,
Composer, Git and [PCOV], `rift-bun`, with Bun and Git, and `rift-go`, with Go,
[golangci-lint] and Git. `make bun install` also installs the Git hook that
checks commit messages.

Containers start for a single command and are removed when it finishes, so
there is nothing to keep running: `docker compose up` is never needed. They run
as your user, so files they create belong to you.

## Development commands

Run `make` on its own to list every target.

| Command                 | What it does                                                                  |
|-------------------------|-------------------------------------------------------------------------------|
| `make test`             | Run the PHP and Go tests, then the PHP type-coverage check.                   |
| `make test:php`         | Run the PHP tests only. Narrow them with `ARGS="--filter=Instance"`.          |
| `make test:go`          | Run the Go tests only. Narrow them with `PKG=./internal/ui`.                  |
| `make test:types`       | Check PHP type coverage. 100% is required.                                    |
| `make test:coverage`    | Run both test suites with code coverage. 100% is required.                    |
| `make sniff`            | Run static analysis: [Larastan] at the max level, and [golangci-lint].        |
| `make lint`             | Check PHP and Go formatting, with [Pint] and golangci-lint, as CI does.       |
| `make format`           | Fix PHP and Go formatting.                                                    |
| `make rift ARGS="list"` | Run Rift from source inside the container.                                    |
| `make build`            | Build native `rift` and `rift-dash` binaries for this machine in `builds/`.   |
| `make demo`             | Record `.github/dash.gif`, the dashboard demo in `README.md`, with [VHS].      |
| `make checksums`        | Re-pin the PHP runtime checksums. See [Updating PHP](#updating-php).          |
| `make shell`            | Open a shell in the `rift-dev` container.                                     |
| `make image`            | Rebuild the images, for example after changing `docker/Dockerfile`.           |
| `make clean`            | Remove the build output, the images and their caches.                         |
| `make composer …`       | Run Composer. See below.                                                      |
| `make bun …`            | Run Bun. See below.                                                           |
| `make go …`             | Run Go in `dash/`. See below.                                                 |
| `make docs:install`     | Install the docs site's dependencies in `docs/`.                              |
| `make docs:dev`         | Serve the docs site with live reload at `http://localhost:3000`.              |
| `make docs:build`       | Build the static docs site, and its Vercel output with security headers.      |
| `make docs:preview`     | Serve the built site with its production headers at `http://localhost:3001`.  |
| `make docs:lint`        | Check the docs site with ESLint and Prettier. `make docs:format` fixes it.    |
| `make docs:bun`         | Run Bun in `docs/`, through `ARGS`: `make docs:bun ARGS="outdated"`.          |

### Composer, Bun and Go

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

## Trying your changes

Rift drives the host's Docker, Git, ports and keychain, so most commands only
make sense on your machine, not inside the development container. To try a
change for real, build a binary and run it from any project:

```bash
make build
cd ~/Sites/myapp
~/Sites/rift/builds/rift up
```

`make rift` is enough for commands that do not touch the host, such as `list`
or `--help`.

`rift dash` needs a real terminal and the host's Docker, so try it with the
built binary too. To try `rift-dash` alone, without Docker or a project, point
it at the stand-in rift its tests use, which answers with the contract fixture:

```bash
cd dash/internal/rift
RIFT_COMMAND='["sh","testdata/fake-rift"]' ../../../builds/rift-dash
```

After a visible change to the dashboard, run `make demo` to record the
`README.md` demo again from `docker/demo/dash.tape`.

## Project structure

```text
app/
├── Checkout/     a Git checkout, and the instance Rift serves it as
├── Commands/     the CLI; router:* lives in Commands/Router
│   └── Concerns/ step rendering and router steps shared by commands
├── Config/       rift.yaml, the global router config and the init template
├── Contracts/    interfaces
├── Dashboard/    the snapshot rift dash shows, as JSON, and finding rift-dash
├── Docker/       the Docker CLI, Compose files and the container runner
├── Enums/        presets, hook stages, and checkout and container states
├── Env/          .env projection, host ports, name resolution and addresses
├── Exceptions/   errors the user can act on
├── Providers/    container bindings
├── Router/       the shared Caddy router, its admin API and its CA
└── Support/      terminal output
dash/             rift-dash, the Go program that draws rift dash
├── cmd/rift-dash/ its main, which only hands over to internal/app
├── internal/      app, the rift client, the snapshot contract, jobs, system and ui
└── testdata/      snapshot.json, the contract fixture both sides are tested against
docker/           the development images and the binary build scripts
docs/             the documentation site, a static Next.js export
Formula/          the Homebrew formula, as this repository is its own tap
tests/
├── Feature/      commands, and classes that use a Laravel facade
├── Helpers/      shared test helpers, loaded by tests/Pest.php
└── Unit/         plain classes, mirroring app/
```

Commands receive their dependencies through `handle()` method injection.
Bindings live in `AppServiceProvider`.

## Code conventions

Both languages are held to the same bar: every declaration documented, one
formatter, static analysis with no exceptions, and 100% test coverage.

### PHP

- Every PHP file declares `strict_types`.
- Every method docblock opens with a verb, describes each `@param` and the
  `@return` in a few words, and adds a description only when the code cannot
  say it:

  ```php
  /**
   * Run a lifecycle stage's hooks, stopping at the first failure.
   *
   * @param  ContainerRunner  $runner  The container runner.
   * @param  Instance  $instance  The current checkout.
   * @param  HookStage  $stage  The lifecycle stage.
   *
   * @return bool Whether every hook succeeded.
   */
  ```

- Formatting follows the Pint rules in `pint.json`. Run `make format` before
  committing.
- Code passes Larastan at the max level, and every parameter, return value,
  property and constant has a type.

### Go

- Every package, type, function and method has a doc comment saying what it
  is or does, in the third person, as Go's own documentation does:
  `// Queue runs chains of tasks in order.` Constants and variables whose name
  says what they are get none, as in the PHP code.
- Comments explain why, not what, as in the PHP code.
- Formatting follows `gofumpt` and `goimports`, and [golangci-lint] checks the
  rest, as configured in `dash/.golangci.yml`. `make format` fixes the
  formatting of both languages.
- Anything that touches the machine goes through `ui.Backend` or a function a
  test can replace, so no test runs rift or Docker.

## Tests

### Rift

Rift's tests are written with [Pest] and cover 100% of `app/`.

- One test file per class, mirroring `app/`. Commands, and classes that use a
  Laravel facade, are tested in `tests/Feature`, the rest in `tests/Unit`.
- Group a class's tests in one `describe('method()')` per public method, and a
  command's tests in one `describe('when …')` per scenario.
- Each test checks one behaviour. When a scenario has several effects, give
  each its own test and share the setup in a `beforeEach`.
- Name the outcome and the condition: `it('returns null for a key with an
  empty value')`, `it('exits with failure when Docker is not running')`.
- Use datasets for the same behaviour across several inputs.
- Nothing reaches the real machine. Fake processes with `Process::fake()`, the
  router's admin API with `Http::fake()` and waits with `Sleep::fake()`.
- Do not use `@codeCoverageIgnore`. A line no test can reach is either dead
  code or needs a seam.

Command tests build on the helpers in `tests/Helpers/Commands.php`:

| Helper            | What it does                                                                  |
|-------------------|-------------------------------------------------------------------------------|
| `project()`       | Create a checkout in a temporary directory and move into it.                  |
| `host()`          | Fake every process as a healthy machine answers. Overrides are matched first. |
| `artisan()`       | Run a command, ready for output and exit code expectations.                   |
| `answerPrompts()` | Let `expectsQuestion()` and `expectsConfirmation()` reply to prompts.         |

Dashboard tests build on the helpers in `tests/Helpers/Dashboard.php`:

| Helper                | What it does                                                             |
|-----------------------|--------------------------------------------------------------------------|
| `dashboardSnapshot()` | Build a snapshot of two projects, with a checkout in every state.        |
| `contractSnapshot()`  | Build the snapshot `dash/testdata/snapshot.json` is made from.           |
| `collectorHost()`     | Fake every process a snapshot reads, for a main checkout and a worktree. |
| `fakeTerminal()`      | Stand in for a terminal that is, or is not, one a person is at.          |

`make test:coverage` writes an HTML report to `.coverage/html/index.html`,
showing which tests cover each line.

Locally, Pest's [TIA engine][Pest TIA] re-runs only the tests your changes
affect, and replays the rest from a cache in `.pest/tia`. Pass
`ARGS="--no-tia"` to run everything, or `ARGS="--tia --fresh"` to rebuild the
cache. `make test:coverage` and CI always run the whole suite, because replayed
tests leave the HTML report empty.

### rift-dash

`rift-dash` is held to the same standard, every internal package at 100%
coverage, checked by `dash/scripts/coverage.sh` because `go test` has no
minimum of its own. `cmd/rift-dash` is left out, as its `main` only hands over
to `internal/app`.

- One `_test.go` beside each file it tests, with tests named after the
  behaviour: `TestDownAsksThenStops`.
- The `ui` package draws screens as plain text. `internal/ui/testdata/*.golden`
  holds them; after a deliberate change, rewrite them with
  `make test:go PKG=./internal/ui ARGS=-update` and review the diff.
- Nothing runs rift or Docker. `internal/rift/testdata/fake-rift` stands in
  for rift, printing the contract fixture, and `ui.Backend` lets tests record
  what the dashboard asks for.
- One test in `internal/app` runs the whole program on that fake rift, to
  prove the parts work together.
- CI also runs the tests with Go's race detector, which needs cgo and so is
  left out of the Alpine development image.

`make test:coverage` writes its Go HTML report to `dash/coverage.html`.

## Commit messages

Commit messages follow [Conventional Commits], checked by [commitlint]:

```text
<type>: <description>
```

| Type       | Use it for                                                    |
|------------|---------------------------------------------------------------|
| `feat`     | A new feature for Rift's users.                               |
| `fix`      | A bug fix for Rift's users.                                   |
| `perf`     | A change that makes Rift faster.                              |
| `refactor` | A code change that neither adds a feature nor fixes a bug.    |
| `test`     | Adding or changing tests.                                     |
| `docs`     | Documentation only.                                           |
| `style`    | Formatting only, with no change in behaviour.                 |
| `build`    | The build, the binaries or the dependencies.                  |
| `ci`       | The GitHub workflows.                                         |
| `chore`    | Anything else that does not touch `app/`.                     |
| `revert`   | Reverting an earlier commit.                                  |
| `ai`       | AI agent configuration, such as prompts or skills.            |

Rift adds `ai` to the standard types, and does not use scopes: write
`feat: add trust command`, not `feat(router): add trust command`.

Mark a breaking change with `!` after the type, and explain it in a
`BREAKING CHANGE:` footer:

```text
feat!: rename the hosts key to aliases

BREAKING CHANGE: rift.yaml uses `aliases` instead of `hosts`.
```

A [Husky] `commit-msg` hook checks each message in the `rift-bun` container, so
Docker must be running when you commit. In a worktree, run `make bun install`
there first. Because the hook can be skipped with `--no-verify`, the Commitlint
workflow checks every commit in a pull request as well.

## Pull requests

1. Branch off `main`, naming the branch after the type of its main change, such
   as `feat/trust-command` or `fix/detect-host-port-holders`.
2. Make your change in commits that each pass the checks below on their own,
   with tests for the behaviour you add or change.
3. Update `README.md` when a command, option or `rift.yaml` key changes, and
   run `make demo` when the dashboard looks different.
4. Run the same checks as CI:

   ```bash
   make lint
   make sniff
   make test
   make test:coverage
   ```

5. Push the branch and open a pull request, for example with the [GitHub CLI]:

   ```bash
   gh pr create --title "feat: add trust command" --body-file pr.md
   ```

Give the pull request a Conventional Commit title too, summarizing the whole
branch: when a pull request is squash-merged, its title becomes the commit
message. The description has exactly two sections:

```markdown
## Context

- Adds a `<domain> resolves to <address>` check to `doctor`'s project section.

## Verification Plan

1. Run `make build`, then `builds/rift doctor` in a project whose domain does
   not resolve, and confirm the check fails with the fix underneath.
```

Context lists what changed, one bullet per piece. The Verification Plan lists
the steps a reviewer takes to see it working, with the exact commands and the
output to expect. CI already runs the checks, so they are not steps.

Every pull request runs these workflows, each one job that checks PHP and Go
like its `make` target, and all of them must pass before a merge:

| Workflow   | What it checks                                                                          |
|------------|-----------------------------------------------------------------------------------------|
| Tests      | Both test suites at 100% coverage, and type coverage, as `make test:coverage` and `make test:types` do. The Go tests also run under the race detector. |
| Lint       | Formatting, as `make lint` does.                                                        |
| Sniff      | Static analysis, as `make sniff` does.                                                  |
| Build      | The four release binaries, built as the release workflow builds them.                   |
| Commitlint | Every commit message in the pull request.                                               |

The Tests workflow comments on the pull request with how PHP coverage changed
from the base branch, and uploads both HTML reports as the `coverage-html`
artifact.
Pull requests from forks and Dependabot get the artifact but no comment.

## AI tooling

Rift is developed with AI agents, often several in parallel, each in its own
worktree. Their instructions for [Claude Code] live in `CLAUDE.md` and
`.claude/`: guidelines for the architecture, Git and docs, rules for PHP, Go,
comments, tests and docs that load for the files they cover, and skills.

### Recording AI-assisted work

Commits made with AI assistance end with a trailer naming the tool and the
model that did the work, not one that only wrote the commit message:

```text
Co-Authored-By: Claude Code <noreply@anthropic.com>
Model: Opus (claude-opus-5-5)
```

### Agent skills

| Skill                  | What it does                                                                        |
|------------------------|-------------------------------------------------------------------------------------|
| `git-commit`           | Writes and checks the message for staged changes, used by agents.                   |
| `/open-pr`             | Opens a pull request with the title and description described above.                |
| `/address-pr-comments` | Works through open review feedback on a pull request, from [Codex] or any reviewer. |
| `/update-dependencies` | Updates Composer and Bun dependencies, the docs site's included, one commit per package. |

The slash commands are only run by hand: their skills set
`disable-model-invocation: true`, so an agent never starts one on its own.

### MCP servers

`.mcp.json` configures the [Model Context Protocol][MCP] servers for this
project, so every worktree gets the same ones:

| Server       | What it is for                                                        |
|--------------|-----------------------------------------------------------------------|
| [Context7]   | Current documentation for Laravel Zero, Pest, Larastan and the rest.  |

## Releasing

Rift follows [Semantic Versioning], which the commits since the last release
decide:

- any breaking change raises the major version: `v1.4.2` to `v2.0.0`,
- otherwise, any `feat` raises the minor version: `v1.4.2` to `v1.5.0`,
- otherwise, the patch version: `v1.4.2` to `v1.4.3`.

To release:

1. Check that every workflow passes on `main`.
2. Tag the version and push the tag:

   ```bash
   git tag v1.5.0
   git push origin v1.5.0
   ```

3. Publish a GitHub release for the tag:

   ```bash
   gh release create v1.5.0 --verify-tag --generate-notes
   ```

   Add `--prerelease` for versions such as `v1.5.0-rc.1`.

Publishing the release starts the `release` workflow. It builds `rift-dash` and
the four binaries that carry it, then uploads them, `rift.phar` and their
checksums to the release. Rerunning it replaces the assets. GitHub does not
start it for draft releases, so publish the release directly rather than saving
a draft first.

This repository is also Rift's [Homebrew] tap, with the formula in
`Formula/rift.rb`. For stable releases, the workflow then sets the formula's
version and four checksums, and commits it to `main` as
`build: publish v1.5.0 to Homebrew`. Pull before your next change. Prereleases
leave the formula alone, so `brew upgrade` never installs them.

That commit lands after the tag, so the tag still holds the previous formula.
That is expected: Homebrew reads the formula from `main`, and the formula only
points at the release's binaries.

### Setting up the release app

`main` only takes signed commits through pull requests, so the workflow commits
as a GitHub App that the branch ruleset lets through. Commits an app makes
through GitHub's API are signed by GitHub and show as verified. Set it up once:

1. [Create a GitHub App][new app] on your account. Give it any name, such as
   `rift-release`, untick Webhook > Active, and under Repository permissions
   set Contents to Read and write. Leave everything else off.
2. On the app's page, note its Client ID, then generate a private key, which
   downloads a `.pem` file.
3. Under Install App, install it on the `rift` repository only.
4. Save the Client ID and the key where the workflow reads them:

   ```bash
   gh variable set RELEASE_APP_CLIENT_ID --repo LucaPipolo/rift --body "<client ID>"
   gh secret set RELEASE_APP_PRIVATE_KEY --repo LucaPipolo/rift < rift-release.pem
   ```

   Then delete the `.pem` file.
5. In Settings > Rules > Rulesets, open the ruleset protecting `main`, and add
   the app to its bypass list, under Add bypass > Apps.

To change anything else in the formula, edit `Formula/rift.rb` and check it
with Homebrew's audit in its official image:

```bash
docker run --rm -v "$PWD":/work:ro homebrew/brew sh -c \
  'brew tap-new --no-git local/check && cp /work/Formula/rift.rb "$(brew --repository local/check)/Formula/" && brew audit --strict local/check/rift'
```

### Building binaries

A Rift binary is `rift.phar`, built with Laravel Zero's `app:build`, carrying
the `rift-dash` built for its platform, appended to a static PHP runtime from
[static-php-cli]. `docker/build-dash.sh` builds `rift-dash`, and
`docker/build.sh` the rest, for every target you name:

```bash
sh docker/build-dash.sh darwin-arm64 darwin-x86_64 linux-arm64 linux-x86_64
bash docker/build.sh darwin-arm64 darwin-x86_64 linux-arm64 linux-x86_64
```

It builds the PHAR in a copy of the working tree with production dependencies
only, so your `vendor/` is left alone. The version comes from the `VERSION`
variable, or from `git describe` when it is not set. `make build` runs both for
your machine only. Runtimes are cached after the first download.

Every binary is written as a new file rather than over the last one, because
macOS kills a binary rewritten in place after it has run.

### Updating PHP

The runtime's PHP version is pinned by `PHP_VERSION` in `docker/build.sh`, and
each runtime is checked against `docker/micro.sha256` before use. To move to a
newer PHP:

1. Change `PHP_VERSION` in `docker/build.sh`.
2. Run `make checksums`, and review the new `docker/micro.sha256`.
3. Run `make build` and try the binary.
4. Update the PHP version in `docker/Dockerfile` and in `.github/workflows/`,
   so development and CI stay on the same minor version.

## Reporting bugs

For a security vulnerability, follow [SECURITY.md](SECURITY.md) instead: do
not open a public issue. For anything else, [open an issue][issues] with:

- what you ran and what you expected,
- the output of the command with `-v`, for example `rift up -v`,
- the output of `rift doctor`,
- your operating system, and how you run Docker.

[Bubble Tea]: https://github.com/charmbracelet/bubbletea
[Bun]: https://bun.sh/
[Caddy]: https://caddyserver.com/
[Claude Code]: https://claude.com/product/claude-code
[Codex]: https://openai.com/codex/
[commitlint]: https://commitlint.js.org/
[Composer]: https://getcomposer.org/
[Context7]: https://context7.com/
[contributing guide]: https://getrift.sh/docs/contributing
[Conventional Commits]: https://www.conventionalcommits.org/
[Docker]: https://www.docker.com/
[GitHub CLI]: https://cli.github.com/
[GNU Make]: https://www.gnu.org/software/make/
[Go]: https://go.dev/
[golangci-lint]: https://golangci-lint.run/
[Homebrew]: https://brew.sh/
[Husky]: https://typicode.github.io/husky/
[issues]: https://github.com/LucaPipolo/rift/issues
[Larastan]: https://github.com/larastan/larastan
[Laravel Zero]: https://laravel-zero.com/
[Lip Gloss]: https://github.com/charmbracelet/lipgloss
[MCP]: https://modelcontextprotocol.io/
[new app]: https://github.com/settings/apps/new
[PCOV]: https://github.com/krakjoe/pcov
[Pest]: https://pestphp.com/
[Pest TIA]: https://pestphp.com/docs/tia
[PHP]: https://www.php.net/
[Pint]: https://laravel.com/docs/pint
[Semantic Versioning]: https://semver.org/
[static-php-cli]: https://static-php.dev/
[VHS]: https://github.com/charmbracelet/vhs
