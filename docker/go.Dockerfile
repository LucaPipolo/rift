# Go toolchain for rift-dash, the terminal dashboard `rift dash` opens.
FROM golang:1.27.4-alpine

# The tests of the git tab run git in temporary repositories.
RUN apk add --no-cache git

COPY --from=golangci/golangci-lint:v2.15.0-alpine /usr/bin/golangci-lint /usr/bin/golangci-lint

# The cache volume starts from this directory, so the non-root user every
# target runs as can write to it.
RUN mkdir -p /tmp/go && chmod 0777 /tmp/go
