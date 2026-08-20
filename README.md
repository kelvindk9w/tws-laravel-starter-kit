# TWS Laravel Starter Kit

Base estrutural reutilizável para projetos Laravel — segurança primeiro, Docker autocontido, convenções rígidas de configuração e testes.

**Stack:** PHP 8.4 · Laravel 13 · PostgreSQL 18 · Redis 8 · Livewire 4 · Filament 5 · Pest 4 · Playwright · nginx+php-fpm.

## Pré-requisitos

Apenas **Docker** (com Compose v2+). Nada de PHP, Composer ou Node na máquina.

## Clonar e rodar (desenvolvimento)

```bash
git clone <repo> meu-projeto && cd meu-projeto
cp .env.example .env

# 1) Dependências PHP (roda em container, nada local)
docker run --rm -v $(pwd):/app -w /app composer:latest composer install --no-interaction

# 2) Subir a stack
docker compose up -d --build

# 3) Gerar a chave da aplicação no .env e RECRIAR os containers
#    (o compose injeta o .env como variáveis de ambiente no start —
#     editar o .env sem recriar não surte efeito)
docker compose exec app php artisan key:generate --force
docker compose up -d --force-recreate app queue scheduler

# 4) Banco e testes
docker compose exec app php artisan migrate
docker compose exec app ./vendor/bin/pest
```

Aplicação: http://localhost:8180 · Mailpit: http://localhost:18025

Portas conflitando? Ajuste no `.env` (`DEV_WEB_PORT`, `DEV_POSTGRES_PORT`, `DEV_REDIS_PORT`, `DEV_MAILPIT_*`) e recrie os containers.

## Produção

`docker-compose.prod.yml` é autocontido: em um servidor com Docker instalado, `docker compose -f docker-compose.prod.yml up -d` sobe tudo (app, nginx, postgres com volume persistente, redis, filas, scheduler). Nenhuma configuração de SO adicional é exigida pelo projeto — firewall/DNS/HTTPS são responsabilidade de quem administra o servidor.

## Convenções (resumo — ver planejamento/decisoes no projeto de origem)

1. **Nada hardcoded:** nome da plataforma, logo, URLs, dados institucionais → `config/platform.php` + `.env`, acesso via helper `platform()`.
2. **Dinheiro é inteiro** (centavos, bigint) — `App\Core\Money\Money`. Nunca float.
3. **Identificadores:** `id` interno nunca exposto; `uuid` externo; código público legível (`XXX-000000`) via `HasPublicCode`.
4. **Respostas de API** sempre via Resources (`App\Core\Http\Resources`) — nunca modelo cru.
5. **Logs de requisição** append-only com status INICIADA→CONCLUÍDA, ID de correlação e redaction de dados sensíveis (LGPD).
6. **i18n:** toda string de UI via `__()` (pt-BR padrão).
7. **Testes** (Pest + Playwright) validam conteúdo, não só status HTTP.

## Estrutura

- `app/Core/` — tudo que é genérico e reutilizável (Auth, ApiKeys, Tenancy, Security, Logging, Uploads, Money, Identifiers, Resources, Support)
- `app/Domain/` — regras de negócio do projeto filho
- `docker/` — Dockerfiles e configs (php, nginx)

## Branches

`desenvolvimento` → `sandbox` → `producao`. Nunca commit direto nas protegidas.

## Licença

MIT (ver LICENSE).
