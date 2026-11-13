---
name: update-dependencies
description: >-
  Updates this repo's Composer and Bun dependencies, one per commit, working through them in a fixed order:
  Composer production before Composer development before Bun, and within each group, a foundational package before
  what depends on it.
when_to_use: >-
  Use when asked to update dependencies, upgrade packages, or run a dependency update pass. Only reachable via
  the /update-dependencies slash command; never invoke this on your own.
disable-model-invocation: true
context: fork
model: sonnet
---

# Updating dependencies

Updates dependencies one at a time, each in its own commit. Never batch multiple dependency updates into a
single commit.

This project runs Composer and Bun through `make`, never on the host (see `.claude/guidelines/dev-env.md`). Put `--`
before any option, so Make passes it on: `make composer -- update symfony/yaml --with-dependencies`.

## Preflight (mandatory)

Before updating anything, confirm the starting point is green. A dependency update is only meaningful if a
failure afterwards can be attributed to it.

```bash
git status --short
make lint
make sniff
make test
make test:coverage
make build
```

- `git status --short` must be empty. If the working tree is dirty, stop and report; never mix an update with
  unrelated changes.
- If any check already fails, stop and report which one. Do not begin the update pass, and do not "fix" the
  pre-existing failure as part of it; that is a separate task with its own commit.

## Branch

Once preflight is green, create and switch to a dated branch off `main`:

```bash
git checkout -b "update/$(date +%F)" main
```

`date +%F` gives `YYYY-MM-DD`, e.g. `update/2026-08-14`. If that branch already exists, an update pass already
ran today; check it out instead of creating a new one (`git checkout "update/$(date +%F)"`) and continue the
queue from there rather than starting over.

## Update order (mandatory)

Before updating anything, build an ordered queue of the outdated packages:

1. **Composer `require` before `require-dev`, then Bun.** Production packages end up in the binary; development
   ones don't. Bun only carries the commit tooling, so it goes last.
2. **General before specific.** Within each group, a foundational package is updated before the packages that
   extend, configure, or plug into it.

Ecosystem groups in this project, in the order they must be updated:

| Group                  | First                     | Then                                                               |
| ---------------------- | ------------------------- | ------------------------------------------------------------------ |
| Composer `require`     | `laravel-zero/foundation` | `laravel-zero/framework`, which requires it                        |
| Composer `require-dev` | `pestphp/pest`            | `pestphp/pest-plugin-phpstan`, `pestphp/pest-plugin-type-coverage` |
| Bun                    | `@commitlint/cli`         | `@commitlint/config-conventional`                                  |

Everything else (`illuminate/http`, `symfony/yaml`, `larastan/larastan`, `laravel/pint`, `mockery/mockery`, `husky`) has no dependent
in this list; update it in its group's position, in whatever order the outdated listing gives it. `php` in
`composer.json` is not a dependency update: moving it is a separate task (`CONTRIBUTING.md`, Updating PHP).

## The update pass

1. List outdated direct dependencies:

   ```bash
   make composer -- outdated --direct
   make bun outdated
   ```

   Split the Composer results into `require` and `require-dev` by reading `composer.json`, then sort each group
   with the rules above. That ordered list is the queue.

2. For each package in the queue, one at a time:

   - A Composer update within the existing constraint (marked `!` by `outdated`):

     ```bash
     make composer -- update {package} --with-dependencies
     ```

     `--with-dependencies` lets the package's own transitive dependencies move with it, but no other direct
     dependency. If Composer refuses because another direct dependency's constraint pins it, report it rather than
     widening that constraint.

   - A Composer major (marked `~`), which crosses the constraint:

     ```bash
     make composer -- require {package} '^{new major}' --with-dependencies
     ```

     Add `--dev` for a `require-dev` package, so it stays in `require-dev`. Pass the constraint as its own argument:
     the usual `{package}:^{major}` form breaks Make, which reads the colon as a target pattern.

   - A Bun package:

     ```bash
     make bun -- update {package} --latest
     ```

   Before crossing a major on `laravel-zero/framework`, `laravel-zero/foundation`, `illuminate/http`,
   `symfony/yaml`, `pestphp/pest` or `larastan/larastan`, check the package's own changelog or upgrade guide for
   breaking changes (fetch it, or search for it). A major bump that needs code changes here is not a dependency
   update: stop and report it so it can be planned as its own task.

3. Verify, in this order:

   ```bash
   make format
   make lint
   make sniff
   make test
   make test:coverage
   make build
   ```

   - `make format` may rewrite files, mostly after a `laravel/pint` update. That's expected, and those changes
     belong in this dependency's commit.
   - `make build` is only needed for a `require` package: `docker/build.sh` builds the PHAR with production
     dependencies only, so a development package cannot change the binary. A production package can break the PHAR
     while every test still passes.
   - A Bun package only needs one check: commitlint still runs and still enforces the config. Pipe a valid and an
     invalid message through it, as the `git-commit` skill does, and confirm one passes and the other fails.
   - If any check fails, stop. Do not commit, and do not continue to the next package. Report the failing
     package and the output. Never work around a failure by loosening a type, raising a Larastan baseline,
     disabling a rule, or pinning back silently.

4. Stage only what this dependency touched:

   ```bash
   git add composer.json composer.lock   # or: git add package.json bun.lock
   ```

   `composer.json` only changes on a major. If step 3 reformatted tracked files, stage them into this same commit
   too. Then hand off to the `git-commit` skill to write and make the commit; it already knows this repo's
   dependency-commit shape (`build: update dependency {name} to {version}`, the resolved version from the lockfile,
   never the constraint). Never push.

5. Move to the next package and repeat steps 2 to 4.

## Transitive security fixes

Once the queue is done, audit the whole lockfile. Updating direct dependencies only moves a transitive package
when it has to, so a vulnerable `symfony/*` package can stay locked underneath a framework update.

```bash
make composer -- audit
make bun audit
```

`composer audit` exits non-zero whenever it finds an advisory, so Make reports `Error 1`: that is the finding, not a
failed command. Read the table above it. Composer has no automatic fix. For every advisory on a package that isn't a direct dependency, update just those
packages, all in one command, within their parents' constraints:

```bash
make composer -- update {package} {package} ...
```

Verify exactly as in step 3, `make build` included, then run the audit again. Stage `composer.lock` and hand off to
`git-commit` with the subject `build: update vulnerable transitive dependencies`, and a body listing each package
as `{name} {old version} → {new version}`, read from the `composer.lock` diff.

This is the one exception to one dependency per commit: Composer resolves these fixes together, and splitting them
would mean assembling lockfiles by hand that Composer never wrote.

An advisory still listed after that is blocked: either no patched release exists yet, or a parent's constraint
holds the package back. It cannot be fixed here. Never force it with an inline alias, a `conflict` entry or a new
direct requirement; report it.

An advisory on a direct dependency is fixed by that package's own update in the queue, not here.

## Boundaries

- If an update reports no change for a package, skip it. Never create an empty commit.
- Adding a dependency that isn't installed yet is a different task (`build: add dependency {name} {version}`),
  not this one.
- Do not squash, reorder, or amend a prior dependency commit; each stays an isolated, individually revertible
  unit (`.claude/guidelines/git.md`).
- Never push at the end of a pass. Pushing needs explicit confirmation.

## When finished

Report a summary: which packages were updated, which were skipped and why, which were deferred as breaking
majors, which transitive packages were fixed, and which advisories remain and what blocks each one.

`open-pr` builds the `## Deferred` and `## Open advisories` sections of its PR body from this summary and has no
other source for them (nothing was committed for a deferred package or an open advisory), and only within the same
session. So for each deferred package, the summary must name the package, its old and new version numbers (the
resolved versions, never the constraint), and a one-line reason it was deferred; and for each open advisory, the
package, its locked version, the advisory title, and what blocks the fix.
