# TWS Laravel Starter Kit

Base estrutural reutilizável para projetos Laravel — segurança primeiro, Docker autocontido, convenções rígidas de configuração e testes.

**Stack:** PHP 8.4 · Laravel 13 · PostgreSQL 18 · Redis 8 · Pest 4 · Playwright · nginx+php-fpm · (planejado: Livewire 4 painel cliente, Filament 5 super admin — ADR-011).

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

### Comandos do dia a dia (sempre em container)

```bash
docker compose exec app php artisan <comando>        # artisan
docker compose exec app php artisan test             # testes (Pest 4)
docker run --rm -v $(pwd):/app -w /app composer:latest composer <cmd>

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

## Produção

`docker-compose.prod.yml` é autocontido: em um servidor com Docker instalado,
`docker compose -f docker-compose.prod.yml up -d --build` sobe tudo — app
PHP-FPM com OPcache (imagem imutável), nginx nas portas 80/443 (TLS com
certificado **autoassinado** embutido na imagem), migrate one-shot, queue
worker, scheduler, PostgreSQL e Redis — **banco e Redis sem porta exposta no
host** (rede interna apenas). Nenhuma configuração de SO adicional é exigida
pelo projeto — firewall/DNS são responsabilidade de quem administra o servidor.

Para produção real:

1. `cp .env.prod.example .env.prod` e defina `APP_KEY` (sem ela cada container
   gera uma chave efêmera própria na subida — serve só para testar a stack).
2. Senhas/portas padrão da stack: defina `PROD_*` no shell ou no `.env` da raiz
   (o Compose interpola `${PROD_*}` dali — ver cabeçalho do
   `docker-compose.prod.yml` e `.env.prod.example`).
3. TLS real: monte seus certificados (`server.crt`/`server.key`) em
   `/etc/nginx/certs` — ver comentário no `docker-compose.prod.yml`.

> **Dados NUNCA se perdem ao reiniciar/recriar containers** (ADR-010): banco,
> Redis e assets públicos ficam em volumes nomeados. Nunca use `down -v`.

## Convenções (resumo dos ADRs — lei do projeto)

1. **Nada hardcoded (ADR-007):** nome da plataforma, logo, URLs, CNPJ, e-mail
   de suporte etc. vêm de `config/platform.php` ← `.env` (`PLATFORM_*`).
   Acesso tipado via helper global `platform()` (ex.: `platform()->name`).
   Nunca texto institucional/URL fixa em código ou views.
2. **Dinheiro é inteiro (ADR-004/005):** centavos em `bigint` no banco, cast
   `App\Core\Money\MoneyAsCents` no model. NUNCA float. Conversões só via
   `App\Core\Money\Money` (`Money::format()`, `Money::parse()`,
   `Money::toApiResponse()` — API retorna inteiro canônico + formatado).
3. **Identificadores em 3 camadas (ADR-010):** `id` interno nunca exposto;
   `uuid` (trait nativa `HasUuids`, UUID v7) nas APIs; `codigo_publico`
   legível (`PREFIXO-XXXXXX`) via `App\Core\Identifiers\HasPublicCode` —
   alfabeto sem ambiguidade, constraint UNIQUE + retry
   (`createWithPublicCodeRetry()`).
4. **Respostas de API (ADR-010):** sempre via Resources
   (`App\Core\Http\Resources\BaseResource`) — nunca modelo Eloquent cru.
5. **Logs de requisição (ADR-004/005):** append-only, status
   INICIADA→CONCLUÍDA, ID de correlação e redaction de dados sensíveis (LGPD).
6. **i18n (ADR-007):** locale padrão `pt_BR`; TODA string de UI via `__()`
   apontando para `lang/pt_BR/`. Multi-idioma = adicionar pasta em `lang/`.
7. **Segredos:** somente em `.env` (gitignored), nunca no código nem na imagem.
8. **Testes (ADR-010):** Pest 4 + Playwright, validando **conteúdo** das
   respostas, não apenas status HTTP. A suíte PHP roda com SQLite em memória
   (o phpunit.xml força `DB_*` para isolar do PostgreSQL de dev).

## Segurança e Logs (pipeline de requisição — ADR-004/005/010)

### A cadeia (bootstrap/app.php)

Toda requisição atravessa, nesta ordem:

```
SecurityHeaders → SecurityValidation → RequestLogging → (api: throttle:api) → rota
```

1. **SecurityHeaders** (`app/Core/Security/Middleware/SecurityHeaders.php`) — o mais externo:
   `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, CSP básica
   e HSTS (só sob HTTPS com `SECURITY_HSTS_ENABLED=true`, padrão em produção). Como é o primeiro,
   até respostas de bloqueio/erro saem com os headers. Valores em `config/security.php`.
2. **SecurityValidation** (`.../SecurityValidation.php`) — PRIMEIRA validação: detecta XSS
   (`<script`, `javascript:`, `on*=`), SQLi comum, null bytes e path traversal em query + corpo +
   nomes de arquivos (inclusive URL-encoded). Ao detectar:
   - grava `request_logs` com status **BLOQUEADA**, payload **sanitizado/escapado** (nunca
     executável — ADR-005) + redigido, com metadados (IP, endpoint, `attack_type`);
   - responde **422** com mensagem genérica (não revela o que detectou) + `X-Correlation-Id`.
3. **RequestLogging** (`app/Core/Logging/Middleware/RequestLogging.php`):
   - **No recebimento**: gera/propaga o `correlation_id` (UUID v7; aceita `X-Correlation-Id`
     de entrada se for UUID válido) e grava o log **INICIADA imediatamente**, antes de qualquer
     processamento de negócio, já com payload redigido.
   - **No terminate**: transição controlada para **CONCLUIDA** (HTTP < 500) ou **ERRO**
     (HTTP ≥ 500, com mensagem capturada e redigida), com `duration_ms` e `http_status_response`.
   - É global de propósito, com guarda para `api/*`: middleware de grupo não executa em rota
     não encontrada, e requisição para endpoint inexistente é sinal de varredura (ADR-010).
4. **throttle:api** — rate limit global da API (60/min padrão). Rotas sensíveis (login, códigos
   2FA/verificação) usam `throttle:sensitive` (5/min padrão). Valores em `config/security.php`.

Também: HTTPS forçado em produção (`URL::forceHttps()` no `AppServiceProvider`), CORS restritivo
(`config/cors.php` — nenhuma origem liberada por padrão; `CORS_ALLOWED_ORIGINS` no `.env`).

### Redaction (LGPD — ADR-004)

`App\Core\Logging\Redactor` mascara antes de persistir:

- chaves sensíveis por nome exato (`password`, `token`, `api_key`, `secret`, `authorization`,
  `card_number`, `cvv`...) ou sufixo (`_token`, `_secret`, `_password`, `_api_key`) → `[REDACTED]`;
- CPF/CNPJ em qualquer string → `123.***.***-09` (3 primeiros + 2 últimos dígitos);
- e-mails → `k***@dominio.com`;
- strings gigantes são truncadas (logs não são storage de payload).

### Onde ver os logs

| Camada | Onde | Conteúdo |
|---|---|---|
| Banco (principal) | tabela `request_logs` | ciclo INICIADA→CONCLUIDA/ERRO/BLOQUEADA, payload sanitizado/redigido, duração, IP, tenant |
| Arquivo (sobrevive a falha do banco) | `storage/logs/request-YYYY-MM-DD.log` | JSON estruturado, 1 linha por evento (`request.started`, `request.finished`, `security.blocked`) |
| Borda | access log do nginx | tudo, inclusive health checks |

O `correlation_id` conecta as camadas: resposta (`X-Correlation-Id`), linha do banco e linhas de
arquivo da mesma requisição. Também entra no contexto compartilhado do Monolog
(`Log::shareContext`) — todo `Log::*` emitido durante a requisição o carrega.

### O que significa um log INICIADA "órfão" (ADR-004)

Log que **permanece em INICIADA** = a requisição não chegou ao terminate: processo morto no meio,
timeout fatal, bug que derrubou o worker ou ataque que explorou falha. **É sinal de incidente —
investigar.** Consulta rápida:

```sql
SELECT * FROM request_logs WHERE status = 'INICIADA' AND created_at < now() - interval '5 minutes';
```

Da mesma forma, log **BLOQUEADA** = tentativa de ataque registrada (ver `attack_type`, `ip`,
`endpoint`), e log **sem `tenant_uuid`** (a partir da Fase 4, quando o tenant for resolvido) =
possível tentativa de acesso sem credencial válida.

### Append-only

`request_logs` é imutável pela aplicação: `update()`/`delete()` via Eloquent lançam
`AppendOnlyViolationException`. As únicas mutações são as transições controladas do model
(`markFinished()`, `bindTenant()`/`bindTenantByCorrelationId()` — gancho para a Fase 4 vincular
o tenant ao log quando a secret key for resolvida). Em produção, complementar com
`REVOKE UPDATE, DELETE` da role da aplicação no PostgreSQL.

### Health check

`GET /api/health` → `{data: {status, version, correlation_id}}` (via `BaseResource`).
**Decisão**: excluído do request log em banco para não poluir a trilha (health checks são
barulhentos) — configurável em `REQUEST_LOG_EXCLUDED_PATHS`. Continua protegido por validação
de segurança, headers e rate limit, e fica no access log do nginx.

## Estrutura

```
docker/
  php/Dockerfile       # PHP-FPM 8.4 multi-stage (dev/prod): pgsql, redis,
                       # intl (icu-data-full p/ pt_BR), bcmath, gd, zip,
                       # opcache, pcntl, sqlite (testes)
  php/*.ini            # configs PHP dev/prod + opcache
  nginx/Dockerfile     # nginx dev (HTTP) e prod (HTTPS + headers OWASP)
docker-compose.yml       # DESENVOLVIMENTO
docker-compose.prod.yml  # PRODUÇÃO autocontida
config/platform.php      # config centralizada da plataforma (ADR-007)
lang/pt_BR/              # traduções pt-BR (idioma padrão)
app/
  Core/    # tudo que é genérico e reutilizável: Auth, ApiKeys, Tenancy,
           # Security, Logging, Uploads, Money, Identifiers, Http/Resources,
           # Support (Platform + helpers globais)
  Domain/  # regras de negócio do projeto filho
tests/     # Pest (Unit/Feature) + e2e/ (Playwright)
```

## Branches

`desenvolvimento` → `sandbox` → `producao`. Nunca commit direto nas protegidas.

## Licença

MIT (ver LICENSE).
