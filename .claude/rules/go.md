# Go conventions

These apply to `dash/`, rift-dash. They mirror the PHP rules: same bar, Go idioms.

## Code

- Doc comments go where PHP has docblocks: every package, type, function and method, unexported ones and test
  helpers too. They follow Go's own convention (https://go.dev/doc/comment), not PHP's: a full sentence that starts
  with the declared name (`// Quote returns ...`), because `go doc`, gopls and revive expect it. Go has no
  `@param`/`@return`; name parameters and results in the prose, as `io.Copy` does, only when the signature does not
  already say them.
- Constants, variables and struct fields get no comment when their name says what they are, as PHP constants don't.
  Comment one only for a why the name cannot carry, such as a unit, a constraint or the reason for its value.
- Comments follow `comments.md`: why, not what, inline ones two lines at most, decisions as "We decided … because …".
- `make format` decides formatting (gofumpt, goimports with the module as local prefix); `make lint` and `make sniff`
  must pass, golangci-lint reporting 0 issues. Never add `//nolint` without a reason after it.
- Keep rift's rules in PHP. If rift-dash needs something rift knows, add it to `SnapshotJson` (and the fixture), never
  derive it in Go.
- Anything that touches the machine goes through `ui.Backend` or a function a test can replace; `ui` never imports
  `system` or `os/exec` for anything but handing the terminal to a foreground task.
- Errors a person reads are full sentences naming the fix, as in PHP.

## Tests

- 100% coverage of `internal/`, checked by `make test:coverage`. A line no test can reach is removed, not excluded.
- One `_test.go` beside each file, tests named after the behaviour: `TestDownAsksThenStops`.
- Screens are compared as plain text against `internal/ui/testdata/*.golden`; after a deliberate change, rewrite them
  with `make test:go PKG=./internal/ui ARGS=-update` and read the diff before committing.
- Nothing runs rift or Docker: use `internal/rift/testdata/fake-rift` and the recording backend in
  `internal/ui/helpers_test.go`. Real processes in tests are limited to harmless ones (`sh`, `true`, `git` in a temp
  directory).
- Before trusting a new test, see it fail once.
