#!/bin/sh
# =============================================================================
# Conferência da imagem de PRODUÇÃO do app do starter Livewire
# (starters/livewire/docker/php/Dockerfile, target prod).
#
#   sh .github/images/check-livewire-app.sh <imagem>
#
# Prova mínima de que a imagem é utilizável e de que nada que não deveria
# viajar foi junto: arquivo de ambiente, testes, storage local, banco SQLite,
# a demonstração e o instalador. Qualquer desvio = saída diferente de 0.
# Roda no CI (job "Imagens de produção", a imagem construída no monorepo; e a
# simulação da instalação publicada, a imagem do projeto criado pelo
# create-project — .github/release/simulate-install.sh) e localmente.
# =============================================================================
set -eu

IMAGE="${1:?informe a imagem: sh .github/images/check-livewire-app.sh <imagem>}"

# Extensões: filas (pcntl) e imagem/MIME real do twstec/kit-uploads.
for ext in pcntl gd fileinfo; do
    if ! docker run --rm --entrypoint php "$IMAGE" -m | grep -qx "$ext"; then
        echo "na imagem: falta a extensão $ext"
        exit 1
    fi
done

docker run --rm --entrypoint sh "$IMAGE" -c '
    set -e
    cd /var/www/html
    # O processo não roda como root.
    test "$(id -u)" != 0

    # Nada de ambiente, testes nem node_modules.
    for proibido in .env .env.example .env.prod.example tests phpunit.xml phpunit.pgsql.xml \
        playwright.config.js node_modules public/hot docker-compose.yml docker-compose.prod.yml docker; do
        if [ -e "/var/www/html/$proibido" ]; then echo "na imagem: $proibido"; exit 1; fi
    done

    # Storage sem nenhum arquivo (uploads, sessões, logs, backups de quem fez o build).
    test -z "$(find /var/www/html/storage -type f)"

    # Nenhum banco SQLite fora do vendor (o banco local de quem fez o build).
    if [ -n "$(find /var/www/html -path /var/www/html/vendor -prune -o \( -name "*.sqlite" -o -name "*.sqlite-*" -o -name "*.sqlite3" \) -print)" ]; then
        echo "na imagem: banco SQLite"; exit 1
    fi

    # Os pacotes que vieram de fora do contexto (monorepo: packages/;
    # simulação da publicação: os ZIPs) ficam no estágio do Composer.
    if [ -e /packages ]; then echo "na imagem: /packages"; exit 1; fi

    # Pacotes do kit COPIADOS para o vendor (nunca link para fora da imagem)
    # e sem o que é de desenvolvimento deles.
    for pacote in kit-foundation kit-auth kit-accounts kit-uploads kit-admin; do
        test -d "vendor/twstec/$pacote/src"
        test ! -L "vendor/twstec/$pacote"
        for proibido in tests vendor composer.lock phpunit.xml; do
            if [ -e "vendor/twstec/$pacote/$proibido" ]; then echo "no pacote $pacote: $proibido"; exit 1; fi
        done
    done

    # O tema do painel é compilado pelo app com as fontes do twstec/kit-admin
    # (resources/css/sources.css): o pacote precisa levar os resources dele
    # para a imagem.
    test -f vendor/twstec/kit-admin/resources/css/sources.css

    # A DEMONSTRAÇÃO (twstec/kit-demo, require-dev) e o INSTALADOR
    # (twstec/kit-installer, require-dev) NÃO vão para produção: nem o
    # pacote, nem o registro dele no Composer; da demo, nem as entradas das
    # landings e as imagens no build do frontend, nem as rotas.
    for pacote in kit-demo kit-installer; do
        if [ -e "vendor/twstec/$pacote" ]; then echo "na imagem: vendor/twstec/$pacote"; exit 1; fi
        if grep -q "twstec/$pacote" vendor/composer/installed.json; then echo "na imagem: twstec/$pacote instalado"; exit 1; fi
    done
    if grep -q "kit-demo" public/build/manifest.json; then echo "na imagem: assets da demo no build"; exit 1; fi
    if [ -e public/build/assets/img ]; then echo "na imagem: imagens da demo no build"; exit 1; fi
    if [ -n "$(find /var/www/html -path /var/www/html/vendor -prune -o -name "*.php" -print | xargs grep -l "Twstec.Kit.Demo" 2>/dev/null)" ]; then
        echo "na imagem: código que nomeia a demo"; exit 1
    fi

    # Sobe: o artisan responde e nenhuma rota é da demo.
    php artisan --version
    if php artisan route:list --json | grep -q "landing"; then echo "na imagem: rotas da demo"; exit 1; fi
'

echo "imagem do app do starter Livewire: conferência ok"
