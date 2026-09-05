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
# postgresql18-client: pg_dump/pg_restore 18 para os backups (Fase 7).
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
        postgresql18-client \
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

# Usuário de desenvolvimento com o MESMO uid/gid do host (build args, padrão
# 1000). php-fpm, queue, scheduler e `docker compose exec` passam a gravar
# como esse usuário: os arquivos que nascem no volume (views compiladas,
# logs, cache, uploads, `artisan make:*`) são editáveis no host e nenhum
# processo cria arquivo que outro não consiga sobrescrever. Sem isso, um
# artisan rodado como root deixava views compiladas e logs root-owned, o
# php-fpm (www-data) não conseguia reescrevê-los e o app inteiro respondia
# 500 até alguém corrigir a permissão à mão.
ARG UID=1000
ARG GID=1000
RUN (getent group "${GID}" >/dev/null || addgroup -g "${GID}" -S dev) \
    && adduser -u "${UID}" -G "$(getent group "${GID}" | cut -d: -f1)" -S -D -h /home/dev dev \
    && chown -R dev /var/www/html /home/dev

COPY docker/php/php-dev.ini /usr/local/etc/php/conf.d/zz-dev.ini

# php-fpm sem root ignora as diretivas user/group do pool e roda os workers
# como o próprio usuário do processo (dev) — exatamente o que queremos.
USER dev

CMD ["php-fpm"]

# -----------------------------------------------------------------------------
# Stage de dependências PHP de produção (sem pacotes de dev, autoload otimizado)
# O composer.json fixa config.platform.php=8.4 para resolver dependências
# corretamente mesmo o Composer rodando em PHP mais novo.
# -----------------------------------------------------------------------------
FROM composer:2 AS composer-prod

# ext-intl é exigida pelo filament/support: a imagem composer:2 não a traz,
# então instalamos aqui para o `composer install` passar na checagem de
# plataforma (a imagem final PHP, stage base, já tem intl compilada).
RUN apk add --no-cache icu-dev && docker-php-ext-install intl

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
