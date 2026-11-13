#!/bin/sh
# Runs the tests with coverage of every internal package, and fails below 100%,
# which go test has no option for.
#
# cmd/rift-dash is left out: its main only hands os.Args and the standard
# streams to internal/app, where everything is tested.
set -eu

go test -coverpkg=./internal/... -coverprofile=coverage.out ./... "$@"
go tool cover -html=coverage.out -o coverage.html

total="$(go tool cover -func=coverage.out | awk '/^total:/ { print $3 }')"
echo "Total: ${total}"
echo "HTML report: dash/coverage.html"

if [ "$total" != "100.0%" ]; then
    go tool cover -func=coverage.out | grep -v '100.0%$' >&2
    echo "Coverage must be 100%." >&2
    exit 1
fi
