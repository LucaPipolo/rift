---
paths:
  - "app/**"
  - "tests/**"
  - "phpunit.xml.dist"
---

# Tests

Tests are written with [Pest](https://pestphp.com/) and cover 100% of `app/`, in lines and in types.

## Where they go

One test file per class, mirroring `app/`: `app/Env/PortResolver.php` is tested in
`tests/Unit/Env/PortResolverTest.php`. Commands, and classes that use a Laravel facade, go in `tests/Feature` (which
boots the application through `Tests\TestCase`); every other class goes in `tests/Unit`.

Shared helpers live in `tests/Helpers/*.php` as plain functions with full docblocks, loaded by `tests/Pest.php`. Look
there before writing setup by hand: `project()`, `host()`, `artisan()` and `answerPrompts()` for commands, `myapp()`,
`probe()`, `fakeNetwork()` and `caddy()` for building instances and fakes. A helper used by one file stays in that file.

## Coverage

`make test:coverage` requires 100% line coverage, and `make test:types` 100% type coverage. A new class lands with
its tests in the same commit, or the commit is not atomic.

Never use `@codeCoverageIgnore`. When a line can't be reached by any input, it is dead code: remove it in its own
`refactor:` commit ahead of the tests. When it can be reached but only through the real machine, it needs a seam (a
facade call or a contract, see the PHP rules), not an ignore comment.

## Structure

- Group a class's tests in one `describe('method()')` per public method, and a command's tests in one
  `describe('when …')` per scenario.
- Each test checks one behaviour. When a scenario has several effects, give each its own `it()` and share the setup
  in a `beforeEach`.
- Name the outcome and the condition, as a plain sentence about behaviour:
  `it('returns null for a key with an empty value')`, `it('exits with failure when Docker is not running')`.
- Use datasets (`->with([...])`) for the same behaviour across several inputs.
- Expected exceptions use `->throws(ConfigException::class, 'the exact message')`, so the message, which tells the
  user what to do, is tested too.

## What a test does

- Test what the code promises, as its docblock states it: the edge cases it names are the tests to write.
- Nothing reaches the real machine. Fake processes with `Process::fake()`, Caddy's admin API with `Http::fake()`,
  waits with `Sleep::fake()`, and host questions with the contract fakes in `tests/Pest.php`. Files go in a
  `scratch()` directory, never the repository or the real home directory.
- A feature test that runs a process with no matching fake fails, because `tests/Pest.php` prevents stray processes.
  Add the fake. Only a test that needs real output a fake can't produce, such as streamed output, opts out with
  `Process::preventStrayProcesses(false)` and a comment above its `it()` saying why.
- Fake only what the test needs to control. A fake that answers everything hides which call the behaviour depends on.
- Before trusting a new test, see it fail: break the code or the expectation once. A test that passes for the wrong
  reason, such as a fake that matches a command the code never runs, hides the gap it was meant to close.

## Running them

```bash
make test
make test:unit ARGS="--filter=PortResolver"
make test:coverage
```

CI runs the whole suite with coverage, Larastan and type coverage on every push and pull request.
