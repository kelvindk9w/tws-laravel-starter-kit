#!/bin/sh
# =============================================================================
# Conferência da imagem de PRODUÇÃO do app do starter React
# (starters/react/docker/php/Dockerfile, target prod).
#
#   sh .github/images/check-react-app.sh <imagem>
#
# As mesmas conferências da imagem do starter Livewire (job "Imagens de
# produção" do .github/workflows/ci.yml), mais as do front React. Prova
# mínima de que a imagem é utilizável e de que nada que não deveria viajar foi
# junto: arquivo de ambiente, testes (inclusive o E2E), storage local, banco
# SQLite, a demonstração e o instalador. Qualquer desvio = saída diferente de 0.
# Roda no CI (job "Imagens de produção do starter React", a imagem construída
# no monorepo; e a simulação da instalação publicada, a imagem do projeto
# criado pelo create-project — .github/release/simulate-install.sh) e
# localmente.
# =============================================================================
set -eu

IMAGE="${1:?informe a imagem: sh .github/images/check-react-app.sh <imagem>}"
# Os módulos do kit que o projeto TEM (os outros não podem estar na imagem).
# Padrão: todos — o starter do monorepo. A simulação da instalação publicada
# pelo comando único (`create-project twstec/kit`) passa a escolha dela.
KIT_MODULES="${KIT_MODULES:-foundation auth accounts uploads admin}"
export KIT_MODULES

# Extensões: filas (pcntl) e imagem/MIME real do twstec/kit-uploads.
for ext in pcntl gd fileinfo; do
    if ! docker run --rm --entrypoint php "$IMAGE" -m | grep -qx "$ext"; then
        echo "na imagem: falta a extensão $ext"
        exit 1
    fi
done

docker run --rm -e KIT_MODULES --entrypoint sh "$IMAGE" -c '
    set -e
    cd /var/www/html
    # O processo não roda como root.
    test "$(id -u)" != 0

    # Nada de ambiente, testes, ferramentas de teste nem node_modules.
    for proibido in .env .env.example .env.prod.example tests phpunit.xml phpunit.pgsql.xml \
        playwright.config.ts playwright.config.js node_modules public/hot bootstrap/ssr \
        docker-compose.yml docker-compose.prod.yml docker; do
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
    for modulo in $KIT_MODULES; do
        pacote="kit-$modulo"
        test -d "vendor/twstec/$pacote/src"
        test ! -L "vendor/twstec/$pacote"
        for proibido in tests vendor composer.lock phpunit.xml; do
            if [ -e "vendor/twstec/$pacote/$proibido" ]; then echo "no pacote $pacote: $proibido"; exit 1; fi
        done
    done

    # Os módulos que o projeto NÃO tem não viajam: nem o pacote, nem o
    # registro dele no Composer.
    for modulo in accounts uploads admin; do
        case " $KIT_MODULES " in *" $modulo "*) continue ;; esac
        if [ -e "vendor/twstec/kit-$modulo" ]; then echo "na imagem: vendor/twstec/kit-$modulo (módulo não escolhido)"; exit 1; fi
        if grep -Eq "\"name\": *\"twstec/kit-$modulo\"" vendor/composer/installed.json; then echo "na imagem: twstec/kit-$modulo instalado (módulo não escolhido)"; exit 1; fi
    done

    # O tema do /admin é compilado com as fontes do twstec/kit-admin.
    case " $KIT_MODULES " in *" admin "*) test -f vendor/twstec/kit-admin/resources/css/sources.css ;; esac

    # A demonstração e o instalador (require-dev) não vão para produção.
    for pacote in kit-demo kit-installer; do
        if [ -e "vendor/twstec/$pacote" ]; then echo "na imagem: vendor/twstec/$pacote"; exit 1; fi
        if grep -q "twstec/$pacote" vendor/composer/installed.json; then echo "na imagem: twstec/$pacote instalado"; exit 1; fi
    done
    if [ -n "$(find /var/www/html -path /var/www/html/vendor -prune -o -name "*.php" -print | xargs grep -l "Twstec.Kit.Demo" 2>/dev/null)" ]; then
        echo "na imagem: código que nomeia a demo"; exit 1
    fi

    # O front React compilado: a entrada do Inertia, o CSS do painel e o tema
    # do /admin (o painel está instalado nesta imagem).
    test -f public/build/manifest.json
    grep -q "\"resources/js/app.tsx\"" public/build/manifest.json
    grep -q "\"resources/css/app.css\"" public/build/manifest.json
    case " $KIT_MODULES " in
        *" admin "*) grep -q "\"resources/css/filament.css\"" public/build/manifest.json ;;
        *) if grep -q "\"resources/css/filament.css\"" public/build/manifest.json; then echo "na imagem: tema do /admin sem o /admin"; exit 1; fi ;;
    esac
    if grep -q "kit-demo" public/build/manifest.json; then echo "na imagem: assets da demo no build"; exit 1; fi

    # Sobe: o artisan responde e as rotas são as do produto (nada da demo).
    php artisan --version
    rotas="$(php artisan route:list --json)"
    echo "$rotas" | grep -q "\"name\":\"dashboard\""
    if echo "$rotas" | grep -q "landing"; then echo "na imagem: rotas da demo"; exit 1; fi
'

echo "imagem do app do starter React: conferência ok"
