#!/bin/sh
# =============================================================================
# SIMULAÇÃO DA INSTALAÇÃO PUBLICADA — pega starter que só funciona dentro do
# monorepo.
#
# Monta, numa pasta limpa, o que o Packagist vai servir depois da publicação:
# cada pacote (packages/*) vira um ZIP pelo `composer archive` (respeita o
# export-ignore do .gitattributes — sem testes, sem phpunit.xml), com o
# composer.json da versão publicada (sem path repository, restrições ^2.0 —
# ver prepare-composer.php); o starter vira um ZIP também. Depois cria o
# projeto como quem usa o kit: `composer create-project` a partir SÓ desses
# ZIPs (repositório `artifact`), sem link para o monorepo — o vendor recebe
# CÓPIAS. Roda o post-create-project-cmd (o instalador em modo não interativo),
# o build do front e a suíte do starter. Por fim constrói a IMAGEM DE PRODUÇÃO
# do projeto criado (app e nginx) — o projeto não tem a pasta packages/ do
# monorepo — e passa as mesmas conferências de imagem limpa do CI.
#
# O repositório `artifact` é RELATIVO (`../packages`, os ZIPs em
# <pasta>/packages, o projeto em <pasta>/app): visto do projeto, é a pasta dos
# ZIPs; visto de /app, no estágio do Composer da imagem, é /packages — onde o
# Dockerfile põe o contexto de build nomeado opcional `packages`. Assim o build
# da imagem resolve os pacotes do kit pelos mesmos ZIPs, sem nada da simulação
# no Dockerfile publicado (no uso real o contexto não existe, /packages fica
# vazio e os pacotes vêm do Packagist).
#
# Uso: simulate-install.sh [etapa] [pasta]
#   etapas: all | copy (git + tar) | package (php + composer) | build (npm) |
#           test (pest) | image (docker: imagens de produção e conferências) |
#           docker (só com STARTER=kit: o caminho SEM PHP na máquina — ver
#           abaixo; depois de copy e package)
#   VERSION (padrão 2.0.0-beta.1)
#   STARTER (padrão livewire): livewire | react | kit — qual pacote vira o
#   projeto: twstec/starter-livewire, twstec/starter-react ou o COMANDO ÚNICO
#   twstec/kit (que baixa o starter escolhido — os dois vão empacotados).
#   Os pacotes do kit são os mesmos.
#   Com STARTER=kit, a escolha que o menu faria, pelo ambiente (sem terminal):
#     KIT_STACK (padrão livewire): livewire | react   → TWS_KIT_STACK
#     KIT_WITHOUT (padrão vazio): opcionais de fora   → TWS_KIT_WITHOUT
#   e as conferências passam a exigir que os módulos DESMARCADOS não estejam
#   no projeto (composer.json, vendor, registro do Composer, imagem).
#
# A ETAPA docker (STARTER=kit): o caminho de quem só tem o Docker. O ZIP do
# twstec/kit (o mesmo que o GitHub serve no "Download ZIP" do espelho:
# `composer archive` respeita o export-ignore, como o GitHub) é aberto numa
# pasta, e lá dentro roda `docker compose run --rm -T instalar` — o instalador
# em container, com a escolha pelo ambiente (KIT_STACK, KIT_WITHOUT e
# DOCKER_NAME/DOCKER_SLOT → TWS_KIT_NAME/TWS_KIT_SLOT). Os ZIPs dos pacotes
# entram no lugar do Packagist (a pasta deles montada em /packages, o
# repositório `artifact` relativo `../packages` visto de /app). Depois:
# conferências (o projeto do starter com o compose.yaml de desenvolvimento, o
# .env com o nome e as portas, os arquivos com o dono da máquina), `docker
# compose up -d`, o site respondendo em http://<nome>.localhost:<porta> (e
# localhost levando para lá), a suíte dentro do container (DOCKER_PEST=1) e,
# por fim, `docker compose down -v --rmi local` (KEEP_DOCKER=1 mantém no ar).
# =============================================================================
set -eu
# POSIX sh (roda também na imagem PHP Alpine do kit); pipefail onde houver.
(set -o pipefail) 2>/dev/null && set -o pipefail

STEP=${1:-all}
WORK=${2:-${RUNNER_TEMP:-/tmp}/kit-publicado}
VERSION=${VERSION:-2.0.0-beta.1}
STARTER=${STARTER:-livewire}
KIT_STACK=${KIT_STACK:-livewire}
KIT_WITHOUT=${KIT_WITHOUT:-}
case "$STARTER" in
    livewire|react) STACK=$STARTER; KIT_WITHOUT= ;;
    kit)
        case "$KIT_STACK" in
            livewire|react) STACK=$KIT_STACK ;;
            *) echo "interface desconhecida: $KIT_STACK (livewire | react)"; exit 2 ;;
        esac ;;
    *) echo "starter desconhecido: $STARTER (livewire | react | kit)"; exit 2 ;;
esac
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
# A demonstração (packages/demo) NÃO é publicada: nem empacotada, nem no
# starter publicado (prepare-composer.php a tira do require-dev).
PACKAGES="foundation auth accounts uploads admin installer"
# Os módulos opcionais escolhidos (sem o comando único: todos) e os
# desmarcados — que não podem chegar ao projeto.
CHOSEN=
LEFT_OUT=
for modulo in accounts uploads admin; do
    case ",$(printf '%s' "$KIT_WITHOUT" | tr -d ' ')," in
        *",$modulo,"*) LEFT_OUT="$LEFT_OUT $modulo" ;;
        *) CHOSEN="$CHOSEN $modulo" ;;
    esac
done
# Os starters que vão empacotados: o do projeto, ou os dois (comando único).
if [ "$STARTER" = kit ]; then STARTERS="livewire react"; else STARTERS=$STARTER; fi

# Arquivos de uma pasta do monorepo como o git os vê (versionados e novos
# não ignorados): nada gerado localmente — vendor, lock de pacote, build.
copy_tree() {
    from=$1
    to=$2
    mkdir -p "$to"
    (cd "$ROOT" && git ls-files -co --exclude-standard -z -- "$from") \
        | (cd "$ROOT" && tar --null -T - -cf -) \
        | tar -xf - -C "$WORK/tree"
    rm -rf "$to"
    mv "$WORK/tree/$from" "$to"
}

copy() {
    rm -rf "$WORK"
    mkdir -p "$WORK/src" "$WORK/packages" "$WORK/tree"

    for pkg in $PACKAGES; do
        copy_tree "packages/$pkg" "$WORK/src/$pkg"
    done

    # O(s) starter(s) como o repositório só-leitura de cada um vai ficar — e,
    # com o comando único, o twstec/kit.
    for starter in $STARTERS; do
        copy_tree "starters/$starter" "$WORK/src/starter-$starter"
    done
    if [ "$STARTER" = kit ]; then
        copy_tree starters/kit "$WORK/src/kit"
    fi
    rm -rf "$WORK/tree"
}

package() {
    for pkg in $PACKAGES; do
        php "$ROOT/.github/release/prepare-composer.php" "$WORK/src/$pkg" "$VERSION" --set-version
        (cd "$WORK/src/$pkg" && composer archive --format=zip --dir="$WORK/packages" --file="twstec-kit-$pkg-$VERSION" --no-interaction)
    done

    for starter in $STARTERS; do
        php "$ROOT/.github/release/prepare-composer.php" "$WORK/src/starter-$starter" "$VERSION" --set-version
        (cd "$WORK/src/starter-$starter" && composer archive --format=zip --dir="$WORK/packages" --file="twstec-starter-$starter-$VERSION" --no-interaction)
    done
    if [ "$STARTER" = kit ]; then
        php "$ROOT/.github/release/prepare-composer.php" "$WORK/src/kit" "$VERSION" --set-version
        (cd "$WORK/src/kit" && composer archive --format=zip --dir="$WORK/packages" --file="twstec-kit-$VERSION" --no-interaction)
    fi

    ls -la "$WORK/packages"

    # O projeto, como `composer create-project` / `laravel new --using=`. O
    # repositório `artifact` relativo (ver o cabeçalho): daqui ($WORK/src) e do
    # projeto ($WORK/app), `../packages` é a pasta dos ZIPs. Com o comando
    # único, `create-project twstec/kit` — sem terminal, a escolha vai pelo
    # ambiente, e o twstec/kit acha o starter pelo mesmo repositório (o
    # --add-repository o grava no composer.json dele).
    if [ "$STARTER" = kit ]; then
        (cd "$WORK/src" && TWS_KIT_STACK="$KIT_STACK" TWS_KIT_WITHOUT="$KIT_WITHOUT" \
            composer create-project "twstec/kit:$VERSION" "$WORK/app" \
            --repository='{"type":"artifact","url":"../packages"}' --add-repository \
            --no-interaction --no-progress)
    else
        (cd "$WORK/src" && composer create-project "twstec/starter-$STARTER:$VERSION" "$WORK/app" \
            --repository='{"type":"artifact","url":"../packages"}' --add-repository \
            --no-interaction --no-progress)
    fi

    cd "$WORK/app"

    # O projeto é o do starter escolhido (com o comando único, nada do
    # twstec/kit sobra: nem o composer.json, nem o código, nem a pasta
    # temporária).
    grep -q "\"name\": \"twstec/starter-$STACK\"" composer.json
    grep -q "'name' => 'twstec/starter-$STACK'" vendor/composer/installed.php
    if [ -e kit-setup ] || ls -d .tws-kit-starter-* >/dev/null 2>&1; then echo 'sobrou arquivo do twstec/kit no projeto'; exit 1; fi

    # Os módulos DESMARCADOS não chegam ao projeto: nem no composer.json, nem
    # no vendor, nem no registro do Composer.
    for modulo in $LEFT_OUT; do
        if grep -q "\"twstec/kit-$modulo\"" composer.json; then echo "módulo desmarcado no composer.json: $modulo"; exit 1; fi
        if [ -e "vendor/twstec/kit-$modulo" ]; then echo "módulo desmarcado instalado: vendor/twstec/kit-$modulo"; exit 1; fi
        if grep -Eq "\"name\": *\"twstec/kit-$modulo\"" vendor/composer/installed.json; then echo "módulo desmarcado no registro do Composer: $modulo"; exit 1; fi
    done
    echo "módulos opcionais do projeto:${CHOSEN:- nenhum}; de fora:${LEFT_OUT:- nenhum}"

    # Nada aponta para o monorepo: pacotes COPIADOS (não link), sem o que é
    # de desenvolvimento deles, nenhum path repository, nenhum caminho
    # `packages/` no registro do Composer.
    for pkg in foundation auth installer $CHOSEN; do
        test -d "vendor/twstec/kit-$pkg/src"
        test ! -L "vendor/twstec/kit-$pkg"
        for proibido in tests phpunit.xml; do
            if [ -e "vendor/twstec/kit-$pkg/$proibido" ]; then echo "no pacote publicado kit-$pkg: $proibido"; exit 1; fi
        done
    done
    if grep -q '"type": *"path"' composer.json; then echo 'composer.json do projeto com path repository'; exit 1; fi
    # A ORIGEM de cada pacote instalado (dist) é o ZIP, nunca um caminho do
    # monorepo (o texto do link de suporte cita packages/ e não conta): nenhum
    # `path`, e todo endereço relativo é um ZIP do repositório `artifact`.
    if grep -Eq '"type": *"path"' vendor/composer/installed.json; then echo 'vendor apontando para o monorepo (path)'; exit 1; fi
    if grep -E '"url": *"\.\./' vendor/composer/installed.json | grep -Evq '"url": *"\.\./packages/[^"/]+\.zip"'; then echo 'vendor apontando para o monorepo (caminho relativo que não é ZIP)'; exit 1; fi
    grep -q '"type": *"artifact"\|"type": *"zip"' vendor/composer/installed.json
    grep -q '"twstec/kit-foundation": "\^2.0@beta"' composer.json

    # Sem a demonstração: nem no composer.json, nem no vendor, nem nas rotas —
    # "/" é a página inicial do produto (rota `home`).
    if grep -q 'kit-demo' composer.json; then echo 'composer.json publicado cita a demo'; exit 1; fi
    if [ -e vendor/twstec/kit-demo ]; then echo 'projeto publicado com a demo instalada'; exit 1; fi
    php artisan route:list --json | grep -q '"name":"home"'
    if php artisan route:list --json | grep -q '"name":"landing'; then echo 'projeto publicado com as rotas da demo'; exit 1; fi

    # O instalador rodou no post-create-project-cmd: chave da aplicação e,
    # com o pacote de contas, o pepper dedicado das chaves de API (sem ele,
    # não há chave de API nem pepper).
    grep -Eq '^APP_KEY=base64:.+' .env
    case " $CHOSEN " in
        *" accounts "*) grep -Eq '^API_KEYS_HASH_PEPPER=.+' .env ;;
        *) if grep -Eq '^API_KEYS_HASH_PEPPER=.+' .env; then echo 'pepper sem o módulo de contas'; exit 1; fi ;;
    esac

    # Starter React: o projeto é o do React (Inertia, sem Livewire no painel
    # do usuário) e o composer.json publicado é do tipo projeto.
    if [ "$STACK" = react ]; then
        grep -q '"name": "twstec/starter-react"' composer.json
        grep -q '"type": "project"' composer.json
        test -d vendor/inertiajs/inertia-laravel
        test -f resources/js/app.tsx
    fi
}

build() {
    cd "$WORK/app"
    npm ci --ignore-scripts --no-audit --no-fund
    npm run build
}

run_tests() {
    cd "$WORK/app"
    ./vendor/bin/pest --ci
}

# Imagens de PRODUÇÃO do projeto criado (app e nginx), com o Dockerfile e o
# compose PUBLICADOS. O projeto está "sujo" como o de quem desenvolve: .env
# com APP_KEY, banco SQLite, node_modules, public/build, logs e os testes —
# o .dockerignore tem de deixar tudo isso de fora. A conferência é a mesma do
# job de imagens do CI (.github/images/check-<starter>-app.sh).
# O caminho só com o Docker (ver o cabeçalho).
docker_path() {
    if [ "$STARTER" != kit ]; then echo 'a etapa docker é do comando único (STARTER=kit)'; exit 2; fi

    zip="$WORK/packages/twstec-kit-$VERSION.zip"
    folder="$WORK/zip/twstec-kit-main"
    name=${DOCKER_NAME:-sim-$KIT_STACK}
    test -f "$zip"
    rm -rf "$WORK/zip"
    mkdir -p "$folder"
    (cd "$folder" && unzip -q "$zip")

    # O ZIP tem o instalador em container, e não tem a suíte do pacote.
    test -f "$folder/compose.yaml"
    test -f "$folder/docker/instalar/Dockerfile"
    if [ -e "$folder/tests" ]; then echo 'o ZIP do twstec-kit leva a suíte'; exit 1; fi

    cd "$folder"
    # Os ZIPs no lugar do Packagist (o que o --add-repository faria).
    docker compose run --rm -T instalar composer config repositories.zips '{"type":"artifact","url":"../packages"}'

    TWS_KIT_STACK="$KIT_STACK" TWS_KIT_WITHOUT="$KIT_WITHOUT" TWS_KIT_NAME="$name" TWS_KIT_SLOT="${DOCKER_SLOT:-}" \
        docker compose run --rm -T -v "$WORK/packages:/packages:ro" instalar

    # O projeto é o do starter, com o Docker de desenvolvimento, e nada do
    # twstec/kit sobrou.
    grep -q "\"name\": \"twstec/starter-$STACK\"" composer.json
    test -f compose.yaml
    test -f .devcontainer/devcontainer.json
    if [ -e kit-setup ] || [ -e docker/instalar ] || ls -d .tws-kit-starter-* >/dev/null 2>&1; then echo 'sobrou arquivo do twstec/kit no projeto'; exit 1; fi
    grep -qx "COMPOSE_PROJECT_NAME=$name" .env
    grep -Eq '^DB_PASSWORD=[0-9a-f]{32}$' .env
    grep -Eq '^REDIS_PASSWORD=[0-9a-f]{32}$' .env
    grep -Eq '^APP_KEY=base64:.+' .env
    test -f public/build/manifest.json
    for modulo in $LEFT_OUT; do
        if [ -e "vendor/twstec/kit-$modulo" ]; then echo "módulo desmarcado instalado: $modulo"; exit 1; fi
    done

    # Os arquivos são de quem é dono da pasta na máquina (nada de root).
    dono=$(stat -c %u .)
    estranhos=$(find . -path ./node_modules -prune -o ! -user "$dono" -print | head -n 5)
    if [ -n "$estranhos" ]; then echo "arquivos com outro dono: $estranhos"; exit 1; fi

    port=$(sed -n 's/^DEV_SITE_PORT=//p' .env)
    docker compose up -d
    ok=0
    for _ in $(seq 1 90); do
        if [ "$(curl -s -o /dev/null -w '%{http_code}' "http://$name.localhost:$port/up")" = 200 ]; then ok=1; break; fi
        sleep 5
    done
    if [ "$ok" != 1 ]; then docker compose ps -a; docker compose logs --tail 80; echo "o site não respondeu em http://$name.localhost:$port"; exit 1; fi
    echo "site no ar: http://$name.localhost:$port/up → 200"
    test "$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' "http://localhost:$port/login")" = "308 http://$name.localhost:$port/login"
    docker compose ps --format '{{.Service}} {{.State}}'

    if [ "${DOCKER_PEST:-0}" = 1 ]; then
        docker compose exec -T app ./vendor/bin/pest --ci
    fi

    if [ "${KEEP_DOCKER:-0}" != 1 ]; then
        docker compose down -v --rmi local
    fi
    echo "caminho só com o Docker ($KIT_STACK;$CHOSEN): ok"
}

image() {
    cd "$WORK/app"
    tag="kit-publicado-$STARTER-$STACK"

    # Nada do monorepo no compose publicado, e ele resolve (sem o contexto
    # `packages`); o Dockerfile é o do starter, sem mudança.
    if grep -v '^[[:space:]]*#' docker-compose.prod.yml | grep -Eq 'additional_contexts|\.\./\.\./packages'; then
        echo 'docker-compose.prod.yml publicado cita o monorepo'; exit 1
    fi
    PROD_POSTGRES_PASSWORD=simulacao PROD_REDIS_PASSWORD=simulacao \
        docker compose -f docker-compose.prod.yml config --quiet
    if grep -q '"type": *"path"' composer.json; then echo 'composer.json do projeto com path repository'; exit 1; fi

    # O contexto opcional `packages` = os ZIPs (o repositório `artifact`
    # relativo do projeto; no uso real, o Packagist).
    docker build --target prod -f docker/php/Dockerfile \
        --build-context packages="$WORK/packages" -t "$tag-app:sim" .
    docker build --target prod -f docker/nginx/Dockerfile -t "$tag-nginx:sim" .

    KIT_MODULES="foundation auth$CHOSEN" sh "$ROOT/.github/images/check-$STACK-app.sh" "$tag-app:sim"

    # nginx: a configuração de produção carrega e o certificado existe.
    docker run --rm --entrypoint sh "$tag-nginx:sim" -c '
        set -e
        test -f /etc/nginx/certs/server.crt
        test -f /etc/nginx/certs/server.key
        grep -q "listen 443" /etc/nginx/conf.d/default.conf
    '

    if [ "${KEEP_IMAGES:-0}" != 1 ]; then
        docker rmi "$tag-app:sim" "$tag-nginx:sim" >/dev/null
    fi
    echo "imagens de produção do projeto publicado ($STARTER → $STACK;$CHOSEN): conferência ok"
}

case "$STEP" in
    copy) copy ;;
    package) package ;;
    build) build ;;
    test) run_tests ;;
    image) image ;;
    docker) docker_path ;;
    all) copy; package; build; run_tests; image ;;
    *) echo "etapa desconhecida: $STEP"; exit 2 ;;
esac
