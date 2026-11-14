---
title: Code conventions
nextjs:
  metadata:
    title: Code conventions
    description: The conventions Rift's PHP and Go code follow, from docblocks and formatting to static analysis.
---

Both languages are held to the same bar: every declaration documented, one
formatter, static analysis with no exceptions, and 100% test coverage.
{% .lead %}

---

## PHP

- Every PHP file declares `strict_types`.
- Every method docblock opens with a verb, describes each `@param` and the
  `@return` in a few words, and adds a description only when the code cannot say
  it:

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

- Formatting follows the [Pint](https://laravel.com/docs/pint) rules in
  `pint.json`. Run `make format` before committing.
- Code passes [Larastan](https://github.com/larastan/larastan) at the max level,
  and every parameter, return value, property and constant has a type.

---

## Go

- Every package, type, function and method has a doc comment saying what it is
  or does, in the third person, as Go's own documentation does:
  `// Queue runs chains of tasks in order.` Constants and variables whose name
  says what they are get none, as in the PHP code.
- Comments explain why, not what, as in the PHP code.
- Formatting follows `gofumpt` and `goimports`, and
  [golangci-lint](https://golangci-lint.run/) checks the rest, as configured in
  `dash/.golangci.yml`. `make format` fixes the formatting of both languages.
- Anything that touches the machine goes through `ui.Backend` or a function a
  test can replace, so no test runs rift or Docker.

---

## The documentation site

- Pages follow the same voice as the rest of Rift's docs: plain facts in the
  second person, no marketing adjectives, and no em or en dashes.
- Every command, filename, path and `rift.yaml` key is inline code, and
  copy-pasteable as written.
- Formatting follows the Prettier rules in `docs/prettier.config.js`. Run
  `make docs:format` before committing.
