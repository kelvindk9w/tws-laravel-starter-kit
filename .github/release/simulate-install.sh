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
# o build do front e a suíte do starter.
#
# Uso: simulate-install.sh [etapa] [pasta]
#   etapas: all | copy (git + tar) | package (php + composer) | build (npm) | test (pest)
#   VERSION (padrão 2.0.0-beta.1)
# =============================================================================
set -eu
# POSIX sh (roda também na imagem PHP Alpine do kit); pipefail onde houver.
(set -o pipefail) 2>/dev/null && set -o pipefail

STEP=${1:-all}
WORK=${2:-${RUNNER_TEMP:-/tmp}/kit-publicado}
VERSION=${VERSION:-2.0.0-beta.1}
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
    mkdir -p "$WORK/src" "$WORK/artifacts" "$WORK/tree"

    for pkg in $PACKAGES; do
        copy_tree "packages/$pkg" "$WORK/src/$pkg"
    done

    # O starter como o repositório só-leitura dele vai ficar.
    copy_tree starters/livewire "$WORK/src/starter"
    rm -rf "$WORK/tree"
}

package() {
    for pkg in $PACKAGES; do
        php "$ROOT/.github/release/prepare-composer.php" "$WORK/src/$pkg" "$VERSION" --set-version
        (cd "$WORK/src/$pkg" && composer archive --format=zip --dir="$WORK/artifacts" --file="twstec-kit-$pkg-$VERSION" --no-interaction)
    done

    php "$ROOT/.github/release/prepare-composer.php" "$WORK/src/starter" "$VERSION" --set-version
    (cd "$WORK/src/starter" && composer archive --format=zip --dir="$WORK/artifacts" --file="twstec-starter-livewire-$VERSION" --no-interaction)

    ls -la "$WORK/artifacts"

    # O projeto, como `composer create-project` / `laravel new --using=`.
    composer create-project "twstec/starter-livewire:$VERSION" "$WORK/app" \
        --repository="{\"type\":\"artifact\",\"url\":\"$WORK/artifacts\"}" --add-repository \
        --no-interaction --no-progress

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
    # monorepo (o texto do link de suporte cita packages/ e não conta).
    if grep -Eq '"type": *"path"|"url": *"\.\./' vendor/composer/installed.json; then echo 'vendor apontando para o monorepo (path)'; exit 1; fi
    grep -q '"type": *"artifact"\|"type": *"zip"' vendor/composer/installed.json
    grep -q '"twstec/kit-foundation": "\^2.0@beta"' composer.json

    # Sem a demonstração: nem no composer.json, nem no vendor, nem nas rotas —
    # "/" é a página inicial do produto (rota `home`).
    if grep -q 'kit-demo' composer.json; then echo 'composer.json publicado cita a demo'; exit 1; fi
    if [ -e vendor/twstec/kit-demo ]; then echo 'projeto publicado com a demo instalada'; exit 1; fi
    php artisan route:list --json | grep -q '"name":"home"'
    if php artisan route:list --json | grep -q '"name":"landing'; then echo 'projeto publicado com as rotas da demo'; exit 1; fi
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

case "$STEP" in
    copy) copy ;;
    package) package ;;
    build) build ;;
    test) run_tests ;;
    all) copy; package; build; run_tests ;;
    *) echo "etapa desconhecida: $STEP"; exit 2 ;;
esac
