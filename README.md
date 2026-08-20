# TWS Laravel Starter Kit

Starter kit Laravel para projetos TWS — base estrutural com Docker, convenções
de segurança e padrões de engenharia definidos nos ADRs do projeto.

**Stack:** PHP 8.4 · Laravel 13 · PostgreSQL 18 · Redis 8 · nginx + PHP-FPM ·
Pest 4 · Playwright · Mailpit (dev)

---

## Pré-requisitos

- **Docker** (com Compose v2+). Nada mais: PHP, Composer e Node rodam em containers.

## Subindo o ambiente de DESENVOLVIMENTO

```bash
cp .env.example .env          # primeira vez apenas
docker compose up -d --build

# dependências PHP (roda em container — a máquina não precisa de PHP/Composer):
docker run --rm -v $(pwd):/app -w /app composer:latest composer install

docker compose exec app php artisan key:generate   # primeira vez apenas
docker compose exec app php artisan migrate
```

Serviços de dev (portas do host configuráveis via `.env`, ver `DEV_*_PORT`):

| Serviço | Onde |
|---|---|
| Aplicação (nginx → php-fpm) | http://localhost:8180 |
| Mailpit (caixa de entrada fake) | http://localhost:18025 (SMTP na 11025) |
| PostgreSQL 18 | localhost:15432 |
| Redis 8 | localhost:16379 |

Também sobem automaticamente: **queue worker** (`queue:work`) e **scheduler**
(loop de `schedule:run` a cada minuto).

### Comandos do dia a dia (sempre em container)

```bash
docker compose exec app php artisan <comando>          # artisan
docker compose exec app php artisan test               # testes (Pest 4)
docker run --rm -v $(pwd):/app -w /app composer:latest composer <cmd>   # composer

# Build do frontend (Node 24 em container):
docker run --rm -v $(pwd):/app -w /app node:24-alpine npm install
docker run --rm -v $(pwd):/app -w /app node:24-alpine npm run build
```

### Testes E2E (Playwright)

Com a stack de dev no ar:

```bash
# em container (não exige Node local):
docker run --rm --network host -v $(pwd):/work -w /work \
  mcr.microsoft.com/playwright:v1.62.1-noble sh -c "npm install --ignore-scripts && npx playwright test"

# ou localmente, se tiver Node:  npx playwright test
```

## Subindo o ambiente de PRODUÇÃO (autocontido)

Em qualquer host com Docker instalado, sem nenhuma configuração de SO:

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

Sobe: nginx (80/443, TLS com certificado **autoassinado** embutido na imagem),
app PHP-FPM com OPcache, migrate (one-shot), queue worker, scheduler,
PostgreSQL e Redis — **banco e Redis sem porta exposta no host** (rede interna).

Para produção real:

1. `cp .env.prod.example .env.prod` e defina `APP_KEY` (sem ela cada container
   gera uma chave efêmera própria — só para teste da stack).
2. Senhas/portas padrão: defina `PROD_*` no shell ou no `.env` da raiz
   (o Compose interpola `${PROD_*}` dali — ver cabeçalho do
   `docker-compose.prod.yml` e `.env.prod.example`).
3. TLS real: monte seus certificados em `/etc/nginx/certs`
   (`server.crt`/`server.key`) — ver comentário no `docker-compose.prod.yml`.

> **Dados NUNCA se perdem ao reiniciar/recriar containers** (ADR-010): banco,
> Redis e assets ficam em volumes nomeados. Nunca use `down -v`.

## Estrutura

```
docker/
  php/Dockerfile       # PHP-FPM 8.4 multi-stage (dev/prod): pgsql, redis,
                       # intl, bcmath, gd, zip, opcache, pcntl, sqlite (testes)
  php/*.ini            # configs PHP dev/prod + opcache
  nginx/Dockerfile     # nginx dev (HTTP) e prod (HTTPS + headers OWASP)
docker-compose.yml       # DESENVOLVIMENTO
docker-compose.prod.yml  # PRODUÇÃO autocontida
app/
  Core/                # fundações reutilizáveis (convenções abaixo)
    Support/           # Platform (config tipada) + helpers globais
    Identifiers/       # HasPublicCode (PREFIXO-XXXXXX)
    Money/             # MoneyAsCents + Money (int centavos, nunca float)
    Http/Resources/    # BaseResource (padronização de API)
    Auth/ ApiKeys/ Tenancy/ Security/ Logging/ Uploads/   # (fases futuras)
  Domain/              # domínios de negócio (fases futuras)
config/platform.php    # config centralizada da plataforma (ADR-007)
lang/pt_BR/            # traduções pt-BR (idioma padrão)
tests/                 # Pest (Unit/Feature) + e2e/ (Playwright)
```

## Convenções (resumo dos ADRs — lei do projeto)

1. **Nada hardcoded (ADR-007):** nome da plataforma, logo, URLs, CNPJ, e-mail
   de suporte etc. vêm de `config/platform.php` ← `.env` (`PLATFORM_*`).
   Acesso tipado via helper global `platform()` (ex.: `platform()->name`).
   Nunca texto institucional/URL fixa em código ou views.
2. **i18n (ADR-007):** locale padrão `pt_BR`; TODA string de UI via `__()`
   apontando para `lang/pt_BR/`. Multi-idioma = adicionar pasta em `lang/`.
3. **Identificadores em 3 camadas (ADR-010):** `id` interno nunca exposto;
   `uuid` (trait nativa `HasUuids`, UUID v7) nas APIs; `codigo_publico`
   legível (`PREFIXO-XXXXXX`) via trait `App\Core\Identifiers\HasPublicCode`
   — alfabeto sem ambiguidade, unicidade garantida por constraint UNIQUE +
   retry (`createWithPublicCodeRetry()`).
4. **Dinheiro (ADR-004/005):** SEMPRE inteiro em centavos (`bigint` no banco,
   cast `App\Core\Money\MoneyAsCents` no model). NUNCA float. Conversões só
   via `App\Core\Money\Money` (`Money::format()`, `Money::parse()`,
   `Money::toApiResponse()` — API retorna inteiro canônico + formatado).
5. **API (ADR-010):** nenhum endpoint retorna modelo Eloquent cru — toda
   entidade tem seu Resource estendendo `App\Core\Http\Resources\BaseResource`.
6. **Segredos:** somente em `.env` (gitignored), nunca no código nem na imagem.
7. **Git flow (ADR-010):** `desenvolvimento` → `sandbox` → `producao`.
   Nunca direto para produção.

## Testes

```bash
docker compose exec app php artisan test   # Pest 4 (unit + feature)
npx playwright test                        # E2E (ver seção acima)
```

Os testes rodam com SQLite em memória (phpunit.xml força `DB_*` para isolar
do PostgreSQL de dev) e validam **conteúdo** das respostas, não só status.
