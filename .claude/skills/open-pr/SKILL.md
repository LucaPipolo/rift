---
name: open-pr
description: >-
  Opens a GitHub pull request for the current branch, generating the title and body from its commits and diff to
  match this repo's exact PR template (a Context bullet list plus a numbered Verification Plan).
when_to_use: >-
  Once the current branch's commits are pushed and ready for review. Only reachable via the /open-pr slash
  command; never invoke this on your own.
disable-model-invocation: true
model: sonnet
allowed-tools: Bash(git status *) Bash(git log *) Bash(git diff *) Bash(git show *) Bash(gh pr list *)
---

# Opening a PR

## 1. Check the branch is pushed

- Status: !`git status -sb`

If the branch has no upstream, or has local commits the remote doesn't have yet, stop and ask the user to push
before continuing. Never push, and never force-push, on their behalf; that stays a manual, confirmed step even
though this skill itself was invoked manually.

## 2. Pick the base

- Open PRs: !`gh pr list --state open --json number,headRefName --jq '.[] | "#\(.number) \(.headRefName)"'`

The base is `main`, unless this branch is stacked on another open PR's branch: its commits from `main` then start
with that branch's commits. In that case, use that branch as the base so the PR shows only its own commits, and
confirm the choice with the user before opening.

## 3. Read what the branch actually did

- Commits: !`git log main..HEAD --oneline`
- Diff stat: !`git diff main...HEAD --stat`

Use the chosen base in place of `main` when it differs. Read the diff itself for anything the commit subjects don't
make obvious.

## 4. Detect a dependency-update branch

If the branch name matches `update/YYYY-MM-DD` (the `update-dependencies` skill's naming), this is a
dependency-update PR: use the title and body in step 4a below instead of writing them from scratch, then skip
straight to step 7. Otherwise continue to step 5, whatever the commits look like.

### 4a. Dependency-update title and body

**Title:** `build: run {date} dependency updates`, where `{date}` is the `YYYY-MM-DD` from the branch name (e.g.
`build: run 2026-08-14 dependency updates`).

**Body:**

```
## Updated

| Package | From | To |
|---|---|---|
| `{name}` | {old version} | {new version} |
...
```

One row per commit, in commit order. For each commit, read its lockfile diff to get the exact old and new version
numbers for that package, never the constraint: `git show {sha} -- composer.lock` for a Composer package (the
`"version"` line under its `"name"`), `git show {sha} -- bun.lock` for a Bun one.

The `build: update vulnerable transitive dependencies` commit changes only `composer.lock` (or `bun.lock`) for
packages that aren't direct dependencies: give it one row per package its body lists, with the versions from that
body.

A deferred package (a major bump needing code changes, reported instead of committed; see
`update-dependencies`'s Boundaries) leaves no trace in git: nothing was committed for it, so it appears in no
commit and no diff. Its only source is the `update-dependencies` summary from an update pass run earlier in
this same session. Take each package's name, versions, and reason from that summary; never infer a deferral
from the commits or the diff, and never guess one. The same goes for advisories the summary reports as still open.

If that summary isn't in this session's context (`/open-pr` run in a fresh session, or on a branch someone
else pushed), omit those sections entirely and say nothing about them. That is the correct outcome, not a
failure; there is no persisted record to go looking for.

When the summary does report a deferral, append:

```

## Deferred

- `{name}` {old version} → {new version}: {one-line reason it was deferred}
```

When it reports advisories still open, append:

```

## Open advisories

- `{name}` {locked version}: {advisory title}, blocked by {what blocks the fix}
```

One bullet per package the summary names, in the order it names them.

Skip steps 5 and 6 below and open the PR with this title and body.

## 5. Write the title

A Conventional Commit subject summarizing the branch as a whole rather than restating one commit, following the
same rules as a commit subject (see the `git-commit` skill): the type of the branch's main change, no scope, lower
case, imperative, no trailing full stop. Real titles from this repo: `feat: check DNS and the router bind address
in doctor`, `fix: detect host processes holding the router ports`, `ci: reject release tags without the v prefix`.
When a PR is squash-merged its title becomes the commit message on `main`, so it must pass commitlint.

## 6. Write the body

Exactly this template. Never add a section beyond these two:

```
## Context

- {what changed, one bullet per distinct piece, each a complete sentence starting with a present-tense verb}

## Verification Plan

1. {a concrete, specific step a reviewer would take to confirm the change works}
2. ...

{Closes #N or Refs #N, when the branch works toward an issue}
```

- **Context** bullets describe what was added or changed, not why: "Adds a `Network` contract and its
  `SystemNetwork` implementation, so `doctor` can resolve the project's domain through the system resolver.",
  "Adds a `<domain> resolves to <address>` check to `doctor`'s project section." Reference the real command,
  class, `rift.yaml` key and make target names from the diff, not vague summaries. When the branch is stacked, the
  first bullet names the PR it is stacked on: "Builds on #4, which must be merged first."
- **Verification Plan** steps are concrete and actionable, phrased as instructions with the exact command and the
  output to expect: "Run `make build`, then run `builds/rift doctor` in a project whose domain doesn't resolve, and
  confirm it prints `✗ myapp.test resolves to this machine` with the fix underneath." Cover each command the change
  touches, both the main checkout and a worktree when the change is per-checkout, macOS and Linux when it touches
  the trust store, DNS or networking, and any edge case the diff suggests (Docker not running, a port already taken,
  a missing or invalid `rift.yaml` key). Don't list `make test` or the CI checks; CI already runs them.
- The issue reference ends the body on its own line: `Closes #N` when merging the PR completes the issue, `Refs #N`
  when it only works toward it. In a stack, only the last PR closes the issue, so merging an earlier one can't close
  it too soon. Never link the issue from the PR's Development sidebar instead: that closes it on merge like `Closes`.

## 7. Open it

```bash
gh pr create --base <base> --title "the title" --body "the body"
```
