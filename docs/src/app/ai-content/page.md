---
title: AI content and assistance
nextjs:
  metadata:
    title: AI content and assistance
    description: Which artificial intelligence tools are used to write Rift and this documentation. The decisions and the responsibility stay with the maintainer.
---

Rift and this documentation are written with the assistance of artificial intelligence tools. This page explains which tools are used, how, and what stays entirely the maintainer's: the ideas, the decisions, and the responsibility for what you read. {% .lead %}

Last updated: 14 November 2026.

---

## Which tools are used

- **Claude and Claude Code**, from [Anthropic](https://www.anthropic.com/): mainly the Claude Opus and Claude Sonnet models, for writing code and documentation.
- **Codex**, from [OpenAI](https://openai.com/): for reviewing pull requests.

---

## How a page of this documentation comes about

The documentation is drafted with Claude from Rift's own source: its commands, its configuration and its repository's guides. The draft is then checked against the code, so that every command, option and behaviour it describes matches what Rift actually does. It is reviewed and corrected by the maintainer before it is published.

---

## What stays the maintainer's

The design of Rift, the structure of this documentation, the editorial and technical decisions, the final review and the responsibility for what is published always remain with the maintainer, Luca Pipolo. The tools are used the way an editor or a pair programmer would be, not as a substitute for human judgement.

---

## What the law says

Article 50(4) of [Regulation (EU) 2024/1689 (the AI Act)](https://eur-lex.europa.eu/legal-content/EN/ALL/?uri=CELEX:32024R1689) requires disclosing text that has been generated or manipulated by AI and published to inform the public on matters of public interest. It exempts text that has undergone human review and for which a person holds editorial responsibility, which is the process described above. So this disclosure isn't required by law; it is published anyway, by choice.

---

## Code written with AI

Rift's code is also written with AI agents, often several in parallel, which is what Rift is built for. The [AI tooling](/docs/ai-tooling) page describes the instructions, skills and MCP servers the repository uses, and how commits made with AI assistance credit the model that did the work.
