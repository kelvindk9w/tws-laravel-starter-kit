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
#           test (pest) | image (docker: imagens de produção e conferências)
#   VERSION (padrão 2.0.0-beta.1)
#   STARTER (padrão livewire): livewire | react — qual starter vira o projeto
#   (twstec/starter-livewire ou twstec/starter-react). Os pacotes são os mesmos.
# =============================================================================
set -eu
# POSIX sh (roda também na imagem PHP Alpine do kit); pipefail onde houver.
(set -o pipefail) 2>/dev/null && set -o pipefail

STEP=${1:-all}
WORK=${2:-${RUNNER_TEMP:-/tmp}/kit-publicado}
VERSION=${VERSION:-2.0.0-beta.1}
STARTER=${STARTER:-livewire}
case "$STARTER" in
    livewire|react) ;;
    *) echo "starter desconhecido: $STARTER (livewire | react)"; exit 2 ;;
esac
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
# A demonstração (packages/demo) NÃO é publicada: nem empacotada, nem no
# starter publicado (prepare-composer.php a tira do require-dev).
PACKAGES="foundation auth accounts uploads admin installer"

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

    # O starter como o repositório só-leitura dele vai ficar.
    copy_tree "starters/$STARTER" "$WORK/src/starter"
    rm -rf "$WORK/tree"
}

package() {
    for pkg in $PACKAGES; do
        php "$ROOT/.github/release/prepare-composer.php" "$WORK/src/$pkg" "$VERSION" --set-version
        (cd "$WORK/src/$pkg" && composer archive --format=zip --dir="$WORK/packages" --file="twstec-kit-$pkg-$VERSION" --no-interaction)
    done

    php "$ROOT/.github/release/prepare-composer.php" "$WORK/src/starter" "$VERSION" --set-version
    (cd "$WORK/src/starter" && composer archive --format=zip --dir="$WORK/packages" --file="twstec-starter-$STARTER-$VERSION" --no-interaction)

    ls -la "$WORK/packages"

    # O projeto, como `composer create-project` / `laravel new --using=`. O
    # repositório `artifact` relativo (ver o cabeçalho): daqui ($WORK/src) e do
    # projeto ($WORK/app), `../packages` é a pasta dos ZIPs.
    (cd "$WORK/src" && composer create-project "twstec/starter-$STARTER:$VERSION" "$WORK/app" \
        --repository='{"type":"artifact","url":"../packages"}' --add-repository \
        --no-interaction --no-progress)

    cd "$WORK/app"

    # Nada aponta para o monorepo: pacotes COPIADOS (não link), sem o que é
    # de desenvolvimento deles, nenhum path repository, nenhum caminho
    # `packages/` no registro do Composer.
    for pkg in $PACKAGES; do
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
    # com o pacote de contas, o pepper dedicado das chaves de API.
    grep -Eq '^APP_KEY=base64:.+' .env
    grep -Eq '^API_KEYS_HASH_PEPPER=.+' .env

    # Starter React: o projeto é o do React (Inertia, sem Livewire no painel
    # do usuário) e o composer.json publicado é do tipo projeto.
    if [ "$STARTER" = react ]; then
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
image() {
    cd "$WORK/app"
    tag="kit-publicado-$STARTER"

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

    sh "$ROOT/.github/images/check-$STARTER-app.sh" "$tag-app:sim"

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
    echo "imagens de produção do projeto publicado ($STARTER): conferência ok"
}

case "$STEP" in
    copy) copy ;;
    package) package ;;
    build) build ;;
    test) run_tests ;;
    image) image ;;
    all) copy; package; build; run_tests; image ;;
    *) echo "etapa desconhecida: $STEP"; exit 2 ;;
esac
