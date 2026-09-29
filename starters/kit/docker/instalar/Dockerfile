# syntax=docker/dockerfile:1
# =============================================================================
# O INSTALADOR EM CONTAINER do twstec/kit — `docker compose run --rm instalar`
# (ver o compose.yaml ao lado da pasta docker/): cria o projeto sem PHP,
# Composer nem Node na máquina, só com o Docker.
#
# Tem o que a criação do projeto usa: o PHP 8.4 com as extensões que os
# starters exigem (o `composer update` confere a plataforma — pcntl do
# Horizon, intl do Filament, gd dos uploads…), o Composer (o mesmo binário
# pinado da imagem de produção), o Node 24 (o `npm ci` e o build do front; em
# Debian, porque os binários nativos do front do React são os de glibc) e o
# `docker` (só a linha de comando: com o socket do Docker montado, o menu
# confere os projetos e as portas que já estão em uso na máquina).
#
# É uma ferramenta de uma vez: não vai para projeto nenhum. A mesma imagem
# (twstec-kit-instalar:<versão>) serve a todos os projetos criados na
# máquina; quando ESTE ARQUIVO muda, a versão no compose.yaml muda junto (o
# roteiro, não: ele é montado da pasta — ver o ENTRYPOINT).
# =============================================================================
FROM composer:2.10.3 AS composer-bin
FROM docker:29-cli AS docker-cli
FROM node:24-slim AS node

FROM php:8.4.24-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype-dev \
        libpq-dev \
        unzip \
        git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql intl bcmath gd zip pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer
COPY --from=docker-cli /usr/local/bin/docker /usr/local/bin/docker
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && node --version && npm --version && composer --version && docker --version

# O roteiro do instalador NÃO fica na imagem: o compose.yaml o monta da pasta
# (docker/instalar/entrypoint.sh), e assim a imagem de uma versão anterior do
# kit, já construída na máquina, roda sempre o roteiro desta versão. Finais de
# linha do Windows (CRLF, num clone com autocrlf) quebrariam o `sh`: saem antes.
WORKDIR /app
ENTRYPOINT ["sh", "-c", "sed 's/\\r$//' /opt/twstec-kit/entrypoint.sh > /tmp/tws-kit-instalar.sh && exec sh /tmp/tws-kit-instalar.sh \"$@\"", "tws-kit-instalar"]
