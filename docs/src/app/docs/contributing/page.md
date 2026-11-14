---
title: Contributing
nextjs:
  metadata:
    title: Contributing
    description: Set up a development environment for Rift. Everything runs in containers, so you only need Docker and GNU Make.
---

Everything you need to work on Rift runs in containers, so you only need
[Docker](https://www.docker.com/) and [GNU Make](https://www.gnu.org/software/make/)
on your machine: no PHP, Composer, Go or Bun. {% .lead %}

---

## Getting started

1. Clone the repository:

   ```bash
   git clone https://github.com/LucaPipolo/rift.git
   cd rift
   ```

2. Install the PHP dependencies, and the commit tooling with its Git hook:

   ```bash
   make composer install
   make bun install
   ```

3. Run the tests:

   ```bash
   make test
   ```

The first `make` command builds the images it needs:

| Image      | What it holds                                                                                   |
| ---------- | ----------------------------------------------------------------------------------------------- |
| `rift-dev` | PHP 8.5, [Composer](https://getcomposer.org/), Git and [PCOV](https://github.com/krakjoe/pcov). |
| `rift-bun` | [Bun](https://bun.sh/) and Git, for the commit tooling and this documentation site.             |
| `rift-go`  | [Go](https://go.dev/), [golangci-lint](https://golangci-lint.run/) and Git.                     |

`make bun install` also installs the Git hook that checks commit messages.

Containers start for a single command and are removed when it finishes, so
there is nothing to keep running: `docker compose up` is never needed. They run
as your user, so files they create belong to you.

---

## Where to go next

{% quick-links %}

{% quick-link title="How Rift is built" icon="lightbulb" href="/docs/how-rift-is-built" description="The two programs in every binary, and the contract between them." /%}

{% quick-link title="Development commands" icon="installation" href="/docs/development-commands" description="Every make target, for tests, analysis, formatting, builds and this site." /%}

{% quick-link title="Tests" icon="presets" href="/docs/tests" description="How the PHP and Go suites are written, and the 100% coverage they keep." /%}

{% quick-link title="Commits and pull requests" icon="plugins" href="/docs/commits-and-pull-requests" description="Commit messages, branch names, and what a pull request needs to merge." /%}

{% /quick-links %}
