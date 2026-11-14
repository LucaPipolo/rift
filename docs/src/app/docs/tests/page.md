---
title: Tests
nextjs:
  metadata:
    title: Tests
    description: How Rift's PHP and Go test suites are written, the helpers they share, and the 100% coverage they keep.
---

Both programs are tested to 100% coverage, and no test ever reaches the real
machine: processes, HTTP calls and waits are all faked. {% .lead %}

---

## Rift

Rift's tests are written with [Pest](https://pestphp.com/) and cover 100% of
`app/`.

- One test file per class, mirroring `app/`. Commands, and classes that use a
  Laravel facade, are tested in `tests/Feature`, the rest in `tests/Unit`.
- Group a class's tests in one `describe('method()')` per public method, and a
  command's tests in one `describe('when …')` per scenario.
- Each test checks one behaviour. When a scenario has several effects, give each
  its own test and share the setup in a `beforeEach`.
- Name the outcome and the condition:
  `it('returns null for a key with an empty value')`,
  `it('exits with failure when Docker is not running')`.
- Use datasets for the same behaviour across several inputs.
- Nothing reaches the real machine. Fake processes with `Process::fake()`, the
  router's admin API with `Http::fake()` and waits with `Sleep::fake()`.
- Do not use `@codeCoverageIgnore`. A line no test can reach is either dead code
  or needs a seam.

### Command helpers

Command tests build on the helpers in `tests/Helpers/Commands.php`:

| Helper            | What it does                                                                  |
| ----------------- | ----------------------------------------------------------------------------- |
| `project()`       | Create a checkout in a temporary directory and move into it.                  |
| `host()`          | Fake every process as a healthy machine answers. Overrides are matched first. |
| `artisan()`       | Run a command, ready for output and exit code expectations.                   |
| `answerPrompts()` | Let `expectsQuestion()` and `expectsConfirmation()` reply to prompts.         |

### Dashboard helpers

Dashboard tests build on the helpers in `tests/Helpers/Dashboard.php`:

| Helper                | What it does                                                             |
| --------------------- | ------------------------------------------------------------------------ |
| `dashboardSnapshot()` | Build a snapshot of two projects, with a checkout in every state.        |
| `contractSnapshot()`  | Build the snapshot `dash/testdata/snapshot.json` is made from.           |
| `collectorHost()`     | Fake every process a snapshot reads, for a main checkout and a worktree. |
| `fakeTerminal()`      | Stand in for a terminal that is, or is not, one a person is at.          |

### Coverage reports and TIA

`make test:coverage` writes an HTML report to `.coverage/html/index.html`,
showing which tests cover each line.

Locally, Pest's [TIA engine](https://pestphp.com/docs/tia) re-runs only the tests
your changes affect, and replays the rest from a cache in `.pest/tia`.

| To                    | Run                                  |
| --------------------- | ------------------------------------ |
| Run every test        | `make test:php ARGS="--no-tia"`      |
| Rebuild the TIA cache | `make test:php ARGS="--tia --fresh"` |

`make test:coverage` and CI always run the whole suite, because replayed tests
leave the HTML report empty.

---

## rift-dash

`rift-dash` is held to the same standard, every internal package at 100%
coverage, checked by `dash/scripts/coverage.sh` because `go test` has no minimum
of its own. `cmd/rift-dash` is left out, as its `main` only hands over to
`internal/app`.

- One `_test.go` beside each file it tests, with tests named after the
  behaviour: `TestDownAsksThenStops`.
- The `ui` package draws screens as plain text. `internal/ui/testdata/*.golden`
  holds them; after a deliberate change, rewrite them with
  `make test:go PKG=./internal/ui ARGS=-update` and review the diff.
- Nothing runs rift or Docker. `internal/rift/testdata/fake-rift` stands in for
  rift, printing the contract fixture, and `ui.Backend` lets tests record what
  the dashboard asks for.
- One test in `internal/app` runs the whole program on that fake rift, to prove
  the parts work together.
- CI also runs the tests with Go's race detector, which needs cgo and so is left
  out of the Alpine development image.

`make test:coverage` writes its Go HTML report to `dash/coverage.html`.
