---
title: Reporting bugs
nextjs:
  metadata:
    title: Reporting bugs
    description: How to report a Rift bug with what is needed to reproduce it, and where to report a security vulnerability instead.
---

A good bug report lets someone else reproduce the problem on their machine.
{% .lead %}

{% callout type="warning" title="Security vulnerabilities" %}
For a security vulnerability, do not open a public issue. Follow
[SECURITY.md](https://github.com/LucaPipolo/rift/blob/main/SECURITY.md) instead.
{% /callout %}

---

## Opening an issue

For anything else, [open an issue](https://github.com/LucaPipolo/rift/issues)
with:

- what you ran and what you expected,
- the output of the command with `-v`, for example `rift up -v`,
- the output of `rift doctor`,
- your operating system, and how you run Docker.

Before opening one, [Troubleshooting](/docs/troubleshooting) covers the most
common problems and their fixes.
