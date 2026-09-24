# API e chaves de API

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
(`config/api_keys.php`).

**Dono da chave precisa estar ativo e com o e-mail confirmado.** A cada
chamada o `ResolveTenant` confere o dono: conta bloqueada/pendente ou, com a
verificação de e-mail ligada (padrão), conta sem e-mail confirmado recebe o
mesmo 401 mudo de chave inválida. Na prática uma conta nova nem chega a ter
chave antes de confirmar — chaves só nascem pelo painel, que exige a
confirmação; a checagem na API cobre a conta que já tinha chave quando a
exigência foi ligada. Ver
[Verificação de e-mail](autenticacao.md#verificação-de-e-mail-no-cadastro).

## Hash da secreta — decisão documentada

**HMAC-SHA256 com pepper** (`ApiKeyHasher`), no espírito do Sanctum (SHA-256):
a sk_ tem ~285 bits de entropia aleatória — KDF lenta (Argon2id) protege
segredos de BAIXA entropia (senhas humanas); aqui só adicionaria latência a
cada request. O pepper (`API_KEYS_HASH_PEPPER`, fallback `APP_KEY`) garante que
vazamento SÓ do banco não permita verificar chaves. Comparação SEMPRE
timing-safe via `hash_equals()`, e pk_ inexistente também passa pela
verificação (hash fictício) para não vazar existência por tempo de resposta.

## Scopes (permissões granulares)

Formato `recurso:acao` (ex.: `customers:read`, `pix:create`, `withdrawals:*`),
jsonb na coluna `scopes`. **Padrão na criação: tudo habilitado (`['*:*']`)** —
o usuário restringe pelo menor privilégio. Wildcards: `*:*` e `recurso:*`.
Checagem no model: `$apiKey->allows('pix:create')`. Proteção de rota:

```php
Route::post('/pix', ...)->middleware('scope:pix:create'); // 403 + scope exigido
```

## Contrato de resposta da API (sucesso e erro)

**Sucesso** — sempre envelopado em `data`, via Resources
(`App\Core\Http\Resources\BaseResource`); listagens paginadas acrescentam
`links` e `meta` do Laravel:

```json
{ "data": { "uuid": "01a0…", "codigo_publico": "PRJ-7K2M4Q", "name": "Loja" } }
```

**Erro** — contrapartida simétrica, em `error`
(`App\Core\Http\Exceptions\ApiErrorRenderer`, registrado em
`bootstrap/app.php`). Vale para TODA rota `api/*`:

```json
{
  "error": {
    "code": "unauthorized",
    "message": "Credenciais de API ausentes, inválidas ou expiradas.",
    "correlation_id": "01a06e5d-5d70-72e3-b4e5-751f6cb0a5ad"
  }
}
```

| Campo | Papel |
|---|---|
| `code` | Identificador **estável**, em inglês, que o cliente programa. Não se traduz. |
| `message` | Texto humano, **traduzido** no idioma da requisição (`lang/*/api.php`). |
| `correlation_id` | O mesmo do header `X-Correlation-Id` e da linha em `request_logs` — é ele que liga a queixa do cliente à trilha de auditoria. |
| `errors` | **Só em 422**: mapa `campo → [mensagens]`. |

Códigos por status: `400 bad_request`, `401 unauthorized`, `403 forbidden`,
`404 not_found`, `405 method_not_allowed`, `409 conflict`, `419 page_expired`,
`422 validation_failed`, `429 too_many_requests`, `503 service_unavailable`,
e `server_error` para qualquer 5xx.

Exemplo de 422:

```json
{
  "error": {
    "code": "validation_failed",
    "message": "Os dados enviados são inválidos.",
    "correlation_id": "01a0…",
    "errors": { "name": ["O campo nome é obrigatório."] }
  }
}
```

**Regras inegociáveis do envelope de erro:**

- **Nunca** stack trace, classe interna, arquivo ou linha do servidor — **nem
  com `APP_DEBUG=true`**. O cliente recebe o mesmo contrato em todo ambiente
  (antes, um 401 devolvia a página de debug do Symfony com
  `/var/www/html/vendor/...` no corpo).
- **5xx nunca ecoa a mensagem da exceção** (pode conter SQL, caminho ou
  segredo): sai a mensagem genérica traduzida e o detalhe fica no log,
  recuperável pelo `correlation_id`.
- 4xx pode carregar a mensagem do `abort()` da aplicação (já traduzida na
  origem, como o 401 do `resolve.tenant`); mensagens internas do
  framework/Symfony são descartadas em favor da tradução do kit.
- O `Retry-After` do rate limit é preservado no header (informação útil e
  não sensível).

Cobertura: `tests/Feature/Api/ErrorEnvelopeTest.php` — um teste por status
(401/403/404/422/429/500), mais a checagem de que nada de servidor vaza.

## Endpoints da API v1 (`routes/api.php`)

Todos sob `resolve.tenant` + scope próprio; `uuid` na URL, nunca `id`
(anti-enumeração — recurso de outro tenant = **404 uniforme**, nunca 403).

| Endpoint | Scope | Observação |
|---|---|---|
| `GET /api/v1/api-keys` | `api-keys:read` | lista paginada do tenant |
| `POST /api/v1/api-keys` | `api-keys:create` | **ação sensível** (abaixo); secreta sai 1x no campo `secret_key` |
| `DELETE /api/v1/api-keys/{uuid}` | `api-keys:revoke` | revogação irreversível |
| `POST /api/v1/api-keys/{uuid}/rotate` | `api-keys:rotate` | **ação sensível**; `grace_period_minutes` no corpo |
| `PUT /api/v1/api-keys/{uuid}/projects` | `api-keys:assign` | vínculo N:N (lista vazia = conta toda) |
| `GET/POST /api/v1/projects` + `GET/PUT/DELETE /api/v1/projects/{uuid}` | `projects:*` | CRUD; projeto nasce só com nome |

Além do scope, o **vínculo da chave com projetos** limita o que ela alcança (ver
[Projetos](tenancy.md#projetos-multi-empresa-organizacional)): toda rota de `api-keys` e o
`POST /projects` exigem chave **sem vínculo** — a chave vinculada recebe 403 mesmo com `*:*`,
salvo para rotacionar ou revogar **a si mesma**.

**Ação sensível** (criação e rotação de chave): exigem o token de
curta duração da [ação sensível](autenticacao.md) (senha de transação + código por e-mail) no header
`X-Sensitive-Action-Token`, obtido via `POST /sensitive-actions/code` +
`POST /sensitive-actions/confirm` (rotas web, sessão). Uso único.

**Bootstrap (primeira chave):** os endpoints exigem uma chave existente. A
primeira chave do usuário é criada pelo painel (`/api-keys`) ou, em dev, via
`tinker` com o `ApiKeyService`:

```php
app(App\Core\ApiKeys\Services\ApiKeyService::class)
    ->create($user, ['name' => 'Bootstrap']); // retorna a sk_ em claro 1x
```

## Rotação

Gera substituta herdando nome, scopes e projetos (`rotated_from_id`/
`rotated_to_id` encadeiam). No ato, o usuário escolhe a morte da antiga:
`grace_period_minutes` **nulo/0 = morte imediata**; **positivo = janela de
coexistência** (antiga segue ativa até `grace_ends_at` — troca sem downtime).
Teto em `API_KEYS_MAX_GRACE_MINUTES` (padrão 7 dias).

## Validade e expiração por inatividade

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

## Testes

`tests/Feature/ApiKeys/` + `tests/Feature/Tenancy/` (Pest): geração/hash (só
hash no banco, formato por ambiente, pepper), ciclo criar/usar/revogar,
rotação com e sem grace (`travel()`), scopes (exato/wildcards/negado = 403 com
mensagem), validade por data, inatividade (aviso 1x, expiração, rearme,
config off), isolamento de tenant (invisibilidade total + 404 uniforme),
chave inválida = 401 + request log sem tenant, last_used_at throttled e
timing-safe estrutural (`hash_equals`).
