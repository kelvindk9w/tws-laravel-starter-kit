#!/bin/sh
# Entrypoint de produção (containers app/queue/scheduler).
# - Gera APP_KEY na primeira subida caso não tenha sido informada via ambiente.
#   Recomendado: definir APP_KEY no .env para que recriações de container
#   não invalidem sessões e dados criptografados.
# - Garante o symlink de storage.
set -e

cd /var/www/html

if [ -z "$APP_KEY" ]; then
    echo "[entrypoint] APP_KEY ausente: gerando chave efemera (defina APP_KEY no .env.prod!)."
    grep -q '^APP_KEY=' .env 2>/dev/null || echo 'APP_KEY=' >> .env
    php artisan key:generate --force --no-interaction
fi

php artisan storage:link --no-interaction 2>/dev/null || true

# Publica os arquivos estáticos no volume compartilhado com o nginx
# (o nginx de produção não tem o código — recebe só o public/ read-only).
if [ -d /app-public ]; then
    cp -r /var/www/html/public/. /app-public/ 2>/dev/null || true
fi

exec "$@"
