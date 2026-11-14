---
title: Project structure
nextjs:
  metadata:
    title: Project structure
    description: Where everything lives in the Rift repository, from the PHP commands to the Go dashboard and this documentation site.
---

The repository holds both programs, their tests, the build scripts, the
Homebrew formula and this site. {% .lead %}

---

## The repository

```text
app/
├── Checkout/     a Git checkout, and the instance Rift serves it as
├── Commands/     the CLI; router:* lives in Commands/Router
│   └── Concerns/ step rendering and router steps shared by commands
├── Config/       rift.yaml, the global router config and the init template
├── Contracts/    interfaces
├── Dashboard/    the snapshot rift dash shows, as JSON, and finding rift-dash
├── Docker/       the Docker CLI, Compose files and the container runner
├── Enums/        presets, hook stages, and checkout and container states
├── Env/          .env projection, host ports, name resolution and addresses
├── Exceptions/   errors the user can act on
├── Providers/    container bindings
├── Router/       the shared Caddy router, its admin API and its CA
└── Support/      terminal output
dash/             rift-dash, the Go program that draws rift dash
├── cmd/rift-dash/ its main, which only hands over to internal/app
├── internal/      app, the rift client, the snapshot contract, jobs, system and ui
└── testdata/      snapshot.json, the contract fixture both sides are tested against
docker/           the development images and the binary build scripts
docs/             this documentation site
Formula/          the Homebrew formula, as this repository is its own tap
tests/
├── Feature/      commands, and classes that use a Laravel facade
├── Helpers/      shared test helpers, loaded by tests/Pest.php
└── Unit/         plain classes, mirroring app/
```

Commands receive their dependencies through `handle()` method injection.
Bindings live in `AppServiceProvider`.

---

## The documentation site

This site is built with [Next.js](https://nextjs.org/) and
[Markdoc](https://markdoc.dev/), from the Syntax template by
[Tailwind Plus](https://tailwindcss.com/plus). It is a static export: a folder
of HTML files, with no server.

```text
docs/
├── src/app/page.md            the introduction, at /
├── src/app/docs/<slug>/       one page.md per page, at /docs/<slug>
├── src/lib/navigation.ts      the sidebar
├── src/markdoc/tags.js        the tags pages can use, such as callout
├── src/components/            the layout, header, search and other components
├── scripts/copy-assets.mjs    copies the dashboard demo from .github/
├── scripts/vercel-output.mjs  writes the Vercel output, with a security policy per page
├── scripts/preview.mjs        serves that output locally, headers and all
└── vercel.json                the Vercel project settings
```

### Add or change a page

1. Write the page as Markdown in `docs/src/app/docs/<slug>/page.md`, starting
   with its frontmatter:

   ```yaml
   ---
   title: Hooks
   nextjs:
     metadata:
       title: Hooks
       description: One sentence for search engines and link previews.
   ---
   ```

2. Add it to the sidebar in `docs/src/lib/navigation.ts`.
3. Preview it with `make docs:dev`, then check it with `make docs:build` and
   `make docs:lint`. `make docs:preview` serves the built site with the same
   headers Vercel sends, to check it under its Content Security Policy.

Search indexes every page at build time, so a new page needs nothing else.

### Markdoc tags

| Tag                 | Use it for                                                   |
| ------------------- | ------------------------------------------------------------ |
| `{% callout %}`     | A note beside the text, with `type="warning"` for a warning. |
| `{% quick-links %}` | A grid of `{% quick-link /%}` cards linking to other pages.  |
| `{% figure /%}`     | An image with a caption.                                     |

The site's template code is covered by the Tailwind Plus licence in
`docs/LICENSE.md`, not by Rift's MIT licence.
