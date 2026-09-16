# Development image: everything rift needs to install, test, lint and build,
# so the host needs Docker and nothing else. PCOV is the coverage driver that
# Pest's coverage report and its TIA engine both rely on.
FROM php:8.5-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pcntl \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && echo 'phar.readonly = Off' > /usr/local/etc/php/conf.d/phar.ini \
    && git config --system --add safe.directory '*'

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_HOME=/tmp/composer

# The container runs as the host user, so the caches mounted here as volumes
# must be writable by any uid. An empty volume inherits these permissions.
RUN mkdir -p /tmp/composer/cache /tmp/.cache/rift-micro \
    && chmod -R a+rwX /tmp/composer /tmp/.cache

WORKDIR /app
