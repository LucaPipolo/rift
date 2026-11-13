---
paths:
  - "README.md"
  - "CONTRIBUTING.md"
  - "SECURITY.md"
---

# Human docs

`README.md`, `CONTRIBUTING.md` and `SECURITY.md` are for people: someone deciding whether to use Rift, installing
and configuring it, or working on its code. They are not the place for agent instructions or anything that belongs in
`CLAUDE.md` or `.claude/`; those serve a different reader, and the two sets of documents are meant to drift apart,
not mirror each other.

## What each covers

`README.md` is for Rift's users, in its current order: what Rift is, `Why use Rift?`, `Install`, `Get started` as
numbered steps, `Everyday usage`, `Integrate with an agent development environment`,
`Configuration and advanced usage` (each topic in a collapsed `<details>` block), `Need help?`, `Contributing`,
and `Licence`.

`CONTRIBUTING.md` is for people working on Rift itself: getting started, development commands, trying a change,
project structure, code conventions, tests, commit messages, pull requests, AI tooling, releasing, and reporting
bugs.

`README.md` never lists what `CONTRIBUTING.md` contains, and a detail only someone working on the code needs goes in
`CONTRIBUTING.md`, never back in `README.md`.

## Voice

Plain facts in second person, addressed to the reader: no invented colour, no marketing adjectives ("powerful",
"seamless", "blazing fast"), no emoji. Explain the why behind a constraint when it saves the reader from guessing.
Verified pattern, from `CONTRIBUTING.md`:

> Rift drives the host's Docker, Git, ports and keychain, so most commands only make sense on your machine, not
> inside the development container.

Never use an em dash (—) or an en dash (–). Rewrite the sentence instead.

## Structure

- Both files open with a `#` title (`# Rift`, `# Contributing to Rift`). In `README.md`, the CI status badges, one
  per workflow, plus the Homebrew and sponsor badges, sit directly under it.
- Prose is hard-wrapped at 80 columns. Tables and long link lines may run past it.
- Links are reference-style (`[Docker Compose]`, `[Git worktree][worktrees]`), never inline, with the
  definitions at the end of the file: sorted alphabetically in `CONTRIBUTING.md`, and in `README.md` the links
  first, then the badge images and the workflow links they point to. Every mention of a technology or tool links to its
  official site the first time it appears. A wrapped line must never start with `[name]:`, or Markdown reads it as
  a definition.
- A sequence a reader follows once, such as install or release, is a numbered list. A reference a reader scans, such
  as which command does what, is a table.
- Every command, filename, path and `rift.yaml` key is inline code, and copy-pasteable as written. A multi-line
  example is a fenced code block with a language tag (`bash`, `yaml`, `text`).

## What stays out

- No comment inside a code block explaining what the line above it already shows.
- No exhaustive option reference that duplicates what `rift init`'s commented `rift.yaml` already documents.
- Nothing that only matters to an AI agent working in this repo. That belongs in `CLAUDE.md` instead.
- No version numbers in prose, except where a reader needs one (the PHP runtime when updating it, a release tag in
  an example).
