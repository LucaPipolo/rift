---
name: git-commit
description: >-
  Writes, validates, and creates the commit for changes already staged in this repo, following its exact
  commit conventions.
when_to_use: >-
  Reach for this every time changes are staged and ready to commit here, not only when explicitly asked to
  word a message.
user-invocable: false
model: haiku
allowed-tools: Bash(git diff *) Bash(git log *)
---

# Writing a commit

By the time this skill runs, the commit boundary has already been decided: the right files are staged, and they
represent one atomic, self-contained change. This skill does not decide what belongs in the commit or when to
commit; it writes the message, validates it, and makes the commit.

## 1. What's staged

- Staged stat: !`git diff --staged --stat`
- Staged diff: !`git diff --staged`
- Recent history, for subject style: !`git log --oneline -10`

If the staged stat above is empty, there is nothing to commit; stop and report that rather than continuing.

## 2. Pick the type

The type enum is the standard Conventional Commits set, plus one addition configured in `commitlint.config.js`:

- `ai`: AI tooling and configuration. Touches `CLAUDE.md`, `.mcp.json`, or anything under `.claude/`.
- Standard types for everything else. The type decides the next release version (`CONTRIBUTING.md`, Releasing), so
  it describes the change for Rift's users, not the files it touches:
  - `feat`: a new capability for Rift's users: a command, an option, a `rift.yaml` key, a new `doctor` check.
  - `fix`: a bug fix users would notice.
  - `refactor`, `perf`, `test`, `style`: changes inside `app/` or `tests/` with no behaviour change for users.
    A commit that only touches `tests/` is `test`, even when it reworks a helper.
  - `docs`: `README.md`, `CONTRIBUTING.md`, `SECURITY.md`.
  - `build`: dependencies, `docker/`, `box.json`, the binary build, `Formula/rift.rb`.
  - `ci`: `.github/workflows/`.
  - `chore`: anything else that does not touch `app/`.

`ai`, `docs` and code changes never mix in one commit. If the staged diff spans more than one of these categories,
stop and report that instead of picking one and hoping: the commit boundary was drawn wrong upstream of this skill,
and only the caller can fix that.

### Breaking changes

A change that breaks an existing `rift.yaml`, command, option or the router's on-disk state marks the type with `!`
and explains the migration in a `BREAKING CHANGE:` footer, above the trailer:

```
feat!: rename the hosts key to aliases

BREAKING CHANGE: rift.yaml uses `aliases` instead of `hosts`.
```

### Dependency commits

One dependency per commit, never batched, never mixing add/remove/update:

```
build: add dependency {name} {version}
build: remove dependency {name}
build: update dependency {name} to {version}
```

`{version}` is the exact version resolved in `composer.lock` or `bun.lock`, never the constraint in
`composer.json` or `package.json`.

### Tooling setup

`build: setup {tool}` is for wiring in a new tool or dependency: `build: setup Husky`, `build: setup Larastan`. A
"setup" commit that is actually building a capability on top of an already-installed tool is `feat`, not `build`.

## 3. Write the subject

Conventional Commits, no scope, ever: `feat: add trust command`, never `feat(router): add trust command`.
Lower case after the type, imperative mood, no trailing full stop. Aim for roughly 50 characters; real subjects
here run up to ~70 when the subject genuinely needs it (`test: fake only the label query in the outdated router
doctor test`), but don't pad to fill space.

## 4. Decide whether it needs a body

Default to no body. Add one only when the reason behind the change is genuinely not obvious from the diff and
the subject line, such as a workaround for a specific Docker, Caddy or macOS behaviour. Never add a body that just
restates what the diff already shows.

**Always add a trailer identifying the tool and model**, regardless of whether the commit has a body otherwise:

```
Co-Authored-By: Claude Code <noreply@anthropic.com>
Model: <this session's model short name> (<this session's model id>)
```

`Co-Authored-By` always names the harness, `Claude Code`, not the model tier. The `Model` placeholder comes from
the session actually running this commit, e.g. `Opus (claude-opus-5-5)`, never a stale or guessed value, and
never copied from an example in this file. Don't add a reasoning-effort or token-usage field: neither is
reliably available to this skill, and a fabricated or session-cumulative number would misrepresent the commit.
Skip both trailer lines on a `git commit --fixup=<sha>` commit: its message is auto-derived from the target
commit's subject and gets squashed away later, so there's nothing to attribute.

## 5. Validate before committing

Run commitlint the way the `commit-msg` hook does, in the `rift-bun` container. `make bun` can't be used here,
because it doesn't pass standard input through:

```bash
printf 'feat: add trust command\n\nCo-Authored-By: Claude Code <noreply@anthropic.com>\nModel: <model short name> (<model id>)\n' \
  | docker compose --progress quiet run --rm -T --user "$(id -u):$(id -g)" bun bunx --bun commitlint
```

Substitute this session's actual model short name and id for the `Model` placeholder. Run this with the actual
message you drafted, trailer included: `footer-leading-blank` requires the blank line before it, and
`footer-max-line-length` caps each trailer line at 100 characters, which is why the tool and the model are on
separate lines rather than crammed into one. If it's rejected, fix the message and check again rather than
committing anyway.

If Docker isn't running, the command fails outright. Report that clearly rather than trying to route around it:
the hook would refuse the commit for the same reason.

## 6. Commit

```bash
git commit -m "the subject" -m "the body, if there is one" \
  -m "Co-Authored-By: Claude Code <noreply@anthropic.com>
Model: <model short name> (<model id>)"
```

Substitute this session's actual model short name and id for the `Model` placeholder. Each `-m` becomes its own
paragraph, separated by a blank line, so the trailer stays a distinct footer block regardless of whether there's a
body. Omit the trailer entirely on a `--fixup` commit (step 4).

The repo's own `commit-msg` hook runs commitlint again at commit time, for real. It is the only hook: formatting
and tests are not checked at commit time, so the caller is responsible for having run `make lint` and `make test`
before staging. If the hook fails, or isn't installed in this worktree (`make bun install` installs it), report
that rather than committing with `--no-verify`.
