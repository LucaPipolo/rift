# Git

Never commit directly to `main` unless the user says so explicitly for that task. Branches are named
`<type>/<short-description>` after the Conventional Commit type of their main change: `feat/doctor-network-checks`,
`fix/detect-host-port-holders`, `ci/release-tag-check`.

**This overrides the default habit of asking before every commit.** While executing an approved task, commit
each atomic unit of work as it is completed, with no confirmation checkpoint per commit. This applies to
committing only: pushing, force-pushing, amending, resetting, rebasing, and every other destructive or
shared-state operation still need explicit confirmation.

A commit is atomic when checking it out alone leaves the project in a working state and it stands for one complete,
self-contained change: never a whole feature bundled into one commit, never split so finely that a commit carries no
value on its own. Working state means what CI checks: `make lint`, `make sniff`, `make test` and
`make test:coverage` all pass, so a new class lands with its tests in the same commit, at 100% coverage. Changes that
depend on each other belong in the same commit; unrelated changes belong in separate ones.

AI tooling (`CLAUDE.md`, `.claude/`, `.mcp.json`) is always its own `ai:` commit, never mixed with code or docs.

If a later change genuinely belongs with a commit already made in this task, and that commit has not been
pushed, fix it up with `git commit --fixup=<sha>` instead of folding it into the next commit. Fixup only ever
targets a commit that already exists on the current branch: never a commit reachable only through `main` or
another branch or worktree. Never amend, never rewrite history, never run an interactive autosquash rebase;
squashing is the user's call.

A pull request may land on `main` as its commits or squashed into one, so both must hold up: every commit passes
commitlint and stands on its own, and the pull request title is a Conventional Commit subject, because a squash
merge turns it into the commit message.
