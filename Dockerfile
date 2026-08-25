FROM alpine:3.22 AS upstream
ARG JORANI_VERSION=v1.0.4
RUN set -eux; \
    apk add --no-cache git; \
    fetched=0; \
    for attempt in 1 2 3 4 5; do \
      rm -rf /src; \
      if GIT_TERMINAL_PROMPT=0 git -c http.version=HTTP/1.1 clone \
        --depth 1 \
        --branch "${JORANI_VERSION}" \
        --single-branch \
        https://github.com/jorani/jorani.git /src; then \
        fetched=1; \
        break; \
      fi; \
      sleep $((attempt * 2)); \
    done; \
    test "$fetched" = "1"; \
    rm -rf /src/.git

FROM composer:2 AS composer
WORKDIR /app/legacy
COPY --from=upstream /src/legacy/composer.json /src/legacy/composer.lock ./
RUN composer install --ignore-platform-reqs --no-dev --no-interaction --prefer-dist \
    && composer config allow-plugins.php-http/discovery true \
    && composer require --ignore-platform-reqs --update-no-dev --no-interaction --prefer-dist --with-all-dependencies \
      minishlink/web-push:11.0.0 guzzlehttp/guzzle:^7.9

FROM php:8.5-apache
ENV LANGUAGE=polish \
    LANGUAGES=pl \
    HAYNE_AD_DEFAULT_LANGUAGE=pl
RUN apt-get update && apt-get install -y --no-install-recommends \
      libcurl4-openssl-dev \
      libfreetype6-dev \
      libjpeg62-turbo-dev \
      libldap2-dev \
      libonig-dev \
      libpng-dev \
      libzip-dev \
      patch \
      zlib1g-dev \
    && docker-php-ext-configure zip \
    && docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath curl gd ldap mbstring zip pdo pdo_mysql \
    && a2enmod rewrite headers deflate filter \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY --from=upstream /src ./
COPY --from=composer /app/legacy/vendor ./legacy/vendor
COPY hayne/overlay/ ./
COPY manifest.webmanifest ./manifest.webmanifest
COPY service-worker.js ./service-worker.js
COPY hayne/tools/ad-sync-preview.php /opt/hayne/ad-sync-preview.php
COPY hayne/tools/ad-sync-plan.php /opt/hayne/ad-sync-plan.php
COPY hayne/tools/calendar-sync.php /opt/hayne/calendar-sync.php
COPY hayne/tools/push-install.php /opt/hayne/push-install.php
COPY hayne/tools/push-vapid.php /opt/hayne/push-vapid.php
COPY hayne/patches/ /tmp/hayne-patches/
RUN set -eux; \
    chmod 0555 /opt/hayne/ad-sync-preview.php /opt/hayne/ad-sync-plan.php /opt/hayne/calendar-sync.php /opt/hayne/push-install.php /opt/hayne/push-vapid.php; \
    for patch_file in /tmp/hayne-patches/*.patch; do \
      patch --batch --forward -p1 < "$patch_file"; \
    done; \
    rm -rf /tmp/hayne-patches; \
    chown -R www-data:www-data legacy/application/logs; \
    chmod 775 legacy/application/logs
