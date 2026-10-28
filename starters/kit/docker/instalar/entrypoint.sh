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
# 3. O Composer instala o que o menu usa (Laravel Prompts).
# 4. A ESCOLHA — o único passo com o Docker: com o socket montado, só o menu
#    (ou a escolha pelas variáveis TWS_KIT_*) entra no grupo dele, para
#    conferir os projetos e as portas em uso. A escolha fica num arquivo.
# 5. O post-create-project-cmd do twstec/kit faz o resto, com a escolha pelo
#    ambiente e SEM o Docker: o starter, o composer update, o instalador do
#    starter, o .env com o Docker de desenvolvimento. É código de terceiros
#    (pacotes do Composer, scripts deles, o artisan) — e o socket do Docker
#    vale root na máquina: nada disso o alcança.
# 6. A reserva do nome e do número do projeto (o volume do banco, com os
#    rótulos — ver DockerHost::reserve()), pela linha de comando do Docker.
# 7. O front: npm ci (sem os scripts de instalação dos pacotes) e o build,
#    sem o Docker (o site abre mesmo antes do Vite subir).
#
# Com argumentos, roda-os como o dono da pasta, sem o Docker (ex.: `docker
# compose run --rm instalar sh`, para investigar).
# =============================================================================
set -eu

APP=/app

owner_uid=${TWS_KIT_UID:-$(stat -c %u "$APP")}
owner_gid=${TWS_KIT_GID:-$(stat -c %g "$APP")}

if [ "$owner_uid" = 0 ]; then
    owner_uid=1000
    owner_gid=1000
fi

# Só o passo da escolha (e a reserva) entra no grupo do socket do Docker.
docker_groups=
if [ -S /var/run/docker.sock ]; then
    docker_groups="--groups=$(stat -c %g /var/run/docker.sock)"
fi

mkdir -p /tmp/home /tmp/cache/composer /tmp/cache/npm
chown -R "$owner_uid:$owner_gid" /tmp/home /tmp/cache

# Pasta do Windows montada no Docker: a mudança de arquivo não chega ao Vite
# sozinha — ele passa a conferir de tempos em tempos (DEV_VITE_POLLING no .env).
polling=false
if grep -E " $APP " /proc/self/mountinfo | grep -Eqi '/mnt/host/[a-z]/|[a-z]:\\|drvfs|9p'; then
    polling=true
fi

# run_as_owner exec|run <comando…>: como o dono da pasta, sem grupo nenhum
# (sem o Docker). run_with_docker <comando…>: idem, no grupo do socket.
run_with_docker() {
    groups=${docker_groups:---clear-groups}
    run_owner run "$@"
}

run_as_owner() {
    groups=--clear-groups
    run_owner "$@"
}

run_owner() {
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

# A escolha: o menu (num terminal) ou a pelas variáveis TWS_KIT_* (sem
# terminal: docker compose run -T …), conferida contra o Docker. Só o código
# do twstec/kit e do Laravel Prompts roda aqui.
choice_file=/tmp/home/escolha.env
run_with_docker env TWS_KIT_CHOICE_FILE="$choice_file" php kit-setup/create.php

# O resto, sem o Docker, com a escolha pelo ambiente (só as variáveis de
# escolha, nunca o arquivo como roteiro). O código de saída é o do instalador.
while IFS='=' read -r variable value; do
    case "$variable" in
        TWS_KIT_STACK|TWS_KIT_WITH|TWS_KIT_WITHOUT|TWS_KIT_NAME|TWS_KIT_SLOT|TWS_KIT_EXPOSE_DB) export "$variable=$value" ;;
        *) echo "escolha inválida no arquivo do instalador: $variable"; exit 1 ;;
    esac
done < "$choice_file"

run_as_owner run composer run-script post-create-project-cmd

# A reserva do nome e do número (o instalador do starter, sem o Docker, não
# a faz): o volume do banco do projeto, com os rótulos do Compose e do número.
name=$(sed -n 's/^COMPOSE_PROJECT_NAME=//p' .env 2>/dev/null | tr -d '"' | head -n 1)
slot=$(sed -n 's/^DEV_SLOT=//p' .env 2>/dev/null | tr -d '"' | head -n 1)
if [ -n "$docker_groups" ] && [ -n "$name" ] && [ -n "$slot" ]; then
    run_with_docker docker volume create \
        --label "com.docker.compose.project=$name" \
        --label com.docker.compose.volume=postgres_data \
        --label "twstec.kit.slot=$slot" \
        "${name}_postgres_data" >/dev/null || true
fi

if [ -f package.json ]; then
    echo
    echo "» Compilando o front (npm ci e npm run build)"
    if ! run_as_owner run sh -c 'npm ci --ignore-scripts --no-audit --no-fund && npm run build'; then
        echo "O front não foi compilado agora (veja a saída acima). O docker compose up -d instala as dependências dele e sobe o Vite, que serve o front enquanto roda."
    fi
fi

url=$(sed -n 's/^APP_URL=//p' .env 2>/dev/null | tr -d '"' | head -n 1)
echo
echo "Tudo pronto. Agora, nesta pasta: docker compose up -d"
if [ -n "$url" ]; then
    echo "Depois, abra $url no navegador."
fi
