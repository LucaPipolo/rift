---
paths:
  - "app/**/*.php"
  - "bootstrap/**/*.php"
  - "config/**/*.php"
  - "tests/**/*.php"
---

# PHP conventions

## Types and classes

- Every file declares `strict_types`.
- Every parameter, return value, property and constant has a type, and the code passes Larastan at level max with
  100% Pest type coverage. Narrow `mixed` from YAML or JSON with real checks, not a `@var` cast or a baseline entry.
- Classes are `final` by default, and `final readonly` when they hold no mutable state, with constructor property
  promotion: `final readonly class PortResolver { public function __construct(private PortProbe $probe) {} }`.
- Value objects with several ways to build them use named static constructors and a private constructor, as
  `Support/Outcome` does (`Outcome::done()`, `Outcome::skipped()`, `Outcome::failed()`).
- Pass named arguments where a call would otherwise be a row of unexplained literals or booleans:
  `new self('✗', 'red', $note, fatal: true)`.

## Dependencies

- Commands receive their dependencies through `handle()` method injection, not the constructor and not
  `app()`/`resolve()`. Bindings live in `AppServiceProvider`.
- Anything that asks the host a question goes through a `Process`, `Http` or `Sleep` facade call, or a contract in
  `app/Contracts/` bound in `AppServiceProvider`. Never call `exec()`, `shell_exec()`, `fsockopen()` or similar
  directly: tests could not fake it, and coverage would require touching the real machine.

## Errors

A problem the user can fix throws one of the exceptions in `app/Exceptions/`, never a bare `RuntimeException`. The
message names what is wrong and, when there is one, the exact command or edit that fixes it. Verified pattern, from
`app/Config/ProjectConfig.php`:

```php
throw new ConfigException(
    'No ' . self::FILENAME . ' in this checkout. Run `rift init` to create one.'
);
```

Config validation messages lead with where the problem is and quote the key in backticks:
`"{$context}: every hook needs a non-empty `run`."`

## Output

Every line printed goes through `App\Support\Ui`, or in a command through the `RendersSteps` helpers (`step()`,
`note()`, `notice()`, `success()`) and `RiftCommand::bail()`. Never `echo`, `$this->info()` or `$this->line()` directly: the step list, the
`-v` log and the colours all depend on output going through one place.

## Formatting

Pint, configured in `pint.json`, decides formatting and class element order. Run `make format` rather than
hand-arranging code.
