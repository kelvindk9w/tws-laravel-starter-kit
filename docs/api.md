# API e chaves de API

Motor de chaves de API, resolução de tenant e a API v1 no pacote
**`twstec/kit-accounts`** ([`packages/accounts`](../packages/accounts/README.md),
namespaces `Twstec\Kit\Accounts\ApiKeys\…` e `Twstec\Kit\Accounts\Tenancy\…`).
O pacote liga sozinho a autenticação por chave, os escopos, o limite por chave
e o envelope de erro — o aplicativo não precisa lembrar de nada (ver
[O que o pacote instala](#o-que-o-pacote-instala-sozinho)). A autenticação da
API é por **par de chaves no header** (não por sessão):

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

### Pepper vazio nunca é pepper

A linha `API_KEYS_HASH_PEPPER=` **sem valor** não aciona o fallback do `env()`
do Laravel: ele devolve a string vazia. Até esta correção o HMAC rodava então
com um pepper de 0 caracteres, em silêncio — um hash que qualquer um com cópia
do banco verifica offline. E o `.env.example` trazia exatamente essa linha.

Agora **vazio ou só espaços vale como ausente**: o pepper passa a ser a
`APP_KEY`, como se a variável não existisse. A regra está no config do pacote
e, de novo, no `ApiKeyHasher` — o único ponto que entrega o pepper ao HMAC,
incluindo o hash fictício da pk_ inexistente —, então vale mesmo com uma cópia
antiga do `config/api_keys.php` publicada no aplicativo. Sem pepper dedicado e
sem `APP_KEY`, o hasher **recusa** em vez de calcular hash sem segredo.

### Trocar o pepper sem invalidar as chaves: peppers anteriores

No estilo do `APP_PREVIOUS_KEYS`: `API_KEYS_PREVIOUS_HASH_PEPPERS` (lista
separada por vírgula; `api_keys.previous_peppers`). Na autenticação, a secreta
é conferida contra o pepper atual e contra cada anterior. Se conferir com um
anterior, a requisição passa e o `secret_hash` é **regravado com o pepper
atual** (migração transparente no primeiro uso); a trilha de arquivo
(`request_log`) recebe `api_keys.secret_hash.migrated` com a chave, o dono e a
origem (`previous_pepper` ou `empty_pepper_legacy`) — nunca a secreta, o hash
ou o pepper.

- **Tempo constante:** todos os peppers aceitos são calculados e comparados
  com `hash_equals` em toda verificação, sem parar no primeiro que confere. O
  custo depende só da configuração, então a pk_ inexistente (verificada contra
  o hash fictício) continua levando o mesmo tempo que a existente.
- **Recusa igual:** secreta que não confere com nenhum continua 401 no
  envelope padrão e conta para o limite de falhas por chave e por IP.
- A lista não aceita item vazio: o pepper vazio só entra pela flag abaixo.

### Chaves emitidas com pepper vazio (legado)

`API_KEYS_ACCEPT_EMPTY_PEPPER_LEGACY=true` (`api_keys.accept_empty_pepper_legacy`,
**desligada por padrão**) aceita também o hash de pepper vazio — e o migra
para o pepper atual no primeiro uso. Nunca é implícito. Em
`APP_ENV=production` a flag ligada grava aviso no log **a cada boot**.

### Avisos de produção

Em `APP_ENV=production`, o boot grava aviso no log (não recusa — derrubar a
aplicação não troca o pepper) quando:

- `API_KEYS_HASH_PEPPER` está ausente ou **vazio** (o aviso diz que vazio
  conta como ausente e que o hash está usando a `APP_KEY`);
- `API_KEYS_ACCEPT_EMPTY_PEPPER_LEGACY=true`.

### Roteiro de transição

1. **Instalação com `API_KEYS_HASH_PEPPER=` vazio que já emitiu chaves**
   (qualquer uma criada a partir do `.env.example` antigo):
   - defina um pepper dedicado: `php artisan tinker` → `Str::random(64)`;
   - ligue `API_KEYS_ACCEPT_EMPTY_PEPPER_LEGACY=true`;
   - cada chave migra no primeiro uso (acompanhe
     `api_keys.secret_hash.migrated` no `request_log`);
   - desligue a flag quando todas as chaves em uso tiverem migrado ou sido
     rotacionadas. Com a expiração por inatividade ligada (padrão), depois de
     `API_KEYS_INACTIVITY_MONTHS` meses com a flag ligada toda chave ainda
     ativa já foi usada — portanto migrada — ou foi desativada por inatividade.
2. **Instalação sem pepper dedicado (fallback na `APP_KEY`):** defina o pepper
   dedicado e declare a `APP_KEY` atual em `API_KEYS_PREVIOUS_HASH_PEPPERS`.
   Depois da migração, rotacionar a `APP_KEY` deixa de afetar as chaves.
3. **Trocar um pepper dedicado:** o novo em `API_KEYS_HASH_PEPPER`, o antigo em
   `API_KEYS_PREVIOUS_HASH_PEPPERS`; retire o antigo da lista quando as chaves
   tiverem migrado.

Chave que nunca é usada durante a janela não migra: depois que o pepper
anterior (ou a flag) sai, ela passa a receber 401 e precisa ser rotacionada.

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
(`Twstec\Kit\Foundation\Http\Resources\BaseResource`); listagens paginadas acrescentam
`links` e `meta` do Laravel:

```json
{ "data": { "uuid": "01a0…", "codigo_publico": "PRJ-7K2M4Q", "name": "Loja" } }
```

**Erro** — contrapartida simétrica, em `error`
(`Twstec\Kit\Foundation\Http\Exceptions\ApiErrorRenderer`, do foundation,
registrado no tratador de exceções pelo pacote `twstec/kit-accounts`, dono da
API). Vale para TODA rota `api/*`:

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

## Endpoints da API v1 (do pacote — `Twstec\Kit\Accounts\Http\ApiRoutes`)

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
| `POST /api/v1/uploads` | `uploads:create` | do starter (`routes/api.php`), no mesmo grupo — vai para o pacote de uploads numa fase futura |

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
app(Twstec\Kit\Accounts\ApiKeys\Services\ApiKeyService::class)
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
- **Inatividade**: job diário `api-keys:process-inactivity` (comando do
  pacote; o agendamento é do aplicativo, em `routes/console.php`, `daily()` + `withoutOverlapping()` + `onOneServer()`)
  desativa chaves sem uso há `API_KEYS_INACTIVITY_MONTHS` meses (padrão 3) com
  status `expired_inactivity`. **Aviso prévio por e-mail**
  `API_KEYS_INACTIVITY_WARNING_DAYS` dias antes (padrão 7), UMA vez por ciclo —
  a flag `inactivity_warning_sent_at` impede repetição e é rearmada quando a
  chave volta a ser usada. O middleware `resolve.tenant` também rejeita chave
  inativa (defesa em profundidade caso o scheduler atrase). Tudo em UTC.

## O que o pacote instala sozinho

O `Twstec\Kit\Accounts\AccountsServiceProvider` é descoberto pelo Composer e
liga, sem nenhuma linha no `bootstrap/app.php`:

| Proteção | Como |
|---|---|
| Autenticação por chave | alias `resolve.tenant` (`ResolveTenant`), sempre no grupo das rotas v1 |
| Escopo e operação de conta | aliases `scope` e `account.key`, declarados em cada rota |
| Limite por chave | `throttle:api` na frente do grupo `api` (o limitador `api` é do foundation) e o `ResolveTenant` antes do `ThrottleRequests` na lista de prioridade — o limite conta a chave, não o IP |
| Limite de falhas de autenticação por chave e por IP | dentro do próprio `ResolveTenant` (`ApiRateLimit`, do foundation) |
| Envelope de erro sem vazamento | render do `ApiErrorRenderer` para `api/*` no tratador de exceções |
| Quem o limite conta | `TenantRateLimitSubject` (a chave, ou o dono com `RATE_LIMIT_API_BY=tenant`) |

Os aliases só entram se o aplicativo não declarou um de mesmo nome, e um
render próprio do aplicativo no `bootstrap/app.php` roda antes do envelope do
pacote. **Opt-out:** `API_KEYS_API_PROTECTIONS=false` — nada disso é instalado
e um aviso vai para o log a cada boot; só é seguro se o aplicativo instalar as
mesmas proteções.

**Rotas.** O pacote registra as rotas `/api/v1` (prefixo, middleware e nome em
`api_keys.api.routes`). Para registrar você mesmo, desligue com
`API_KEYS_API_ROUTES=false` e chame `ApiRoutes::register()` onde quiser; a
autenticação por chave entra no grupo em qualquer caso. O envelope de erro vale
para `api/*`: um prefixo fora de `api/` fica sem ele.

## Testes

`packages/accounts/tests` (aplicação Laravel limpa, sem nada do starter): as
proteções acima (401 no envelope, limite de falhas, 403 de escopo, 404 fora do
vínculo, 429 por chave, inatividade, dono não verificado, pepper), os nomes
antigos, as traduções e a arquitetura do pacote.

`tests/Feature/ApiKeys/` + `tests/Feature/Tenancy/` do starter (Pest): geração/hash (só
hash no banco, formato por ambiente, pepper), ciclo criar/usar/revogar,
rotação com e sem grace (`travel()`), scopes (exato/wildcards/negado = 403 com
mensagem), validade por data, inatividade (aviso 1x, expiração, rearme,
config off), isolamento de tenant (invisibilidade total + 404 uniforme),
chave inválida = 401 + request log sem tenant, last_used_at throttled e
timing-safe estrutural (`hash_equals`).
