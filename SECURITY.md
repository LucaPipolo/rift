# Security policy

Rift runs with access to your machine: it installs a certificate authority in
your system trust store with `sudo`, and runs a router that serves your projects
over HTTPS. Reports of anything that could weaken that are very welcome.

## Supported versions

Only the latest release receives security fixes. Update with
`brew upgrade rift`, or download the latest binary from the
[releases page][releases].

## Reporting a vulnerability

Do not open a public issue. Report it privately instead:

- through [GitHub's private vulnerability reporting][report], or
- by email to [dev@lucapipolo.com](mailto:dev@lucapipolo.com).

Include:

- what an attacker could do, and under which conditions,
- the steps to reproduce it,
- the Rift version (`rift --version`), your operating system, and how you run
  [Docker].

You will get an acknowledgement, then updates as the fix progresses. Once a
fixed release is out, the vulnerability is disclosed in a
[GitHub security advisory][advisories], crediting you unless you prefer
otherwise.

## Scope

In scope:

- the `rift` binary and the PHAR it embeds,
- how Rift creates, stores and trusts its certificate authority,
- the router container Rift configures, and the ports and admin API it exposes,
- what Rift writes to `.env`, `rift.yaml` and `~/.config/rift`,
- the build and release pipeline, and the [Homebrew] formula.

Out of scope, so report it to the project concerned instead:

- vulnerabilities in [Caddy], Docker, [static-php-cli] or other dependencies,
  unless Rift's use of them causes the issue,
- the projects you run with Rift, and their containers.

[advisories]: https://github.com/LucaPipolo/rift/security/advisories
[Caddy]: https://caddyserver.com/
[Docker]: https://www.docker.com/
[Homebrew]: https://brew.sh/
[releases]: https://github.com/LucaPipolo/rift/releases/latest
[report]: https://github.com/LucaPipolo/rift/security/advisories/new
[static-php-cli]: https://static-php.dev/
