---
title: Security
nextjs:
  metadata:
    title: Security
    description: How getrift.sh and the Rift repository are kept secure, and how to report a vulnerability in Rift or this website.
---

This page explains the security measures in place on this website and in the Rift repository, and how to report a vulnerability. {% .lead %}

Last updated: 14 November 2026.

---

## Source code

Rift and this website are open source. You can inspect both in the [LucaPipolo/rift repository on GitHub](https://github.com/LucaPipolo/rift): the website lives in its `docs/` folder.

---

## What keeps this website secure

Every page is sent with a [Content Security Policy](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Content-Security-Policy) that allows only the scripts and styles the page itself needs, listed by hash, and with the other [security headers recommended by the OWASP Secure Headers Project](https://owasp.org/www-project-secure-headers/). The policy is generated from each built page, so it always matches what the page contains. The website is static: it has no forms, no accounts and no server-side code that handles your input.

---

## Security automation on GitHub

The repository has [secret scanning with push protection](https://docs.github.com/en/code-security/secret-scanning) switched on, which blocks attempts to push credentials or keys into the code, and [Dependabot alerts](https://docs.github.com/en/code-security/dependabot/dependabot-alerts/about-dependabot-alerts), which flag known vulnerabilities in Rift's dependencies.

---

## Reporting a vulnerability

Rift installs a certificate authority in your system's trust store and runs a router that serves your projects, so reports of anything that could weaken that are very welcome. Don't open a public issue. Report it privately instead:

- through [GitHub's private vulnerability reporting](https://github.com/LucaPipolo/rift/security/advisories/new), which stays visible only to the maintainer until it is fixed, or
- by email to [dev@lucapipolo.com](mailto:dev@lucapipolo.com).

The repository's [security policy](https://github.com/LucaPipolo/rift/blob/main/SECURITY.md) explains what to include, which versions receive fixes, and what is in scope. The same contact details are published in a machine-readable form at [/.well-known/security.txt](/.well-known/security.txt).
