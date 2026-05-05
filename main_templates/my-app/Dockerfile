FROM php:8.4-cli-bookworm AS deps

ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libonig-dev \
        libzip-dev \
        libxml2-dev \
        libsqlite3-dev \
        pkg-config \
    && docker-php-ext-install \
        bcmath \
        intl \
        mbstring \
        pdo_mysql \
        pdo_sqlite \
        sockets \
        xml \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --no-scripts \
        --ignore-platform-req=ext-mongodb \
        --optimize-autoloader

FROM php:8.4-cli-bookworm AS frontend
WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        xz-utils \
    && rm -rf /var/lib/apt/lists/*

RUN curl -fsSL https://nodejs.org/dist/v22.18.0/node-v22.18.0-linux-x64.tar.xz -o /tmp/node.tar.xz \
    && tar -xJf /tmp/node.tar.xz -C /usr/local --strip-components=1 \
    && rm -f /tmp/node.tar.xz

COPY --from=deps /app/vendor ./vendor
COPY . .
RUN mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views database \
    && touch database/database.sqlite
RUN npm ci
RUN php artisan wayfinder:generate --with-form
RUN npm run build

FROM php:8.4-cli-bookworm AS app

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV APP_ENV=production
ENV APP_DEBUG=false
WORKDIR /var/www/html

RUN apt-get update && apt-get install -y --no-install-recommends \
        autoconf \
        build-essential \
        git \
        unzip \
        libicu-dev \
        libonig-dev \
        libssl-dev \
        libsqlite3-dev \
        libxml2-dev \
        libzip-dev \
        make \
        pkg-config \
    && docker-php-ext-install \
        bcmath \
        intl \
        mbstring \
        pdo_mysql \
        pdo_sqlite \
        sockets \
        xml \
        zip \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && rm -rf /var/lib/apt/lists/*

COPY --from=deps /app/vendor ./vendor
COPY . .
COPY --from=frontend /app/public/build ./public/build
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache database
RUN php artisan package:discover --ansi
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache database

EXPOSE 8080
ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["web"]
