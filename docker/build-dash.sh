#!/bin/sh
# Builds rift-dash, the terminal dashboard `rift dash` opens, for each target.
#
# Usage:
#   docker/build-dash.sh <target>...   targets: darwin-arm64 darwin-x86_64 linux-arm64 linux-x86_64
#
# Writes builds/rift-dash-<target>, statically linked, so it runs wherever the
# matching rift binary does.
set -eu

root="$(cd "$(dirname "$0")/.." && pwd)"

[ $# -gt 0 ] || { echo "Usage: docker/build-dash.sh <target>..." >&2; exit 1; }

for target in "$@"; do
    case "$target" in
        darwin-arm64|darwin-x86_64|linux-arm64|linux-x86_64) ;;
        *) echo "Unknown target ${target}. Use: darwin-arm64 darwin-x86_64 linux-arm64 linux-x86_64" >&2; exit 1 ;;
    esac

    os="${target%-*}"
    arch="${target#*-}"
    [ "$arch" = x86_64 ] && arch=amd64

    (cd "$root/dash" && CGO_ENABLED=0 GOOS="$os" GOARCH="$arch" go build -trimpath \
        -ldflags "-s -w" \
        -o "$root/builds/rift-dash-$target" ./cmd/rift-dash)

    echo "Built builds/rift-dash-$target"
done
