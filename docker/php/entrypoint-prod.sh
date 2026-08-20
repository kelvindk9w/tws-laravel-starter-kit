#!/bin/sh
# Entrypoint de produção (containers app/queue/scheduler).
# - Gera APP_KEY na primeira subida caso não tenha sido informada via ambiente.
#   Recomendado: definir APP_KEY no .env para que recriações de container
#   não invalidem sessões e dados criptografados.
# - Garante o symlink de storage.
set -e

cd /var/www/html

if [ -z "$APP_KEY" ] && [ ! -s .env ]; then
    echo "[entrypoint] APP_KEY ausente e sem .env: gerando chave efemera (defina APP_KEY no ambiente!)."
    php artisan key:generate --force --no-interaction
fi

php artisan storage:link --no-interaction 2>/dev/null || true

exec "$@"
