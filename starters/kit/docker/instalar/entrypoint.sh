#!/bin/sh
# =============================================================================
# `docker compose run --rm instalar` — cria o projeto NESTA PASTA (a do ZIP ou
# do clone do twstec-kit, montada em /app), com o mesmo menu do
# `composer create-project twstec/kit`.
#
# 1. Confere que a pasta ainda é o twstec-kit (e não um projeto já criado).
# 2. O DONO DOS ARQUIVOS: tudo roda com o uid/gid de quem é dono da pasta na
#    máquina (TWS_KIT_UID/TWS_KIT_GID vencem) — nada fica com dono root no
#    Linux e no WSL. No Windows (pasta do Windows montada), a pasta aparece
#    como root: vale 1000, e o Windows não se importa.
# 3. O Docker: com o socket montado, o processo entra no grupo dele — o menu
#    confere os projetos e as portas em uso.
# 4. O Composer instala o que o menu usa (Laravel Prompts), e o
#    post-create-project-cmd do twstec/kit faz o resto (o menu, o starter, o
#    composer update, o instalador do starter, o .env com o Docker de
#    desenvolvimento).
# 5. O front: npm ci e o build (o site abre mesmo antes do Vite subir).
#
# Com argumentos, roda-os como o dono da pasta (ex.: `docker compose run --rm
# instalar sh`, para investigar).
# =============================================================================
set -eu

APP=/app

owner_uid=${TWS_KIT_UID:-$(stat -c %u "$APP")}
owner_gid=${TWS_KIT_GID:-$(stat -c %g "$APP")}

if [ "$owner_uid" = 0 ]; then
    owner_uid=1000
    owner_gid=1000
fi

groups=--clear-groups

if [ -S /var/run/docker.sock ]; then
    groups="--groups=$(stat -c %g /var/run/docker.sock)"
fi

mkdir -p /tmp/home /tmp/cache/composer /tmp/cache/npm
chown -R "$owner_uid:$owner_gid" /tmp/home /tmp/cache

# Pasta do Windows montada no Docker: a mudança de arquivo não chega ao Vite
# sozinha — ele passa a conferir de tempos em tempos (DEV_VITE_POLLING no .env).
polling=false
if grep -E " $APP " /proc/self/mountinfo | grep -Eqi '/mnt/host/[a-z]/|[a-z]:\\|drvfs|9p'; then
    polling=true
fi

run_as_owner() {
    exec_or_run=$1
    shift
    set -- setpriv --reuid="$owner_uid" --regid="$owner_gid" "$groups" \
        env HOME=/tmp/home COMPOSER_HOME=/tmp/home/.composer COMPOSER_CACHE_DIR=/tmp/cache/composer \
        npm_config_cache=/tmp/cache/npm TWS_KIT_UID="$owner_uid" TWS_KIT_GID="$owner_gid" \
        TWS_KIT_VITE_POLLING="$polling" "$@"

    if [ "$exec_or_run" = exec ]; then
        exec "$@"
    fi

    "$@"
}

if [ "$#" -gt 0 ]; then
    run_as_owner exec "$@"
fi

if [ ! -f "$APP/composer.json" ]; then
    echo "Esta pasta não tem o composer.json do twstec-kit. Rode o comando dentro da pasta do ZIP (ou do clone) do twstec-kit."
    exit 1
fi

if ! grep -Eq '"name": *"twstec/kit"' "$APP/composer.json"; then
    echo "Esta pasta já é um projeto (não é mais o twstec-kit). Para subir o projeto: docker compose up -d"
    exit 1
fi

cd "$APP"

echo "» Preparando o instalador (Composer)"
run_as_owner run composer install --no-interaction --no-progress --quiet

# O menu (num terminal) ou a escolha pelas variáveis TWS_KIT_* (sem terminal:
# docker compose run -T …). O código de saída é o do instalador.
run_as_owner run composer run-script post-create-project-cmd

if [ -f package.json ]; then
    echo
    echo "» Compilando o front (npm ci e npm run build)"
    if ! run_as_owner run sh -c 'npm ci --no-audit --no-fund && npm run build'; then
        echo "O front não foi compilado agora (veja a saída acima). O docker compose up -d instala as dependências dele e sobe o Vite, que serve o front enquanto roda."
    fi
fi

url=$(sed -n 's/^APP_URL=//p' .env 2>/dev/null | tr -d '"' | head -n 1)
echo
echo "Tudo pronto. Agora, nesta pasta: docker compose up -d"
if [ -n "$url" ]; then
    echo "Depois, abra $url no navegador."
fi
