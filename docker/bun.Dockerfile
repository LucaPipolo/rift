# Bun image for commitlint and Husky, so the host still needs only Docker.
# Husky configures the hooks through git, so the image needs git too.
FROM oven/bun:1-alpine

RUN apk add --no-cache git \
    && git config --system --add safe.directory '*'

# The container runs as the host user, so the Bun cache mounted here as a
# volume must be writable by any uid.
RUN mkdir -p /tmp/.bun-cache && chmod -R a+rwX /tmp/.bun-cache

WORKDIR /app
