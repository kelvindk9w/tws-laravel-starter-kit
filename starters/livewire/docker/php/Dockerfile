# syntax=docker/dockerfile:1
# =============================================================================
# Dockerfile PHP-FPM — TWS Laravel Starter Kit
#
# Targets:
#   dev   → desenvolvimento (código montado via volume bind)
#   prod  → produção (código, vendor e assets DENTRO da imagem — imutável)
#
# O target prod precisa do contexto de build nomeado `packages` (a pasta
# packages/ da raiz), além do contexto principal (o starter):
#   docker build --build-context packages=../../packages --target prod \
#     -f docker/php/Dockerfile .
#
# Base pinada: php:8.4.24-fpm-alpine (nunca usar `latest`).
# Extensões: pgsql, redis, intl, bcmath, gd, zip, opcache, pcntl (filas).
# As dependências PHP de produção são instaladas SOBRE este mesmo base (estágio
# composer-prod), então a checagem de plataforma do Composer vale para o PHP
# que de fato roda em produção.
# postgresql18-client: pg_dump/pg_restore 18 para os backups.
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
#
# POR QUE ESTE ESTÁGIO PARTE DO `base`, E NÃO DA IMAGEM `composer`: ele já
# partiu de `composer:2`, e foi por isso que a imagem de produção deixou de
# construir quando o Horizon entrou. A imagem `composer` traz o PHP DELA (outra
# versão, outro conjunto de extensões), e a checagem de plataforma do
# `composer install` compara as exigências do lock com o PHP que está rodando
# ali — não com o PHP que vai rodar em produção. Cada extensão nova exigida por
# uma dependência (ext-pcntl do Horizon, ext-intl do Filament) virava um
# `docker-php-ext-install` remendado aqui, e o primeiro esquecido quebrou o
# build inteiro.
#
# Instalando no `base`, a checagem de plataforma é feita contra EXATAMENTE o PHP
# e as extensões da imagem final: se passar aqui, a aplicação tem em produção
# tudo que as dependências declaram precisar; se faltar extensão, o build falha
# alto — que é o comportamento certo. Por isso não existe, nem deve existir,
# `--ignore-platform-reqs` neste arquivo.
#
# Do composer, só o binário vem da imagem oficial, com versão pinada.
# -----------------------------------------------------------------------------
FROM composer:2.10.3 AS composer-bin

FROM base AS composer-prod

COPY --from=composer-bin /usr/bin/composer /usr/bin/composer

# O build roda como root dentro do estágio descartável; nada deste usuário
# chega à imagem final (que roda como www-data).
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app

COPY . .

# PACOTES DO KIT. O composer.json instala os pacotes por path repository
# (`../../packages/<pacote>`), que visto de /app é /packages/<pacote>. Eles
# chegam por um CONTEXTO DE BUILD NOMEADO, `packages` (a pasta packages/ da
# raiz do repositório — ver docker-compose.prod.yml e o CI), porque o
# contexto principal é o starter e não enxerga a pasta de cima. O
# packages/.dockerignore deixa de fora o que é de desenvolvimento do pacote
# (vendor, testes, lock).
#
# COMPOSER_MIRROR_PATH_REPOS=1: em produção o pacote é COPIADO para
# vendor/, nunca um link — a imagem final não leva /packages, e o vendor/
# precisa ser autossuficiente.
COPY --from=packages . /packages/
ENV COMPOSER_MIRROR_PATH_REPOS=1

# O `.dockerignore` deixa o `storage/` inteiro de fora (uploads, backups e
# sessões da máquina de quem fez o build nunca entram na imagem). A estrutura
# vazia que o framework espera é recriada aqui, antes do `composer install`,
# porque o `package:discover` do post-autoload-dump boota a aplicação.
#
# `check-platform-reqs` depois da instalação é redundante de propósito: deixa
# no log do build a lista de extensões conferidas contra o PHP da imagem final.
RUN mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --no-progress \
        --prefer-dist \
    && composer check-platform-reqs --no-dev \
    && rm -rf docker storage/logs/*

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

# O CÓDIGO É DE ROOT, e o processo roda como www-data: o usuário que atende a
# requisição e processa a fila LÊ o código, mas não consegue reescrevê-lo. Uma
# falha que desse escrita em disco ao processo (upload mal validado, desvio de
# caminho) fica restrita aos diretórios de estado abaixo, em vez de poder
# plantar PHP dentro de app/ ou public/ de uma imagem que se diz imutável.
COPY --from=composer-prod /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

# Entrypoint: exige APP_KEY em produção e publica o public/ para o nginx.
#
# Só o ESTADO é gravável por www-data: storage/ (logs, sessões, views
# compiladas, uploads locais), bootstrap/cache e o ponto de montagem do volume
# compartilhado com o nginx. O link public/storage é criado aqui, no build,
# porque em tempo de execução o public/ já não é gravável pelo processo.
COPY docker/php/entrypoint-prod.sh /usr/local/bin/entrypoint-prod.sh
RUN chmod 755 /usr/local/bin/entrypoint-prod.sh \
    && ln -sfn /var/www/html/storage/app/public /var/www/html/public/storage \
    && mkdir -p /app-public \
    && chown -R www-data:www-data storage bootstrap/cache /app-public

USER www-data

ENTRYPOINT ["entrypoint-prod.sh"]
CMD ["php-fpm"]
