# Contributing to Rift

Rift is a [Laravel Zero] application. Everything you need to work on it runs in
containers, so you only need [Docker] and [GNU Make] on your machine: no
[PHP], [Composer] or [Bun].

## Getting started

```bash
git clone https://github.com/LucaPipolo/rift.git
cd rift
make composer install
make bun install
make test
```

The first `make` command builds two images: `rift-dev`, with PHP 8.5,
Composer, Git and [PCOV], and `rift-bun`, with Bun and Git. `make bun install`
also installs the Git hook that checks commit messages.

Containers start for a single command and are removed when it finishes, so
there is nothing to keep running: `docker compose up` is never needed. They run
as your user, so files they create belong to you.

## Development commands

Run `make` on its own to list every target.

| Command                 | What it does                                                                  |
|-------------------------|-------------------------------------------------------------------------------|
| `make test`             | Run the test suite, then the type-coverage check.                             |
| `make test:unit`        | Run the test suite only. Narrow it with `ARGS="--filter=Instance"`.           |
| `make test:types`       | Check type coverage. 100% is required.                                        |
| `make test:coverage`    | Run the test suite with code coverage. 100% is required.                      |
| `make sniff`            | Run [Larastan] static analysis at the max level.                              |
| `make lint`             | Check formatting with [Pint], as CI does.                                     |
| `make format`           | Fix formatting.                                                               |
| `make rift ARGS="list"` | Run Rift from source inside the container.                                    |
| `make build`            | Build a native binary for this machine at `builds/rift`.                      |
| `make checksums`        | Re-pin the PHP runtime checksums. See [Updating PHP](#updating-php).          |
| `make shell`            | Open a shell in the `rift-dev` container.                                     |
| `make image`            | Rebuild both images, for example after changing `docker/Dockerfile`.          |
| `make clean`            | Remove the build output, both images and their caches.                        |
| `make composer …`       | Run Composer. See below.                                                      |
| `make bun …`            | Run Bun. See below.                                                           |

### Composer and Bun

`make composer` and `make bun` pass everything after them to the tool, so they
read like the tools themselves:

```bash
make composer update
make composer update laravel-zero/framework
make composer require vendor/package
make bun install
```

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

## Project structure

```text
app/
├── Checkout/     a Git checkout, and the instance Rift serves it as
├── Commands/     the CLI; router:* lives in Commands/Router
│   └── Concerns/ step rendering and router steps shared by commands
├── Config/       rift.yaml, the global router config and the init template
├── Contracts/    interfaces
├── Docker/       the Docker CLI, Compose files and the container runner
├── Enums/        presets, hook stages and container states
├── Env/          .env projection and host port resolution
├── Exceptions/   errors the user can act on
├── Providers/    container bindings
├── Router/       the shared Caddy router, its admin API and its CA
└── Support/      terminal output
docker/           the development images and the binary build script
Formula/          the Homebrew formula, as this repository is its own tap
tests/
├── Feature/      commands, and classes that use a Laravel facade
├── Helpers/      shared test helpers, loaded by tests/Pest.php
└── Unit/         plain classes, mirroring app/
```

Commands receive their dependencies through `handle()` method injection.
Bindings live in `AppServiceProvider`.

## Code conventions

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

## Tests

Tests are written with [Pest] and cover 100% of `app/`.

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

`make test:coverage` writes an HTML report to `.coverage/html/index.html`,
showing which tests cover each line.

Locally, Pest's [TIA engine][Pest TIA] re-runs only the tests your changes
affect, and replays the rest from a cache in `.pest/tia`. Pass
`ARGS="--no-tia"` to run everything, or `ARGS="--tia --fresh"` to rebuild the
cache. `make test:coverage` and CI always run the whole suite, because replayed
tests leave the HTML report empty.

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

1. Branch off `main`.
2. Make your change, with tests for the behaviour you add or change.
3. Update `README.md` when a command, option or `rift.yaml` key changes.
4. Run the same checks as CI:

   ```bash
   make lint
   make sniff
   make test
   make test:coverage
   ```

5. Push the branch and open a pull request, for example with the [GitHub CLI]
   from the branch:

   ```bash
   gh pr create --fill
   ```

Give the pull request a Conventional Commit title too: when a pull request is
squash-merged, its title becomes the commit message. Every pull request runs
these workflows:

| Workflow   | What it checks                                                                 |
|------------|--------------------------------------------------------------------------------|
| Lint       | Formatting, as `make lint` does.                                               |
| Sniff      | Static analysis, as `make sniff` does.                                         |
| Tests      | The whole suite with 100% coverage, as `make test:coverage` does.              |
| Types      | 100% type coverage, as `make test:types` does.                                 |
| Commitlint | Every commit message in the pull request.                                      |

The Tests workflow comments on the pull request with how coverage changed from
the base branch, and uploads the HTML report as the `coverage-html` artifact.
Pull requests from forks and Dependabot get the artifact but no comment.

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

Publishing the release starts the `release` workflow. It builds the four
binaries, then uploads them, `rift.phar` and their checksums to the release.
Rerunning it replaces the assets. GitHub does not start it for draft releases,
so publish the release directly rather than saving a draft first.

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

A Rift binary is `rift.phar`, built with Laravel Zero's `app:build`, appended
to a static PHP runtime from [static-php-cli]. `docker/build.sh` does both, for
every target you name:

```bash
bash docker/build.sh darwin-arm64 darwin-x86_64 linux-arm64 linux-x86_64
```

It builds the PHAR in a copy of the working tree with production dependencies
only, so your `vendor/` is left alone. The version comes from the `VERSION`
variable, or from `git describe` when it is not set. `make build` runs it for
your machine only. Runtimes are cached after the first download.

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

[Bun]: https://bun.sh/
[commitlint]: https://commitlint.js.org/
[Composer]: https://getcomposer.org/
[Conventional Commits]: https://www.conventionalcommits.org/
[Docker]: https://www.docker.com/
[GitHub CLI]: https://cli.github.com/
[GNU Make]: https://www.gnu.org/software/make/
[Homebrew]: https://brew.sh/
[Husky]: https://typicode.github.io/husky/
[issues]: https://github.com/LucaPipolo/rift/issues
[Larastan]: https://github.com/larastan/larastan
[Laravel Zero]: https://laravel-zero.com/
[new app]: https://github.com/settings/apps/new
[PCOV]: https://github.com/krakjoe/pcov
[Pest]: https://pestphp.com/
[Pest TIA]: https://pestphp.com/docs/tia
[PHP]: https://www.php.net/
[Pint]: https://laravel.com/docs/pint
[Semantic Versioning]: https://semver.org/
[static-php-cli]: https://static-php.dev/
