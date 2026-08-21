# TWS Laravel Starter Kit

Base estrutural reutilizável para projetos Laravel — segurança primeiro, Docker autocontido, convenções rígidas de configuração e testes.

**Stack:** PHP 8.4 · Laravel 13 · PostgreSQL 18 · Redis 8 · Pest 4 · Playwright · Livewire 4 (painel do usuário) · Filament 5 (super admin) · Horizon (filas) · spatie/laravel-backup (backup → R2) · nginx+php-fpm.

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

# 5) (Opcional) Promover um usuário a super admin do /admin:
docker compose exec app php artisan user:make-admin email@exemplo.com
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

Com a stack de dev no ar, crie o usuário E2E (uma única vez por banco):

```bash
docker compose exec app php artisan tinker --execute='
  \App\Core\Auth\Models\User::factory()->create([
    "email" => "e2e@example.com",
    "password" => "E2eSenhaForte123",
  ]);'
# (credenciais sobreponíveis via E2E_USER_EMAIL / E2E_USER_PASSWORD)
```

Depois rode a suíte:

```bash
# em container (não exige Node local) — o --user evita artefatos
# root-owned (test-results/, tests/e2e/.auth/) no repositório:
docker run --rm --network host --user $(id -u):$(id -g) -e HOME=/tmp \
  -v $(pwd):/work -w /work \
  mcr.microsoft.com/playwright:v1.62.1-noble sh -c "npm install --ignore-scripts && npx playwright test"

# ou localmente, se tiver Node:  npx playwright test
```

## Landing pública, showcase de componentes (/ui) e login demo

A home `/` é uma **landing de vitrine** do kit (hero com screenshot real do
painel, stack, "horas economizadas", grid de 12 features, comando de
instalação copiável e CTA com glow da marca), com layout próprio
(`resources/views/components/layouts/landing.blade.php` — público, sem auth,
tema escuro fixo). Strings em `lang/pt_BR/landing.php`; branding via
`platform()`.

- **Tipografia**: títulos em Space Grotesk Variable (self-hosted via
  `@fontsource-variable/space-grotesk`, token `--font-display`), corpo em
  Instrument Sans.
- **Screenshot do hero**: `public/img/landing/dashboard.png` (commitado).
  Para regerar com a stack dev no ar: `node tests/e2e/capture-hero.js`
  (faz login com o usuário demo e captura o /dashboard em tema escuro).
- **Motion**: tokens de easing/duração em `resources/css/app.css`
  (`--ease-out`, `--ease-in-out`); scroll-reveal discreto via
  IntersectionObserver em `resources/js/ui.js` (`data-reveal`), desligado
  com `prefers-reduced-motion`.

### Showcase de componentes (`/ui`)

Documentação viva dos **componentes Blade do kit** (estilo docs: sidebar
sticky com scrollspy, variantes lado a lado): `<x-button>`, `<x-alert>`,
`<x-badge>`, `<x-input>`, `<x-select>`, `<x-checkbox>`, `<x-toggle>`,
`<x-card>`, `<x-modal>`, `<x-toast>`, `<x-empty-state>`, `<x-spinner>`,
`<x-snippet>` e `<x-ui-icon>` (em `resources/views/components/` — copie e use
em qualquer tela).

- **Snippets copiáveis**: cada variante exibe o código `<x-…>` com botão de
  copiar (clipboard via `data-copy` em `resources/js/ui.js`, feedback no
  próprio botão + toast do kit).
- **Toggle claro/escuro**: prova os dois temas (persiste em `localStorage`
  como `ui-theme`; a landing permanece sempre escura por decisão de design).
- **JS de UI centralizado**: modal (`data-modal-open`/`data-modal-close`),
  toast (`data-toast-show`), copiar, scroll-reveal e scrollspy vivem em
  `resources/js/ui.js`, servido pelo Vite — nada de `<script>` inline nas
  views (CSP-friendly).

Kill switch: `UI_SHOWCASE_ENABLED` (config/ui.php). **Padrão: ligado só em
`APP_ENV=local`**; desabilitado, a rota responde **404**. Em produção,
defina `UI_SHOWCASE_ENABLED=false` (já está no `.env.prod.example`).

> Nota: o nome `<x-icon>` pertence ao pacote `blade-icons` (dependência do
> Filament) — por isso os ícones inline do kit usam `<x-ui-icon>`.

### Login demo (fricção zero em dev)

Quando `DEMO_LOGIN_ENABLED=true` (**padrão só em `APP_ENV=local`**), a tela de
login mostra um aviso e vem com as credenciais demo pré-preenchidas — basta
clicar em "Entrar" (padrão demo.filamentphp.com). O usuário é criado pelo
`DemoUserSeeder`, chamado automaticamente pelo `DatabaseSeeder` quando o flag
está ligado:

```bash
docker compose exec app php artisan migrate --seed   # cria o usuário demo
# demo@tws.dev / demo-password  (sobreponíveis via DEMO_USER_EMAIL/PASSWORD)
```

**NUNCA habilite em produção** — credenciais conhecidas seriam uma backdoor.
Em produção, `DEMO_LOGIN_ENABLED=false` e nada disso aparece na tela.

## Identidade visual / design tokens

Rebranding de um projeto novo = **1 arquivo + .env**:

- **`resources/css/theme.css`** — bloco `@theme` do Tailwind 4 com os tokens da
  linguagem: cor de marca (`--color-brand`), tipografia (`--font-display`,
  `--font-sans`), radii (`--radius-lg/xl`) e motion (`--ease-out`,
  `--ease-in-out`, `--animate-spin/shimmer`). Importado pelo `app.css`.
- **`.env` → `config/platform.php`** — nome (`PLATFORM_NAME`), logo
  (`PLATFORM_LOGO_URL`) e cor primária (`PLATFORM_PRIMARY_COLOR`, injetada em
  runtime como `--brand` no `<head>` — sem rebuild). Acesso tipado via
  `platform()`.

O showcase `/ui` abre com a seção **Tema** mostrando os tokens vivos e como
editá-los. Tema claro/escuro/sistema: toggle de 3 estados nas navs e no Perfil,
padrão = preferência do SO, sem flash no carregamento (script inline mínimo em
`resources/views/partials/theme-script.blade.php`, coberto pela CSP base).

## Produção

`docker-compose.prod.yml` é autocontido: em um servidor com Docker instalado,
`docker compose -f docker-compose.prod.yml up -d --build` sobe tudo — app
PHP-FPM com OPcache (imagem imutável), nginx nas portas 80/443 (TLS com
certificado **autoassinado** embutido na imagem), migrate one-shot,
Horizon (filas), scheduler, PostgreSQL e Redis — **banco e Redis sem porta exposta no
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
`endpoint`), e log **sem `tenant_uuid`** (credencial inválida/ausente — o tenant não foi
resolvido, Fase 4) = possível tentativa de acesso sem credencial válida.

### Append-only

`request_logs` é imutável pela aplicação: `update()`/`delete()` via Eloquent lançam
`AppendOnlyViolationException`. As únicas mutações são as transições controladas do model
(`markFinished()`, `bindTenant()`/`bindTenantByCorrelationId()` — usado pelo middleware
`resolve.tenant` da Fase 4 para vincular o tenant ao log quando a secret key é resolvida).
Em produção, complementar com
`REVOKE UPDATE, DELETE` da role da aplicação no PostgreSQL.

### Health check

`GET /api/health` → `{data: {status, version, correlation_id}}` (via `BaseResource`).
**Decisão**: excluído do request log em banco para não poluir a trilha (health checks são
barulhentos) — configurável em `REQUEST_LOG_EXCLUDED_PATHS`. Continua protegido por validação
de segurança, headers e rate limit, e fica no access log do nginx.

## Autenticação (Fase 3 — ADR-006/010)

Implementação própria e enxuta em `app/Core/Auth/` — **sem** Breeze/Jetstream/Fortify.
Autenticação web por **sessão** (os painéis usam sessão/cookie; a API pública usa
o par de chaves pk_/sk_ no header — ver *API Keys & Tenancy*).

### Model User (`app/Core/Auth/Models/User.php`)

- Identificadores em 3 camadas (ADR-010): `id` interno nunca exposto, `uuid` (HasUuids)
  e `codigo_publico` `USR-xxxxxx` (HasPublicCode).
- **Duas senhas separadas** (ADR-006): `password` (login) e `transaction_password`
  (ações sensíveis), ambas com cast `hashed` → **Argon2id** (checklist item 4;
  `config/hashing.php`, `HASH_DRIVER`). Parâmetros Argon por `.env` (`ARGON_*`);
  `rehash_on_login` faz upgrade gradual de hashes antigos.
- **Dados pessoais criptografados em repouso** (checklist 12): `name` com cast
  `encrypted` (AES-256-GCM da `APP_KEY`). `email` fica em texto (é a chave de lookup
  do login; UNIQUE no banco). Classificação de dados do ADR-006: o que pode ser texto
  é texto; o que exige criptografia é criptografado; segredos ficam só como hash.
- `status` (`UserStatus`): login é **deny-by-default** — só conta `active` autentica.

### Fluxos web (rotas em `routes/web.php`, Form Requests em `Http/Requests`)

| Fluxo | Rotas | Observações |
|---|---|---|
| Registro | `GET/POST /register` | senha forte via config (`AUTH_PASSWORD_MIN`); sessão regenerada |
| Login | `GET/POST /login` | **bloqueio por tentativas** (RateLimiter, e-mail+IP — `AUTH_LOGIN_MAX_ATTEMPTS`/`AUTH_LOGIN_LOCKOUT_MINUTES`); mensagem única anti-enumeração; `session()->regenerate()` (fixation) |
| Logout | `POST /logout` | invalida sessão + renova token CSRF |
| Recuperação | `GET/POST /forgot-password`, `GET/POST /reset-password` | broker nativo do Laravel (token com hash + expiração); resposta uniforme anti-enumeração; `remember_token` renovado no reset |
| Senha de transação | `GET/PUT /settings/transaction-password` | deve ser **diferente** da senha de login; alteração exige a atual |
| Ação sensível | `POST /sensitive-actions/code` + `POST /sensitive-actions/confirm` | ver abaixo |

Todas as rotas sensíveis passam por `throttle:sensitive` (5/min padrão,
`config/security.php`) além dos limites de negócio próprios.

### Ação sensível: senha de transação + código por e-mail (2FA — checklist 24)

Fluxo (ADR-006: saque, rotação de chave de API, alterações críticas):

1. `POST /sensitive-actions/code` com a senha de transação → gera código de
   **6 dígitos**, persiste **somente o hash** (`verification_codes`) com
   expiração (`AUTH_VERIFICATION_CODE_TTL_MINUTES`, padrão 10 min) e envia por
   **e-mail enfileirado** (Redis; Mailpit em dev). Reenvio com cooldown
   (`AUTH_VERIFICATION_CODE_RESEND_COOLDOWN_SECONDS`, padrão 60s); código novo
   invalida os anteriores.
2. `POST /sensitive-actions/confirm` com o código → valida (expiração +
   máx. `AUTH_VERIFICATION_CODE_MAX_ATTEMPTS` tentativas, padrão 5 — ao esgotar,
   o código morre) e emite o **token de ação sensível**: 64 chars aleatórios,
   só hash SHA-256 no banco, curta duração (`AUTH_SENSITIVE_TOKEN_TTL_MINUTES`,
   padrão 10 min), **uso único** (consumido na validação).
3. Rotas de operação sensível usam o middleware **`sensitive.token`**
   (`RequiresSensitiveActionToken`): exige o token no header
   `X-Sensitive-Action-Token` (ou campo `sensitive_action_token`), sempre
   combinado com `auth`:

```php
Route::post('/saque', ...)->middleware(['auth', 'sensitive.token']);
```

**Canais de verificação plugáveis** (TOTP/WhatsApp futuros — ADR-006): contrato
`App\Core\Auth\Contracts\VerificationChannelDriver` + `VerificationChannelManager`.
Hoje só `EmailVerificationDriver`; novo canal = novo driver no mapa + case no enum
`VerificationChannel`, sem tocar no fluxo.

### Sessão e CSRF (checklist 22/23)

- Cookies de sessão: `HttpOnly` + `SameSite=Lax` sempre; `Secure` por padrão em
  produção (`config/session.php` — `SESSION_SECURE_COOKIE`, default
  `APP_ENV=production`). Sessão regenerada no login, invalidada no logout.
- CSRF nativo do grupo `web` (Laravel 13: `PreventRequestForgery` — token +
  validação de origem `Sec-Fetch-Site`/`Origin`), testado com e sem token.
- Credenciais nunca aparecem em logs: `password`, `transaction_password`, `code`
  e afins são `[REDACTED]` pelo Redactor (testado na pipeline de request log).

### Testes

`tests/Feature/Auth/` (Pest): registro, login ok/errado, bloqueio após N
tentativas + liberação após o decay, conta inativa, sessão regenerada, flags do
cookie, deny-by-default, logout, CSRF (419 sem token), recuperação de senha
(`Notification::fake()`), senha de transação (definir/alterar/erros), fluxo
completo de ação sensível (`Mail::fake()` — código válido/inválido/expirado/
tentativas esgotadas, cooldown de reenvio, token uso único/expirado/de outro
usuário).

## API Keys & Tenancy (Fase 4 — ADR-005/006/010)

Motor de chaves de API em `app/Core/ApiKeys/` + resolução de tenant em
`app/Core/Tenancy/`. A autenticação da API é por **par de chaves no header**
(não por sessão):

| Chave | Formato | Papel |
|---|---|---|
| **Pública** | `pk_live_...` / `pk_test_...` | Identificação/lookup (indexada, única). Vai no header `X-Api-Key`. |
| **Secreta** | `sk_live_...` / `sk_test_...` | Credencial. Vai no `Authorization: Bearer`. **Só o hash no banco** — exibida UMA única vez (criação/rotação); perdeu = rotaciona. |

```http
X-Api-Key: pk_test_9fK2...
Authorization: Bearer sk_test_xQ7...
```

O prefixo de ambiente (`live`/`test`) vem de `API_KEYS_ENVIRONMENT`
(`config/api_keys.php` — ADR-007).

### Hash da secreta (checklist item 5) — decisão documentada

**HMAC-SHA256 com pepper** (`ApiKeyHasher`), no espírito do Sanctum (SHA-256):
a sk_ tem ~285 bits de entropia aleatória — KDF lenta (Argon2id) protege
segredos de BAIXA entropia (senhas humanas); aqui só adicionaria latência a
cada request. O pepper (`API_KEYS_HASH_PEPPER`, fallback `APP_KEY`) garante que
vazamento SÓ do banco não permita verificar chaves. Comparação SEMPRE
timing-safe via `hash_equals()`, e pk_ inexistente também passa pela
verificação (hash fictício) para não vazar existência por tempo de resposta.

### Tenancy (ADR-010)

Middleware **`resolve.tenant`** (`ResolveTenant`, grupo `api/v1`): valida o par
pk_/sk_ (existência → hash timing-safe → status → validade → grace de rotação
→ inatividade) → resolve o **tenant** (usuário dono da chave) no container
(helpers `tenant()` / `tenantKey()`, `TenantContext`) e no user resolver da
request → **vincula o request log ao tenant** (`tenant_uuid` = uuid do dono)
→ atualiza `last_used_at` **throttled** (máx. 1 escrita a cada
`API_KEYS_LAST_USED_THROTTLE_SECONDS`, padrão 60s).

**Chave inválida = 401 padronizado** (mensagem única, não oracular) e o request
log permanece **SEM tenant** — exatamente o sinal de ataque/tentativa de burla
do ADR-010 (o log INICIADA é gravado antes, sem vínculo; a identificação falhou).

### Scopes (permissões granulares — ADR-006)

Formato `recurso:acao` (ex.: `customers:read`, `pix:create`, `withdrawals:*`),
jsonb na coluna `scopes`. **Padrão na criação: tudo habilitado (`['*:*']`)** —
o usuário restringe pelo menor privilégio. Wildcards: `*:*` e `recurso:*`.
Checagem no model: `$apiKey->allows('pix:create')`. Proteção de rota:

```php
Route::post('/pix', ...)->middleware('scope:pix:create'); // 403 + scope exigido
```

### Endpoints da API v1 (`routes/api.php`)

Todos sob `resolve.tenant` + scope próprio; `uuid` na URL, nunca `id`
(checklist 11 — recurso de outro tenant = **404 uniforme**, nunca 403).

| Endpoint | Scope | Observação |
|---|---|---|
| `GET /api/v1/api-keys` | `api-keys:read` | lista paginada do tenant |
| `POST /api/v1/api-keys` | `api-keys:create` | **ação sensível** (abaixo); secreta sai 1x no campo `secret_key` |
| `DELETE /api/v1/api-keys/{uuid}` | `api-keys:revoke` | revogação irreversível |
| `POST /api/v1/api-keys/{uuid}/rotate` | `api-keys:rotate` | **ação sensível**; `grace_period_minutes` no corpo |
| `PUT /api/v1/api-keys/{uuid}/projects` | `api-keys:assign` | vínculo N:N (lista vazia = conta toda — ADR-005) |
| `GET/POST /api/v1/projects` + `GET/PUT/DELETE /api/v1/projects/{uuid}` | `projects:*` | CRUD; projeto nasce só com nome (ADR-005) |

**Ação sensível** (criação e rotação de chave — ADR-010): exigem o token de
curta duração da Fase 3 (senha de transação + 2FA por e-mail) no header
`X-Sensitive-Action-Token`, obtido via `POST /sensitive-actions/code` +
`POST /sensitive-actions/confirm` (rotas web, sessão). Uso único.

**Bootstrap (primeira chave):** os endpoints exigem uma chave existente. A
primeira chave do usuário é criada pelo painel (`/api-keys`, Fase 6) ou, em dev, via
`tinker` com o `ApiKeyService`:

```php
app(App\Core\ApiKeys\Services\ApiKeyService::class)
    ->create($user, ['name' => 'Bootstrap']); // retorna a sk_ em claro 1x
```

### Rotação (ADR-006)

Gera substituta herdando nome, scopes e projetos (`rotated_from_id`/
`rotated_to_id` encadeiam). No ato, o usuário escolhe a morte da antiga:
`grace_period_minutes` **nulo/0 = morte imediata**; **positivo = janela de
coexistência** (antiga segue ativa até `grace_ends_at` — troca sem downtime).
Teto em `API_KEYS_MAX_GRACE_MINUTES` (padrão 7 dias).

### Validade e expiração por inatividade (ADR-006)

- **Validade 100% do usuário**: `expires_at` vazio = sem validade; o sistema
  NUNCA impõe prazo.
- **Inatividade**: job diário `api-keys:process-inactivity` (scheduler em
  `routes/console.php`, `daily()` + `withoutOverlapping()` + `onOneServer()`)
  desativa chaves sem uso há `API_KEYS_INACTIVITY_MONTHS` meses (padrão 3) com
  status `expired_inactivity`. **Aviso prévio por e-mail**
  `API_KEYS_INACTIVITY_WARNING_DAYS` dias antes (padrão 7), UMA vez por ciclo —
  a flag `inactivity_warning_sent_at` impede repetição e é rearmada quando a
  chave volta a ser usada. O middleware `resolve.tenant` também rejeita chave
  inativa (defesa em profundidade caso o scheduler atrase). Tudo em UTC.

### Projetos (multi-empresa organizacional — ADR-005)

`projects` (PRJ-xxxxxx): 1 login gerencia N projetos; nascem só com nome. O
vínculo chave↔projeto é **N:N** e **opcional**: chave sem vínculo enxerga a
conta toda; vinculada restringe àqueles projetos. No MVP são metadados
organizacionais — a custódia segue uma por conta.

### Testes

`tests/Feature/ApiKeys/` + `tests/Feature/Tenancy/` (Pest): geração/hash (só
hash no banco, formato por ambiente, pepper), ciclo criar/usar/revogar,
rotação com e sem grace (`travel()`), scopes (exato/wildcards/negado = 403 com
mensagem), validade por data, inatividade (aviso 1x, expiração, rearme,
config off), isolamento de tenant (invisibilidade total + 404 uniforme),
chave inválida = 401 + request log sem tenant, last_used_at throttled e
timing-safe estrutural (`hash_equals`).

## Uploads Seguros (Fase 5 — ADR-010, checklist item 14)

Módulo em `app/Core/Uploads/`. **Função global única**: todo upload do
sistema passa pelo `SecureUploadService::handle()` — nenhum controller faz
`store()` direto de arquivo.

### Política de segurança (lei)

O arquivo é o que os **magic bytes** dizem, NUNCA a extensão declarada.
Pipeline, nesta ordem — **qualquer suspeita = rejeitado**:

1. **Formulário** (delegada ao chamador via Form Request): arquivo presente,
   `mimes:` (o Laravel sniffa o MIME real — corte grosseiro) e tamanho teto.
2. **Segurança do arquivo** (`FileSecurityValidator`):
   - Executável disfarçado (PE `MZ`, ELF, shebang, Java class) → fora;
   - MIME real via `finfo` contra a **allowlist por tipo** (config: imagens
     jpeg/png/webp + pdf por padrão). PDF é só PDF, imagem é só imagem;
   - Extensão declarada divergente do conteúdo real → fora;
   - Varredura de script embutido (`<?php`, `<?=`, `<script`, `#!`) → fora
     (polyglot);
   - PDF com JavaScript/ações automáticas (`/JS`, `/JavaScript`,
     `/OpenAction`, `/AA`) → fora (política do dono: suspeita = não aceita);
   - **Re-encode de imagem via GD: SIM** (decisão documentada). A imagem é
     decodificada e re-gerada do zero antes de persistir — metadados,
     comentários e trailing data (onde payloads se escondem) não sobrevivem.
     Custo irrelevante para os tamanhos do MVP (≤5 MB) e a GD já está no
     container; inclui teto de pixels contra decompression bomb. Falha de
     decode ou GD ausente = falha fechada (rejeita).
3. **Nome seguro**: `uuid` + extensão derivada do MIME REAL. O nome original
   NUNCA compõe o path (guardado sanitizado em `original_name`, só exibição).
4. **Persistência** no disco configurado + registro em `uploads` (uuid,
   `codigo_publico` UPL-xxxxxx, tenant_uuid/user_id, MIME real, tamanho,
   sha256 do conteúdo final) + log estruturado (`upload.stored` /
   `upload.rejected`, sem dados sensíveis).

Arquivo rejeitado **não toca o disco nem o banco** — só o log.

### Como usar o serviço

```php
use App\Core\Uploads\Services\SecureUploadService;

$upload = app(SecureUploadService::class)->handle(
    $request->file('file'),           // UploadedFile (Form Request já validou)
    disk: 's3',                        // opcional — default: config uploads.disk
    directory: 'documentos',           // opcional — default: uploads.directory
    allowedTypes: ['image', 'pdf'],    // opcional — default: uploads.allowed_types
);

$upload->url();  // URL temporária assinada (bucket NUNCA público)
```

Rejeições lançam `UploadRejectedException` (com `reason` estável para logs);
os controllers convertem em 422. Vínculo automático: na API (ResolveTenant)
o registro sai com `tenant_uuid`; na web autenticada, com `user_id`.

### Endpoints de exemplo (prova de reuso)

- `POST /api/v1/uploads` (scope `uploads:create`) — campo `file`, opcional
  `directory`. Retorna o `UploadResource` padronizado (uuid, path, url,
  MIME real, tamanho, sha256).
- `POST /settings/avatar` (web autenticada) — campo `avatar`, restrito a
  imagens (`avatars/`, re-encode GD obrigatório).

### Configuração (Cloudflare R2 — S3-compatível)

O R2 usa o driver `s3` nativo (Flysystem) com `endpoint` customizado —
config padrão do Laravel 13 em `config/filesystems.php`. Em produção:

```dotenv
UPLOADS_DISK=s3
AWS_ACCESS_KEY_ID=<r2_access_key>
AWS_SECRET_ACCESS_KEY=<r2_secret>
AWS_DEFAULT_REGION=auto
AWS_BUCKET=<bucket>
AWS_ENDPOINT=https://<accountid>.r2.cloudflarestorage.com
```

Em dev, `UPLOADS_DISK=local` (ou MinIO apontando o mesmo disco `s3`).
Limites por tipo, tipos permitidos, teto de pixels e validade das URLs
assinadas: seção *Uploads* do `.env.example` → `config/uploads.php`.

### Testes

`tests/Feature/Uploads/` (Pest) com fixtures programáticas em
`tests/Fixtures/uploads.php` (PDF mínimo, PNG 1×1 real, ELF fake — nada de
binário commitado): PDF/imagem legítimos aceitos; PDF com `/JavaScript`,
ELF renomeado `.pdf`, polyglot com PHP embutido, texto disfarçado,
extensão divergente, tamanho acima do limite e decompression bomb
rejeitados; nome seguro sem nome original; sha256 do retorno confere com o
disco; re-encode elimina trailing payload; vínculo tenant (API) vs user_id
(web); scope `uploads:create` exigido.

## Painéis (Fase 6 — ADR-011)

Dois frontends na mesma codebase: **painel do usuário em Livewire 4** e
**super admin em Filament 5**. Branding 100% via `platform()` (nome, logo e
cor primária — `PLATFORM_NAME`/`PLATFORM_LOGO_URL`/`PLATFORM_PRIMARY_COLOR`
no .env; ADR-007/010). Toda string via `__()` (pt-BR). Tema claro/escuro no
painel do usuário (toggle no topo, default = preferência do SO).

### Painel do usuário (Livewire 4)

| Rota | Tela |
|---|---|
| `/dashboard` | Boas-vindas, código público, contadores e ações rápidas |
| `/profile` | Dados, senha de login, senha de transação e avatar (mesma tela) |
| `/api-keys` | Chaves de API: criar (scopes + vínculo N:N com projetos), visualização única da secreta, rotacionar (grace period), revogar |
| `/projects` | Projetos: CRUD só com nome, tudo inline (ADR-005) |
| `/notifications` | Preferências de e-mail (esqueleto p/ notificações de pagamento) |

Princípio de UI (ADR-005): tudo se resolve na MESMA tela — formulários
inline e modais em vez de navegação. Ações sensíveis (criar/rotacionar
chave) abrem o modal de confirmação: senha de transação → código por e-mail
→ executa. Nada de lógica duplicada: as telas consomem `ApiKeyService`
(Fase 4) e `SensitiveActionService` (Fase 3); a senha de transação usa o
`TransactionPasswordService` compartilhado com o controller da Fase 3.

### Super admin (Filament 5) — `/admin`

- **Acesso**: somente `is_admin` + conta ativa (`User::canAccessPanel`) —
  qualquer outro usuário recebe **403**; guest vai ao login do painel.
- **Criar o primeiro admin** (a flag NUNCA é mass-assignable nem editável
  por telas — a única porta é o comando):

```bash
docker compose exec app php artisan user:make-admin email@exemplo.com
# revogar:  ... user:make-admin email@exemplo.com --remove
```

- **Resources**: Usuários (listar/ver/bloquear), Chaves de API (visão
  global de todos os tenants + revogar), Projetos, Request Logs (consulta
  de auditoria read-only com filtros de status/tenant/endpoint/período —
  logs órfãos, sem tenant, destacados em vermelho) e Uploads.
- **Configurações** (`/admin/settings`): parâmetros operacionais editáveis
  pela UI (meses de inatividade p/ expirar chaves, dias de aviso prévio,
  limites de upload, rate limits) gravados na tabela `settings` — sem
  editar .env. Somente a whitelist de `config/settings.php` é gravável;
  campo vazio = volta ao valor do .env. Os overrides são aplicados no boot
  (`SettingsServiceProvider`, cacheados) e lidos pelo helper `setting()`.
- **IP allowlist** (ADR-011, checklist 25 — obrigatória em produção):
  `ADMIN_ALLOWED_IPS` no .env (IPs ou CIDRs separados por vírgula). Vazio =
  sem restrição (apenas desenvolvimento). Middleware: `EnsureAdminIpAllowed`.

### CSP e JavaScript (decisão documentada)

O painel do usuário roda o **bundle CSP-safe do Livewire** (`csp_safe` em
`config/livewire.php`) — a CSP estrita (sem `unsafe-eval`) segue íntegra.
O Filament 5 usa expressões Alpine incompatíveis com esse bundle (modais e
ações não abrem), então SOMENTE as rotas `/admin*`: (1) recebem o bundle
normal do Livewire (middleware `UseEvalBundleForAdmin`, com assets
publicados em `public/vendor/livewire` via `post-install-cmd`) e (2) ganham
`'unsafe-eval'` no `script-src` (SecurityHeaders, configurável por
`SECURITY_CSP_ADMIN`). Mitigação: /admin é painel interno, atrás de
`is_admin` + IP allowlist em produção.

### Testes

`./vendor/bin/pest` (Pest): telas Livewire (renderização + ações com
conteúdo — criar projeto, criar/rotacionar/revogar chave com o fluxo 2FA
real e código capturado do mailable, avatar, preferências, isolamento
anti-IDOR entre tenants), acesso ao /admin (403 a não-admin, comando de
promoção, IP allowlist), resources Filament (listagens, bloquear usuário,
revogar chave, filtros de request logs) e Settings (override, fallback ao
.env, whitelist). E2E Playwright: login → dashboard → chaves de API,
gating do /admin (`tests/e2e/panel.spec.js`).

## Backup e Filas (Fase 7 — ADR-010)

### Backup (spatie/laravel-backup → Cloudflare R2)

**Estratégia em camadas** (a regra: *backup que não restaura não é backup*):

| Camada | Frequência | Ferramenta | Onde |
|---|---|---|---|
| Dump lógico do PostgreSQL | Cron via `.env` (padrão: 1/1h) | `backup:run --only-db` (pg_dump 18, gzip, zip AES-256) | R2 |
| **Validação cruzada produção→sandbox** | A cada dump bem-sucedido | Webhook nativo do pacote → endpoint privado no sandbox | produção→sandbox |
| Health check (idade/tamanho) | Diário (cron via `.env`) | `backup:monitor` | alerta e-mail + webhook |
| Retenção (7d todos → 16d diários → 8 sem. semanais → 4m mensais → 2a anuais) | Diário | `backup:clean` | R2 |
| **PITR/WAL archiving** | Contínuo | **Camada de INFRA** (pgBackRest/WAL-G → R2) — documentada, NÃO implementada na aplicação | R2 |

O dump lógico é portátil e restaurável no sandbox — é ele que alimenta a
validação cruzada. O PITR (RPO de segundos/minutos) é a camada de desastre
da infraestrutura: configurar `archive_command`/pgBackRest no PostgreSQL de
produção é responsabilidade de quem opera o servidor, não do app.

**Configuração** (tudo por `.env` — ver `.env.example`, seção Backups):

- `BACKUP_DISKS` — destino do dump. Dev: `local`. Produção: `backup`
  (disco Flysystem S3 do `config/filesystems.php` que reutiliza as
  credenciais R2 `AWS_*`; bucket dedicado opcional via `BACKUP_R2_BUCKET`).
- `BACKUP_ARCHIVE_PASSWORD` — senha da criptografia do zip (AES-256).
  **Obrigatória em produção** (o dump contém o banco inteiro).
- `BACKUP_RUN_CRON` / `BACKUP_CLEAN_CRON` / `BACKUP_MONITOR_CRON` —
  frequências (UTC). Começar em 1h e reduzir quando o banco crescer (o PITR
  cobre o RPO).
- `BACKUP_ALERT_EMAIL` — destino dos alertas de falha (padrão:
  `PLATFORM_SUPPORT_EMAIL`).
- `BACKUP_WEBHOOK_URL` — URL do webhook da validação cruzada (abaixo).

**Política de notificações** (decisão documentada): sucesso do dump → SÓ
webhook (é o gatilho da validação cruzada; e-mail de sucesso é ruído);
falhas e backup não saudável → e-mail + webhook; rotina boa é silenciosa.

**Comandos** (no container: `docker compose exec app php artisan ...`):

```bash
php artisan backup:run --only-db   # dump manual
php artisan backup:list            # backups existentes, saúde e espaço
php artisan backup:monitor         # health check (idade/tamanho)
```

**Restauração manual** (desastre ou restore no sandbox):

```bash
# 1) Localize o zip (backup:list) e baixe do R2 (ou copie de storage/app/private/<APP_NAME>/)
# 2) Descriptografe/descompacte (a senha é BACKUP_ARCHIVE_PASSWORD):
unzip -P "$BACKUP_ARCHIVE_PASSWORD" backup.zip -d restore/
# 3) Restaure o dump (db-dumps/<database>.sql.gz) em um PostgreSQL 18 vazio:
gunzip -c restore/db-dumps/*.sql.gz | psql -h <host> -U <user> <database>
```

**Contrato do webhook de validação cruzada (produção→sandbox):** a cada
evento, o app faz `POST BACKUP_WEBHOOK_URL` com JSON:

```json
{
  "type": "backup_successful",
  "application_name": "TWS Starter Kit (production)",
  "disk_name": "backup",
  "backup_name": "TWS Starter Kit"
}
```

- `type`: `backup_successful` | `backup_failed` | `cleanup_successful` |
  `cleanup_failed` | `healthy_backup_found` | `unhealthy_backup_found`.
- `backup_failed`/`cleanup_failed` incluem `exception` (mensagem do erro);
  `unhealthy_backup_found` inclui `failures` (lista de motivos).
- O gatilho da validação cruzada é `backup_successful`: o sandbox deve
  baixar o zip mais recente do R2, descriptografar, restaurar em banco
  descartável e rodar as checagens (contagens, integridade) — **o endpoint
  receptor no sandbox NÃO faz parte deste kit** (implementação na fase do
  gatPay; proteger com segredo compartilhado + IP allowlist — não é rota
  pública comum). URL vazia = webhook desativado (nenhuma chamada é feita).

### Filas com Horizon (/horizon)

- **Dashboard `/horizon`**: restrito a `is_admin` (gate `viewHorizon` no
  `HorizonServiceProvider` — fora do ambiente `local`, guest e usuário
  comum recebem 403) + a MESMA IP allowlist do /admin
  (`EnsureAdminIpAllowed` nas rotas do Horizon). CSP própria: a SPA Vue do
  dashboard precisa de `unsafe-eval` e das fontes do fonts.bunny.net —
  liberados SOMENTE nas rotas do Horizon (configurável por
  `SECURITY_CSP_HORIZON`); o resto da aplicação segue com a CSP estrita.
- **Dev**: o serviço `queue` do `docker-compose.yml` segue com
  `queue:work` simples; para ver o dashboard com dados reais, troque o
  comando do serviço por `php artisan horizon` (opcional).
- **Produção**: o serviço `horizon` do `docker-compose.prod.yml` roda
  `php artisan horizon` (supervisor com balanceamento; processos via
  `HORIZON_MAX_PROCESSES`). `tries=1` por padrão — fintech não faz retry
  cego em job financeiro (checklist §7.9).
- Assets: o Horizon serve CSS/JS inline (não exige publicação em
  `public/vendor`).

### Testes

Suíte Pest (Feature): configuração de backup (disco de destino, disco R2,
compressão/criptografia, política de notificações, health check,
agendamentos com `onOneServer`+`withoutOverlapping`) e o contrato do
webhook com `Http::fake` (payload de sucesso + nenhuma chamada com URL
vazia) — sem chamadas reais ao R2. Horizon: gating (guest/usuário comum =
403, admin = 200), IP allowlist aplicada às rotas, CSP dedicada e
supervisores por ambiente.

## Pendências conhecidas (conscientes — não são bugs)

O kit está completo para ser herdado. Os itens abaixo foram **decisões de
escopo documentadas**, a endereçar no projeto filho (gatPay) ou na
infraestrutura:

1. **PITR/WAL archiving → R2** (RPO de segundos): camada de **infraestrutura**
   (pgBackRest/WAL-G no PostgreSQL de produção) — documentada na seção
   *Backup*, intencionalmente fora da aplicação.
2. **Receptor do webhook de validação cruzada no sandbox**: o contrato do
   payload está na seção *Backup*; o endpoint que baixa/restaura/valida o
   dump é responsabilidade do projeto filho.
3. **Canais de verificação TOTP/WhatsApp** (checklist item 24): o contrato
   `VerificationChannelDriver` está pronto; hoje só e-mail.
4. **IP allowlist por chave de API** (checklist item 25 — roadmap ADR-006):
   essencial quando existirem chaves com permissão de saque.
5. **Append-only em nível de banco**: `REVOKE UPDATE, DELETE` da role da
   aplicação no PostgreSQL de produção (a imutabilidade hoje é garantida
   pela aplicação — ver seção *Segurança e Logs*).
6. **Endurecimento da CSP**: remover `'unsafe-inline'` do `script-src` com
   nonces (a CSP atual já não usa `unsafe-eval` fora de /admin e /horizon).
7. **Borda e e-mail (operação, não código)**: WAF Cloudflare no domínio
   (checklist 29) e SPF/DKIM/DMARC (checklist 30).
8. **Octane/FrankenPHP**: reavaliar só com volume relevante e auditoria de
   worker-safety (checklist 28) — php-fpm foi escolha deliberada.
9. **Docs públicas da API**: site estático separado — fora do escopo do kit.

## Estrutura

```
docker/
  php/Dockerfile       # PHP-FPM 8.4 multi-stage (dev/prod): pgsql, redis,
                       # intl (icu-data-full p/ pt_BR), bcmath, gd, zip,
                       # opcache, pcntl, sqlite (testes), pg_dump 18 (backups)
  php/*.ini            # configs PHP dev/prod + opcache
  nginx/Dockerfile     # nginx dev (HTTP) e prod (HTTPS + headers OWASP)
docker-compose.yml       # DESENVOLVIMENTO
docker-compose.prod.yml  # PRODUÇÃO autocontida
config/platform.php      # config centralizada da plataforma (ADR-007)
lang/pt_BR/              # traduções pt-BR (idioma padrão)
app/
  Core/    # tudo que é genérico e reutilizável: Auth, ApiKeys, Tenancy,
           # Security, Logging, Uploads, Money, Identifiers, Http/Resources,
           # Settings (configs editáveis pelo admin), Support (Platform +
           # helpers globais)
  Livewire/   # painel do usuário (Fase 6)
  Filament/   # super admin /admin (Fase 6)
  Domain/  # regras de negócio do projeto filho
tests/     # Pest (Unit/Feature) + e2e/ (Playwright)
```

## Branches

`desenvolvimento` → `sandbox` → `producao`. Nunca commit direto nas protegidas.

## Licença

MIT (ver LICENSE).
