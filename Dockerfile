FROM alpine:3.22 AS upstream
ARG JORANI_VERSION=v1.0.4
RUN apk add --no-cache curl tar \
    && mkdir -p /src \
    && curl -fL \
      --retry 5 \
      --retry-delay 2 \
      --retry-all-errors \
      -o /tmp/jorani.tar.gz \
      "https://github.com/jorani/jorani/archive/refs/tags/${JORANI_VERSION}.tar.gz" \
    && tar -xzf /tmp/jorani.tar.gz --strip-components=1 -C /src \
    && rm -f /tmp/jorani.tar.gz

FROM composer:2 AS composer
WORKDIR /app/legacy
COPY --from=upstream /src/legacy/composer.json /src/legacy/composer.lock ./
RUN composer install --ignore-platform-reqs --no-dev --no-interaction --prefer-dist

FROM php:8.5-apache
RUN apt-get update && apt-get install -y --no-install-recommends \
      libfreetype6-dev \
      libjpeg62-turbo-dev \
      libldap2-dev \
      libpng-dev \
      libzip-dev \
      patch \
      zlib1g-dev \
    && docker-php-ext-configure zip \
    && docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd ldap zip pdo pdo_mysql \
    && a2enmod rewrite headers deflate filter \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY --from=upstream /src ./
COPY --from=composer /app/legacy/vendor ./legacy/vendor
COPY hayne/overlay/ ./
COPY hayne/tools/ad-sync-preview.php /opt/hayne/ad-sync-preview.php
COPY hayne/tools/ad-sync-plan.php /opt/hayne/ad-sync-plan.php
COPY hayne/patches/ /tmp/hayne-patches/
RUN set -eux; \
    chmod 0555 /opt/hayne/ad-sync-preview.php /opt/hayne/ad-sync-plan.php; \
    for patch_file in /tmp/hayne-patches/*.patch; do \
      patch --batch --forward -p1 < "$patch_file"; \
    done; \
    rm -rf /tmp/hayne-patches; \
    chown -R www-data:www-data legacy/application/logs; \
    chmod 775 legacy/application/logs
