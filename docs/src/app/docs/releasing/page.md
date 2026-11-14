---
title: Releasing
nextjs:
  metadata:
    title: Releasing
    description: How Rift versions are numbered, how to publish a release, how the Homebrew formula is updated, and how binaries are built.
---

A release is a Git tag and a GitHub release. Publishing it starts a workflow
that builds the binaries and updates the Homebrew formula. {% .lead %}

---

## Choosing the version

Rift follows [Semantic Versioning](https://semver.org/), which the commits since
the last release decide:

- any breaking change raises the major version: `v1.4.2` to `v2.0.0`,
- otherwise, any `feat` raises the minor version: `v1.4.2` to `v1.5.0`,
- otherwise, the patch version: `v1.4.2` to `v1.4.3`.

---

## Publishing a release

1. Check that every workflow passes on `main`.
2. Tag the version and push the tag:

   ```bash
   git tag v1.5.0
   git push origin v1.5.0
   ```

3. Publish a GitHub release for the tag:

   ```bash
   gh release create v1.5.0 --verify-tag --generate-notes
   ```

   Add `--prerelease` for versions such as `v1.5.0-rc.1`.

Publishing the release starts the `release` workflow. It builds `rift-dash` and
the four binaries that carry it, then uploads them, `rift.phar` and their
checksums to the release. Rerunning it replaces the assets.

{% callout type="warning" title="Publish directly, not as a draft" %}
GitHub does not start the workflow for draft releases, so publish the release
directly rather than saving a draft first.
{% /callout %}

---

## The Homebrew formula

This repository is also Rift's [Homebrew](https://brew.sh/) tap, with the formula
in `Formula/rift.rb`. For stable releases, the workflow then sets the formula's
version and four checksums, and commits it to `main` as
`build: publish v1.5.0 to Homebrew`. Pull before your next change.

Prereleases leave the formula alone, so `brew upgrade` never installs them.

That commit lands after the tag, so the tag still holds the previous formula.
That is expected: Homebrew reads the formula from `main`, and the formula only
points at the release's binaries.

### Checking a formula change

To change anything else in the formula, edit `Formula/rift.rb` and check it with
Homebrew's audit in its official image:

```bash
docker run --rm -v "$PWD":/work:ro homebrew/brew sh -c \
  'brew tap-new --no-git local/check && cp /work/Formula/rift.rb "$(brew --repository local/check)/Formula/" && brew audit --strict local/check/rift'
```

---

## Setting up the release app

`main` only takes signed commits through pull requests, so the workflow commits
as a GitHub App that the branch ruleset lets through. Commits an app makes
through GitHub's API are signed by GitHub and show as verified. Set it up once:

1. [Create a GitHub App](https://github.com/settings/apps/new) on your account.
   Give it any name, such as `rift-release`, untick **Webhook > Active**, and
   under **Repository permissions** set **Contents** to **Read and write**. Leave
   everything else off.
2. On the app's page, note its Client ID, then generate a private key, which
   downloads a `.pem` file.
3. Under **Install App**, install it on the `rift` repository only.
4. Save the Client ID and the key where the workflow reads them:

   ```bash
   gh variable set RELEASE_APP_CLIENT_ID --repo LucaPipolo/rift --body "<client ID>"
   gh secret set RELEASE_APP_PRIVATE_KEY --repo LucaPipolo/rift < rift-release.pem
   ```

   Then delete the `.pem` file.

5. In **Settings > Rules > Rulesets**, open the ruleset protecting `main`, and
   add the app to its bypass list, under **Add bypass > Apps**.

---

## Building binaries

A Rift binary is `rift.phar`, built with Laravel Zero's `app:build`, carrying
the `rift-dash` built for its platform, appended to a static PHP runtime from
[static-php-cli](https://static-php.dev/). `docker/build-dash.sh` builds
`rift-dash`, and `docker/build.sh` the rest, for every target you name:

```bash
sh docker/build-dash.sh darwin-arm64 darwin-x86_64 linux-arm64 linux-x86_64
bash docker/build.sh darwin-arm64 darwin-x86_64 linux-arm64 linux-x86_64
```

It builds the PHAR in a copy of the working tree with production dependencies
only, so your `vendor/` is left alone. The version comes from the `VERSION`
variable, or from `git describe` when it is not set. `make build` runs both for
your machine only. Runtimes are cached after the first download.

Every binary is written as a new file rather than over the last one, because
macOS kills a binary rewritten in place after it has run.

---

## Updating PHP

The runtime's PHP version is pinned by `PHP_VERSION` in `docker/build.sh`, and
each runtime is checked against `docker/micro.sha256` before use. To move to a
newer PHP:

1. Change `PHP_VERSION` in `docker/build.sh`.
2. Run `make checksums`, and review the new `docker/micro.sha256`.
3. Run `make build` and try the binary.
4. Update the PHP version in `docker/Dockerfile` and in `.github/workflows/`, so
   development and CI stay on the same minor version.
