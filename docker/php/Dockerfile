# syntax=docker/dockerfile:1
# =============================================================================
# Dockerfile PHP-FPM — TWS Laravel Starter Kit
#
# Targets:
#   dev   → desenvolvimento (código montado via volume bind)
#   prod  → produção (código, vendor e assets DENTRO da imagem — imutável)
#
# Base pinada: php:8.4.24-fpm-alpine (nunca usar `latest`).
# Extensões: pgsql, redis, intl, bcmath, gd, zip, opcache, pcntl (filas).
# =============================================================================

# -----------------------------------------------------------------------------
# Base compartilhada: PHP-FPM 8.4 + extensões exigidas pelo projeto
# -----------------------------------------------------------------------------
FROM php:8.4.24-fpm-alpine AS base

# Bibliotecas de runtime + dependências de build das extensões.
# .build-deps é removido ao final para manter a imagem enxuta.
RUN apk add --no-cache \
        icu-libs \
        icu-data-full \
        libzip \
        libpng \
        libjpeg-turbo \
        freetype \
        oniguruma \
        postgresql-libs \
        sqlite-libs \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        postgresql-dev \
        oniguruma-dev \
        sqlite-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        pdo_sqlite \
        intl \
        bcmath \
        gd \
        zip \
        opcache \
        pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

WORKDIR /var/www/html

# -----------------------------------------------------------------------------
# DESENVOLVIMENTO: código entra via volume bind no docker-compose.yml
# -----------------------------------------------------------------------------
FROM base AS dev

COPY docker/php/php-dev.ini /usr/local/etc/php/conf.d/zz-dev.ini

CMD ["php-fpm"]

# -----------------------------------------------------------------------------
# Stage de dependências PHP de produção (sem pacotes de dev, autoload otimizado)
# O composer.json fixa config.platform.php=8.4 para resolver dependências
# corretamente mesmo o Composer rodando em PHP mais novo.
# -----------------------------------------------------------------------------
FROM composer:2 AS composer-prod

WORKDIR /app

COPY . .

RUN composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --prefer-dist

# -----------------------------------------------------------------------------
# Stage de build do frontend (Node 24 LTS — build roda EM CONTAINER, nunca local)
# -----------------------------------------------------------------------------
FROM node:24-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json* ./

RUN if [ -f package-lock.json ]; then npm ci --ignore-scripts; else npm install --ignore-scripts; fi

COPY . .
COPY --from=composer-prod /app/vendor ./vendor

RUN npm run build

# -----------------------------------------------------------------------------
# PRODUÇÃO: imagem imutável com código + vendor + assets compilados
# -----------------------------------------------------------------------------
FROM base AS prod

COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/zz-prod.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini

COPY --from=composer-prod --chown=www-data:www-data /app /var/www/html
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build

# Entrypoint: gera APP_KEY se ausente e garante o symlink de storage.
COPY docker/php/entrypoint-prod.sh /usr/local/bin/entrypoint-prod.sh
RUN chmod +x /usr/local/bin/entrypoint-prod.sh \
    && mkdir -p storage/logs bootstrap/cache /app-public \
    && chown -R www-data:www-data storage bootstrap/cache /app-public

USER www-data

ENTRYPOINT ["entrypoint-prod.sh"]
CMD ["php-fpm"]
