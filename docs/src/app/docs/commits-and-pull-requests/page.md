---
title: Commits and pull requests
nextjs:
  metadata:
    title: Commits and pull requests
    description: How to write Rift's commit messages, name branches, and open a pull request that passes every check.
---

Commit messages follow Conventional Commits, and every pull request runs the
same checks as `make`. {% .lead %}

---

## Commit messages

Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/),
checked by [commitlint](https://commitlint.js.org/):

```text
<type>: <description>
```

| Type       | Use it for                                                 |
| ---------- | ---------------------------------------------------------- |
| `feat`     | A new feature for Rift's users.                            |
| `fix`      | A bug fix for Rift's users.                                |
| `perf`     | A change that makes Rift faster.                           |
| `refactor` | A code change that neither adds a feature nor fixes a bug. |
| `test`     | Adding or changing tests.                                  |
| `docs`     | Documentation only.                                        |
| `style`    | Formatting only, with no change in behaviour.              |
| `build`    | The build, the binaries or the dependencies.               |
| `ci`       | The GitHub workflows.                                      |
| `chore`    | Anything else that does not touch `app/`.                  |
| `revert`   | Reverting an earlier commit.                               |
| `ai`       | AI agent configuration, such as prompts or skills.         |

Rift adds `ai` to the standard types, and does not use scopes: write
`feat: add trust command`, not `feat(router): add trust command`.

### Breaking changes

Mark a breaking change with `!` after the type, and explain it in a
`BREAKING CHANGE:` footer:

```text
feat!: rename the hosts key to aliases

BREAKING CHANGE: rift.yaml uses `aliases` instead of `hosts`.
```

### The commit hook

A [Husky](https://typicode.github.io/husky/) `commit-msg` hook checks each
message in the `rift-bun` container, so Docker must be running when you commit.
In a worktree, run `make bun install` there first.

Because the hook can be skipped with `--no-verify`, the Commitlint workflow
checks every commit in a pull request as well.

---

## Opening a pull request

1. Branch off `main`, naming the branch after the type of its main change, such
   as `feat/trust-command` or `fix/detect-host-port-holders`.
2. Make your change in commits that each pass the checks below on their own,
   with tests for the behaviour you add or change.
3. Update the docs when a command, option or `rift.yaml` key changes: `README.md`
   and the pages in `docs/`. Run `make demo` when the dashboard looks different.
4. Run the same checks as CI:

   ```bash
   make lint
   make sniff
   make test
   make test:coverage
   ```

   When you changed the docs site, run `make docs:lint` and `make docs:build`
   too.

5. Push the branch and open a pull request, for example with the
   [GitHub CLI](https://cli.github.com/):

   ```bash
   gh pr create --title "feat: add trust command" --body-file pr.md
   ```

### Title and description

Give the pull request a Conventional Commit title too, summarizing the whole
branch: when a pull request is squash-merged, its title becomes the commit
message.

The description has exactly two sections:

```markdown
## Context

- Adds a `<domain> resolves to <address>` check to `doctor`'s project section.

## Verification Plan

1. Run `make build`, then `builds/rift doctor` in a project whose domain does
   not resolve, and confirm the check fails with the fix underneath.
```

- **Context** lists what changed, one bullet per piece.
- **Verification Plan** lists the steps a reviewer takes to see it working, with
  the exact commands and the output to expect. CI already runs the checks, so
  they are not steps.

---

## Checks on every pull request

Every pull request runs these workflows, each one job that checks PHP and Go
like its `make` target. All of them must pass before a merge:

| Workflow   | What it checks                                                                                                                                         |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Tests      | Both test suites at 100% coverage, and type coverage, as `make test:coverage` and `make test:types` do. The Go tests also run under the race detector. |
| Lint       | Formatting, as `make lint` does.                                                                                                                       |
| Sniff      | Static analysis, as `make sniff` does.                                                                                                                 |
| Build      | The four release binaries, built as the release workflow builds them.                                                                                  |
| Commitlint | Every commit message in the pull request.                                                                                                              |

The Tests workflow comments on the pull request with how PHP coverage changed
from the base branch, and uploads both HTML reports as the `coverage-html`
artifact. Pull requests from forks and Dependabot get the artifact but no
comment.
