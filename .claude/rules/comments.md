---
paths:
  - "app/**/*.php"
  - "bootstrap/**/*.php"
  - "config/**/*.php"
  - "tests/**/*.php"
---

# Comments

A comment explains code from a bird's-eye view: intent, and why, never a step-by-step restatement of what the
next line already says. If a comment could be deleted after reading the code once, delete it. Never add one
indiscriminately, on every block out of habit; most lines need none. Keep the ones that remain short: a comment that
goes deep into implementation detail has usually drifted into restating the code.

## Classes

Every class, interface, trait and enum gets a one-line docblock saying what it is or does, as a noun phrase or in
the third person. Verified patterns:

```php
/**
 * One git checkout of a project: the main clone or a worktree beside it.
 */
final readonly class Checkout
```

```php
/**
 * Picks host ports no other checkout has taken.
 */
final readonly class PortResolver
```

## Methods and functions

Every method and every test helper function gets a docblock, including private ones and those with typed
signatures. The title is imperative, never third person: `Resolve`, `Create`, `Get`, `Determine whether`, `Render`,
`Run`. Constructors read `Create a new {thing} instance.` Every `@param` is listed, aligned with two spaces around
the type and the name, with a short description; `@return` has the type and a short description; `@throws` names the
exception class alone. A blank line separates each group. Verified pattern, from `app/Env/PortResolver.php`:

```php
/**
 * Resolve a port, keeping the current one when it is still free.
 *
 * @param  int|null  $current  The port already in .env, if any.
 * @param  int  $default  The port to start scanning from.
 * @param  string  $composeProject  The checkout's Compose project name.
 * @param  list<int>  $reserved  Ports sibling worktrees have claimed but may not be serving yet.
 *
 * @return int The free port.
 *
 * @throws ConfigException
 */
```

A one-line title is enough when it already says everything worth knowing; add a description between the title and
the tags only when the title alone leaves something genuinely unclear. Verified pattern, from the same file:

```php
/**
 * Determine whether a port is free for a Compose project.
 *
 * A port the project's own containers publish counts as free, since it is
 * about to be handed back to them.
 *
 * ...
 */
```

Use the precise array shapes PHPStan understands (`list<int>`, `array<string, string>`,
`list<array{method: string, path: string, body: mixed}>`), never a bare `array`.

## Inline comments

Outside a docblock, a `//` comment is for the rare line where the code alone doesn't explain itself: a
non-obvious constraint, a workaround, an edge case a reader would otherwise trip over. Two lines at most, and it
explains why, not what the next line does. Verified pattern, from `app/Env/PortResolver.php`:

```php
// A worktree's .env is seeded from the main checkout's, so its current
// port is often one a sibling already claimed.
```

In tests, the same kind of comment sits above an `it()` whose reason for existing isn't obvious from its name.
Verified pattern, from `tests/Unit/Env/PortResolverTest.php`:

```php
// Otherwise a plain re-run would march every worktree up a port each time,
// because its own containers are still holding the port it wants back.
it('keeps a current port the checkout itself publishes', function (): void {
```

If code exists because of something found online, an issue, a changelog entry, Docker or Caddy behaviour, link it
with `@see` rather than re-explaining the reasoning inline:

```php
// @see https://github.com/caddyserver/caddy/issues/1234
```

## Architectural comments

A comment about a design decision rather than one method follows the same bar as everywhere else: bird's-eye,
short, only where the code genuinely can't explain itself. State the decision first, then why it was made: "We
decided {choice} because {reason}," followed by further context only if the reason alone leaves something
unclear:

```php
/**
 * We decided to match the CA by fingerprint rather than by name because every
 * Caddy CA shares the same name, so a name match would accept another
 * machine's or an older router's certificate.
 */
```

## In short

Not too many inline comments. Docblocks everywhere, short, in plain language. The ones that exist earn their place.
