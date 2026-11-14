---
title: URLs and hostnames
nextjs:
  metadata:
    title: URLs and hostnames
    description: Write per-worktree values such as APP_URL into .env with templates, and serve extra hostnames such as an API or admin domain.
---

Your app usually needs to know its own address. Templates in the `env` section
write the right values into each worktree's `.env`, and `hosts` serves extra
hostnames beside the main one. {% .lead %}

---

## Templates for `.env`

Each key under `env` is written to `.env`, with placeholders filled in for the
current worktree:

```yaml
env:
  APP_URL: 'https://{domain}'
```

In the main checkout this writes `APP_URL=https://myapp.test`. In the `search`
worktree it writes `APP_URL=https://search.myapp.test`.

### Placeholders

The examples are the main checkout in `myapp/` and a worktree in
`myapp-search/`:

| Placeholder      | Main checkout        | `search` worktree           |
| ---------------- | -------------------- | --------------------------- |
| `{name}`         | `myapp`              | `myapp`                     |
| `{slug}`         | empty                | `myapp-search`              |
| `{domain}`       | `myapp.test`         | `search.myapp.test`         |
| `{base_domain}`  | `myapp.test`         | `myapp.test`                |
| `{compose_name}` | `myapp`              | `myapp-search`              |
| `{url}`          | `https://myapp.test` | `https://search.myapp.test` |

`{slug}` is the whole worktree directory name as a slug. Only the domain drops
the project name prefix.

Any [managed port](/docs/project-configuration#managed-ports) key is a
placeholder too, such as `{VITE_PORT}`.

{% callout title="Prefer {url} for the app's address" %}
`{url}` includes the router's HTTPS port when it is not `443`, while
`https://{domain}` never does. `rift init` writes `{url}`, so your app keeps the
right address if you
[move the router to other ports](/docs/valet-herd-lerd#other-ports).
{% /callout %}

---

## Additional hostnames

For an app with an API or admin hostname, list each one under `hosts`, and
write them into `.env` so your app can tell them apart:

```yaml
hosts:
  - api
  - admin

env:
  APP_URL: '{url}'
  API_DOMAIN: 'api.{domain}'
  ADMIN_DOMAIN: 'admin.{domain}'
```

Each copy then answers on these hostnames:

| App copy          | Additional hostnames                               |
| ----------------- | -------------------------------------------------- |
| Main              | `api.myapp.test`, `admin.myapp.test`               |
| `search` worktree | `api.search.myapp.test`, `admin.search.myapp.test` |

All hostnames route to the same app container. Rift handles their certificates;
your app decides how to respond to each hostname.

### Rules for aliases

- Each alias must be a single lowercase DNS label, such as `api`, not
  `api.v2`.
- Rift refuses a worktree whose name would collide with an alias, such as
  `myapp-api` when `api` is an alias, because both would be served at
  `api.myapp.test`.
