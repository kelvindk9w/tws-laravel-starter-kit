# TWS Laravel Starter Kit

Base estrutural para aplicações Laravel do ecossistema TWS/gatPay (ADR-011).
PHP 8.4 · Laravel 13 · PostgreSQL 18 · Redis 8 · Docker (nginx + php-fpm) · Pest 4 + Playwright.

## Subir o ambiente (dev)

```bash
cp .env.example .env   # ajuste se necessário
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Aplicação em `http://localhost:8180` (nginx). Mailpit em `http://localhost:18025`.

## Testes

```bash
docker compose exec app ./vendor/bin/pest
```

## Convenções (leis do projeto)

- ADRs em `gatPay/planejamento/decisoes/` são lei (multi-PSP, logs, idempotência, identificadores...).
- `declare(strict_types=1)` em todo arquivo; código em inglês, docblocks em português.
- Config centralizada (ADR-007): dados da plataforma SEMPRE via `config/platform.php` + `.env`,
  acessados pelo helper `platform()`. Nada hardcoded.
- Identificadores (ADR-010): `id` interno nunca exposto; `uuid` externo; `codigo_publico` legível
  (`HasPublicCode`). Dinheiro SEMPRE em centavos (`app/Core/Money`).
- Respostas de API sempre via Resources (`BaseResource`) — nunca model cru.
- Testes validam CONTEÚDO da resposta, não só status HTTP (ADR-010).

---

## Segurança e Logs (pipeline de requisição)

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
3. **RequestLogging** (`app/Core/Logging/Middleware/RequestLogging.php`) — ADR-004:
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
