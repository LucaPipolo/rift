#!/usr/bin/env bash
# Builds rift.phar, then a native binary per target by appending the PHAR to a
# static PHP "micro" runtime from static-php-cli (https://static-php.dev).
#
# Usage:
#   docker/build.sh <target>...   targets: darwin-arm64 darwin-x86_64 linux-arm64 linux-x86_64
#   docker/build.sh --checksums   re-pin docker/micro.sha256 after changing PHP_VERSION
#
# Writes builds/rift.phar and builds/rift-<target>. The build runs in a copy of
# the working tree with production dependencies only, so vendor/ is untouched.
set -euo pipefail

PHP_VERSION="8.5.8"
TARGETS=(darwin-arm64 darwin-x86_64 linux-arm64 linux-x86_64)
MIRROR="https://dl.static-php.dev/static-php-cli/common"

root="$(cd "$(dirname "$0")/.." && pwd)"
checksums="$root/docker/micro.sha256"
cache="${MICRO_CACHE:-${HOME:-/tmp}/.cache/rift-micro}"

# Map a release target to static-php-cli's naming, e.g. darwin-arm64 -> macos-aarch64.
tarball() {
    local os="${1%-*}" arch="${1#*-}"
    [[ "$os" == darwin ]] && os=macos
    [[ "$arch" == arm64 ]] && arch=aarch64
    echo "php-${PHP_VERSION}-micro-${os}-${arch}.tar.gz"
}

# Download a target's runtime once, verify it against the pinned checksum, and print its path.
runtime() {
    local name dir expected
    name="$(tarball "$1")"
    dir="$cache/${name%.tar.gz}"

    if [[ ! -f "$dir/micro.sfx" ]]; then
        expected="$(grep " ${name}\$" "$checksums" | cut -d' ' -f1 || true)"
        [[ -n "$expected" ]] || { echo "No checksum pinned for ${name}. Run docker/build.sh --checksums." >&2; exit 1; }

        mkdir -p "$dir"
        curl -fsSL -o "$dir/$name" "$MIRROR/$name"
        echo "${expected}  $dir/$name" | sha256sum -c --quiet - >&2 || { rm -rf "$dir"; echo "Checksum mismatch for ${name}." >&2; exit 1; }
        tar -xzf "$dir/$name" -C "$dir" micro.sfx
        rm "$dir/$name"
    fi

    echo "$dir/micro.sfx"
}

if [[ "${1:-}" == "--checksums" ]]; then
    work="$(mktemp -d)"
    trap 'rm -rf "$work"' EXIT

    for target in "${TARGETS[@]}"; do
        name="$(tarball "$target")"
        curl -fsSL -o "$work/$name" "$MIRROR/$name"
        (cd "$work" && sha256sum "$name")
    done > "$checksums"

    cat "$checksums"
    exit 0
fi

[[ $# -gt 0 ]] || { echo "Usage: docker/build.sh <target>... (${TARGETS[*]})" >&2; exit 1; }

for target in "$@"; do
    [[ " ${TARGETS[*]} " == *" $target "* ]] || { echo "Unknown target ${target}. Use: ${TARGETS[*]}" >&2; exit 1; }
done

version="${VERSION:-$(git -C "$root" describe --tags --always --dirty 2>/dev/null || echo dev)}"
version="${version#v}"

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

echo "Building rift ${version} with PHP ${PHP_VERSION}"

tar -C "$root" --exclude=./vendor --exclude=./node_modules --exclude=./builds --exclude=./dist --exclude=./.git -cf - . | tar -C "$work" -xf -

(
    cd "$work"
    composer install --no-dev --prefer-dist --no-interaction --no-progress --quiet
    php -d phar.readonly=0 rift app:build rift.phar --build-version="$version" --no-interaction
)

mkdir -p "$root/builds"
cp "$work/builds/rift.phar" "$root/builds/rift.phar"

for target in "$@"; do
    # Assigned first so a failed download stops the build instead of writing an empty binary.
    micro="$(runtime "$target")"
    cat "$micro" "$root/builds/rift.phar" > "$root/builds/rift-$target"
    chmod +x "$root/builds/rift-$target"
    echo "Built builds/rift-$target"
done
