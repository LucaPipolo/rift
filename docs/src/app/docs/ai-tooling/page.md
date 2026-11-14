---
title: AI tooling
nextjs:
  metadata:
    title: AI tooling
    description: How Rift is developed with AI agents, how AI-assisted commits are recorded, and the skills and MCP servers the project configures.
---

Rift is developed with AI agents, often several in parallel, each in its own
worktree. {% .lead %}

Their instructions for [Claude Code](https://claude.com/product/claude-code)
live in `CLAUDE.md` and `.claude/`: guidelines for the architecture, Git and
docs, rules for PHP, Go, comments, tests and docs that load for the files they
cover, and skills.

---

## Recording AI-assisted work

Commits made with AI assistance end with a trailer naming the tool and the model
that did the work, not one that only wrote the commit message:

```text
Co-Authored-By: Claude Code <noreply@anthropic.com>
Model: Opus (claude-opus-5-5)
```

---

## Agent skills

| Skill                  | What it does                                                                                                                                         |
| ---------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| `git-commit`           | Writes and checks the message for staged changes, used by agents.                                                                                    |
| `/open-pr`             | Opens a pull request with the title and description described in [Commits and pull requests](/docs/commits-and-pull-requests#title-and-description). |
| `/address-pr-comments` | Works through open review feedback on a pull request, from [Codex](https://openai.com/codex/) or any reviewer.                                       |
| `/update-dependencies` | Updates Composer and Bun dependencies, one commit per package.                                                                                       |

The slash commands are only run by hand: their skills set
`disable-model-invocation: true`, so an agent never starts one on its own.

---

## MCP servers

`.mcp.json` configures the [Model Context Protocol](https://modelcontextprotocol.io/)
servers for this project, so every worktree gets the same ones:

| Server                            | What it is for                                                       |
| --------------------------------- | -------------------------------------------------------------------- |
| [Context7](https://context7.com/) | Current documentation for Laravel Zero, Pest, Larastan and the rest. |
