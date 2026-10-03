# API e chaves de API

> **Módulo opcional** (`twstec/kit-accounts`, com as contas). Sem ele não há
> API v1 nem chaves; o `/api/health` continua com o limite e o envelope de
> erro, ligados pelo aplicativo — ver [instalação e módulos](instalacao.md).

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

**A chave autentica a CONTA** (2.0 — ver [Contas](tenancy.md)). A chave é da
conta, não de quem a criou: continua valendo quando essa pessoa sai da conta
ou é excluída. Respostas, códigos e envelopes são os mesmos da 1.x; o
`tenant_uuid` do request log passa a ser o uuid da conta (na conta pessoal, o
uuid da pessoa, como antes). `tenant()` devolve a conta; a pessoa por trás da
chave — quem a criou, enquanto for membro ativo; senão o dono da conta — é o
`$request->user()` da rota e é quem precisa do token de ação sensível.

**Dono da conta precisa estar ativo e com o e-mail confirmado.** A cada
chamada o `ResolveTenant` confere o dono da conta da chave: conta
bloqueada/pendente ou, com a verificação de e-mail ligada (padrão), sem e-mail
confirmado recebe o mesmo 401 mudo de chave inválida (na conta pessoal, o dono
é a própria pessoa — a regra da 1.x). Na prática uma conta nova nem chega a ter
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

Formato `recurso:acao` (ex.: `customers:read`, `orders:create`, `invoices:*`),
jsonb na coluna `scopes`. **Padrão na criação: tudo habilitado (`['*:*']`)** —
o usuário restringe pelo menor privilégio. Wildcards: `*:*` e `recurso:*`.
Checagem no model: `$apiKey->allows('orders:create')`. Proteção de rota:

```php
Route::post('/orders', ...)->middleware('scope:orders:create'); // 403 + scope exigido
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

Códigos próprios, mais específicos que o do status (a exceção implementa
`Twstec\Kit\Foundation\Http\Exceptions\Contracts\ProvidesApiErrorCode`):
os da [idempotência](#idempotência-idempotency-key) — `400
idempotency_key_missing`, `400 idempotency_key_invalid`, `409
idempotency_request_in_progress` e `422 idempotency_key_reused`.

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
(anti-enumeração — recurso de outra conta = **404 uniforme**, nunca 403). Toda
consulta sai filtrada pela conta da chave (escopo da conta — ver
[Isolamento automático](tenancy.md#isolamento-automático)).

| Endpoint | Scope | Observação |
|---|---|---|
| `GET /api/v1/api-keys` | `api-keys:read` | lista paginada da conta |
| `POST /api/v1/api-keys` | `api-keys:create` | **ação sensível** (abaixo); secreta sai 1x no campo `secret_key`; aceita `Idempotency-Key` sem guardar a secreta |
| `DELETE /api/v1/api-keys/{uuid}` | `api-keys:revoke` | revogação irreversível |
| `POST /api/v1/api-keys/{uuid}/rotate` | `api-keys:rotate` | **ação sensível**; `grace_period_minutes` no corpo; aceita `Idempotency-Key` sem guardar a secreta |
| `PUT /api/v1/api-keys/{uuid}/projects` | `api-keys:assign` | vínculo N:N (lista vazia = conta toda) |
| `GET/POST /api/v1/projects` + `GET/PUT/DELETE /api/v1/projects/{uuid}` | `projects:*` | CRUD; projeto nasce só com nome; o `POST` aceita `Idempotency-Key` |
| `POST /api/v1/uploads` | `uploads:create` | do pacote `twstec/kit-uploads`, no mesmo grupo (ver [Uploads](uploads.md#endpoints)); aceita `Idempotency-Key` sem guardar a URL assinada |

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

## Idempotência (`Idempotency-Key`)

Quando o cliente reenvia um `POST` (timeout, queda de rede, retry automático
do SDK), a operação não pode rodar de novo. O cabeçalho `Idempotency-Key` resolve
isso: a primeira requisição com a chave executa, e as repetições recebem a
**resposta original** sem executar nada. O middleware é do
`twstec/kit-foundation` (`Twstec\Kit\Foundation\Idempotency`) e vale **por rota**.
O desenho segue o rascunho da IETF *The Idempotency-Key HTTP Header Field*.

### Na rota

```php
use Twstec\Kit\Foundation\Idempotency\Middleware\HandleIdempotencyKey;

// Chave opcional: sem ela, a rota funciona como sempre.
Route::post('pedidos', [PedidoController::class, 'store'])
    ->middleware(['auth', 'idempotent']);

// Chave obrigatória: sem ela, 400 `idempotency_key_missing`.
Route::post('faturas', [FaturaController::class, 'store'])
    ->middleware(['auth', 'idempotent:required']);

// Rota que exibe um segredo UMA vez: a resposta não é guardada (ver abaixo).
Route::post('tokens', [TokenController::class, 'store'])
    ->middleware(['auth', HandleIdempotencyKey::using(required: true, withhold: true, keep: ['data.uuid'])]);
```

Do lado do cliente, uma chave nova (UUID v4) por **operação**, repetida em
cada nova tentativa da mesma operação:

```bash
curl -X POST https://exemplo.test/api/v1/projects \
  -H "X-Api-Key: $PUBLICA" -H "Authorization: Bearer $SECRETA" \
  -H "Idempotency-Key: 6f1c0e2a-3b4d-4e5f-8a9b-0c1d2e3f4a5b" \
  -H "Content-Type: application/json" -d '{"name":"Pedidos"}'
```

As rotas `POST` da API v1 dos pacotes já aceitam a chave (opcional):
`POST /projects` repete a resposta original; `POST /api-keys` e
`POST /api-keys/{uuid}/rotate`, que exibem a secreta uma vez, e
`POST /uploads`, que devolve uma URL assinada (credencial enquanto vale),
usam o modo **sem corpo** (abaixo).

### O que acontece

| Situação | Resposta | Executa? |
|---|---|---|
| Primeira requisição com a chave | a da rota | sim |
| Mesma chave, mesma requisição, já concluída | **replay**: o mesmo status e o mesmo corpo, byte a byte, com `Idempotent-Replayed: true` e `X-Original-Correlation-Id` | não |
| Mesma chave, requisição **diferente** (corpo, caminho, query ou método) | `422` `idempotency_key_reused` | não |
| Mesma chave ainda **em processamento** | `409` `idempotency_request_in_progress` com `Retry-After: 1` (ou, com `IDEMPOTENCY_WAIT_MS`, espera curta e o replay) | não |
| Chave fora do formato, ou dois cabeçalhos | `400` `idempotency_key_invalid` | não |
| Rota com `required` e sem a chave | `400` `idempotency_key_missing` | não |
| Chave **vencida** (`IDEMPOTENCY_TTL_HOURS`, padrão 24 h desde a primeira execução) | vale como chave nova | sim |

As recusas saem no [envelope de erro](#contrato-de-resposta-da-api-sucesso-e-erro)
com o `code` estável da tabela. Métodos lidos: `POST` e `PATCH`
(`IDEMPOTENCY_METHODS`); nos outros, o cabeçalho é ignorado.

**Formato da chave:** de 16 a 255 caracteres (`IDEMPOTENCY_KEY_MIN_LENGTH`,
`IDEMPOTENCY_KEY_MAX_LENGTH`) entre `A-Z a-z 0-9 - _ . : ~ + / =`. Pode vir
entre aspas (Structured Field String, como no rascunho da IETF).

### Escopo: de quem é a chave

A mesma chave em clientes diferentes são chaves diferentes, e nenhum enxerga a
resposta guardada do outro.

- **API com chave de API** (módulo de contas): conta + **chave de API** que fez
  a chamada. Duas integrações da mesma conta, cada uma com a sua chave, não
  colidem.
- **Sessão** (painel): conta atual + pessoa. Só com a base (sem o módulo de
  contas): a pessoa autenticada.
- **Sem ninguém identificado: falha fechada.** A requisição com chave não
  executa e sai como erro de servidor (500 genérico; o detalhe vai para o log
  como `api.idempotency.scope_missing`). Nunca se usa o IP nem uma constante
  como escopo. Para escopo próprio, registre um
  `Twstec\Kit\Foundation\Idempotency\Contracts\IdempotencyScopeResolver` no
  container (o do aplicativo vence o do pacote).

**Ordem:** o middleware roda **depois** da autenticação, do limite, dos
bindings e da autorização (`can:`), em qualquer ordem em que a rota o
declare (lista de prioridade do kernel). Um replay nunca dispensa nenhuma
delas. Confirmação de **uso único** (o `sensitive.token`) vai **depois** do
`idempotent`: a repetição devolve o replay sem consumir uma confirmação nova
(e sem executar nada).

### O que é guardado (e o que nunca é)

Tabela `idempotency_keys` (migration do foundation):

- **A chave e o escopo:** só o SHA-256. A chave do cliente nunca vira nome de
  arquivo, chave de cache nem linha de log; o log leva só os 12 primeiros
  caracteres do hash (`key_fingerprint`).
- **A requisição: só o hash, NUNCA o corpo.** SHA-256 de uma forma canônica
  (versão 1) com o método, o caminho real, a query e o corpo normalizado:
  - **JSON** (`application/json`, `+json`): chaves de objeto em ordem,
    listas na ordem em que vieram, sem espaços. Tipos preservados: `1`,
    `1.0` e `"1"` são diferentes, `{}` e `[]` também, e o inteiro maior que
    64 bits entra com os dígitos exatos. Reordenar as chaves ou mudar a
    indentação **não** muda o hash.
  - **Formulário** (`x-www-form-urlencoded`, `multipart/form-data`): os
    campos interpretados, com as chaves em ordem.
  - **Arquivos**, em qualquer tipo de corpo: nome original, tamanho e
    SHA-256 do conteúdo de cada um. O mesmo nome com outro conteúdo é outra
    requisição.
  - **Outro conteúdo** (ou JSON inválido): o tipo de mídia e o SHA-256 dos
    bytes.

  Na dúvida a forma canônica **distingue**: duas requisições equivalentes
  podem dar hashes diferentes (o cliente recebe `422` e usa outra chave), mas
  duas requisições que o aplicativo leria diferente nunca dão o mesmo hash.
- **A resposta: CIFRADA** com o encrypter do Laravel (AES-256-CBC com MAC,
  chave `APP_KEY`; a rotação com `APP_PREVIOUS_KEYS` continua decifrando).
  Só os cabeçalhos `Content-Type`, `Content-Language` e `Location` vão junto,
  nunca cookie. Vive só enquanto a chave vale, e a poda de hora em hora
  (`idempotency:prune`, `IDEMPOTENCY_PRUNE_SCHEDULE`, agendada pelo pacote)
  apaga o que venceu. Resposta maior que `IDEMPOTENCY_MAX_RESPONSE_BYTES`
  (256 KB) ou que não dá para capturar (stream, arquivo) é guardada **sem
  corpo**. Resposta que não decifra mais (`APP_KEY` trocada sem a anterior em
  `APP_PREVIOUS_KEYS`) vira o replay sem corpo, e a requisição **não executa de
  novo** por isso.

### Rotas que exibem um segredo uma vez (`withhold`)

A resposta guardada é um segredo a mais em repouso. Numa rota que mostra a
secreta de uma chave de API, um código de recuperação ou um token, o kit
**não guarda o corpo**, nem cifrado. Com `withhold`:

- só os campos da lista branca `keep` (ex.: `data.uuid`) são guardados;
- o replay devolve o **status original**, `Idempotent-Replayed: true`, o
  `Location` (se houver) e o corpo
  `{…campos de keep…, "idempotency": {"replayed": true, "body_withheld": true, "message": "…"}}`;
- o segredo **nunca é reexibido**. Quem perdeu a resposta original sabe qual
  recurso foi criado (pelo `keep`) e segue o caminho normal de recuperação:
  na API v1, **rotacionar** a chave criada.

Por que não reexibir dentro do prazo: guardar a secreta por 24 h, mesmo
cifrada, cria uma segunda cópia recuperável dela, ao alcance de qualquer um
com a chave de API e a `Idempotency-Key`. Na API v1, `POST /api-keys` e
`POST /api-keys/{uuid}/rotate` usam `withhold` com `keep` em `data.uuid`,
`data.codigo_publico` e `data.public_key`. Atenção à rotação da **própria**
chave sem carência: a credencial antiga morre na hora, e a repetição com ela
recebe `401` antes da idempotência.

`POST /api/v1/uploads` segue a mesma regra: a URL assinada não é guardada nem
reexibida. A repetição devolve `uuid`, código público, tipo, tamanho, hash e
situação. Como obter uma URL nova está em
[Uploads](uploads.md#endpoints).

### Falhas: quando a chave fica livre de novo

| Resultado da execução | Destino da chave |
|---|---|
| `2xx` e `3xx` | guardado (replay até vencer) |
| `5xx` ou exceção | **liberada**: a linha é apagada e a próxima tentativa executa. Erro de servidor nunca congela a chave |
| `422` de validação | liberada (o cliente corrige o corpo e usa a mesma chave). Com `IDEMPOTENCY_FREEZE_VALIDATION_ERRORS=true`, guardado |
| outros `4xx` | liberada, salvo os listados em `IDEMPOTENCY_FREEZE_CLIENT_ERRORS` (ex.: `404,410`). `401`, `403`, `408`, `409`, `425` e `429` nunca congelam |
| sem resultado registrado: o processo morre no meio, ou a rota termina e a gravação da conclusão falha (banco fora naquele instante) | a linha fica "em processamento". **Padrão: nunca é retomada** — as repetições recebem `409 idempotency_request_in_progress` até a chave vencer. Com `IDEMPOTENCY_ABANDONED_TAKEOVER_SECONDS` > 0, uma repetição com o **mesmo** corpo retoma a chave depois desse prazo e **executa de novo** (`api.idempotency.abandoned_taken_over` no log) |

**Nas rotas comuns, grave dentro de uma transação.** Se a rota grava e
depois responde `5xx`, a chave é liberada e a repetição executa de novo. A
transação faz o `5xx` desfazer a escrita. O modo transacional, abaixo, faz
isso sozinho.

### Resultado indeterminado: por que o padrão é nunca retomar

Se a rota gravou o efeito e o processo morreu (ou o banco falhou) antes de
marcar a chave como concluída, a linha fica "em processamento". O middleware
não tem como saber se o efeito foi confirmado antes ou depois da queda.
Retomar a chave depois de um prazo executaria a operação de novo, e esse é
justamente o efeito duplicado que a idempotência existe para impedir. Pela
regra do kit, segurança vem primeiro: **efeito duplicado é pior que uma chave
presa.** Por isso o padrão é `IDEMPOTENCY_ABANDONED_TAKEOVER_SECONDS=0`. A
falha ao concluir vai para o log como `api.idempotency.persist_failed`, nível
`critical`, com `rolled_back: false`.

**O que o cliente faz com o `409` que não passa:**

1. Consulta o estado do recurso (o pedido, a fatura) pelo caminho normal da
   API.
2. Se a operação **não** aconteceu, repete com uma **chave nova**.

A chave presa sai sozinha quando vence (`IDEMPOTENCY_TTL_HOURS`).

| Escolha | Risco |
|---|---|
| `0` (padrão) | a chave fica presa até vencer, e o cliente consulta antes de repetir |
| `> 0` (ex.: `300`) | depois do prazo, a repetição executa de novo: **efeito duplicado** se a primeira tinha confirmado |

**Recomendação.** Operação financeira ou irreversível (registrar um valor,
emitir uma fatura, disparar um envio): mantenha `0` e use o **modo
transacional** (abaixo) sempre que o efeito for todo no banco. Um prazo maior
que zero só serve para operação cujo efeito duplicado é inofensivo, e precisa
ser maior que o tempo máximo da rota.

### Modo transacional (`idempotent:transactional`)

Com `transactional`, a rota roda **dentro de uma transação do banco**, e a
conclusão da chave (a resposta cifrada e o status `completed`) é gravada
**na mesma transação**. O efeito e o "concluída" são confirmados juntos, ou
nenhum dos dois:

```php
Route::post('pedidos', [PedidoController::class, 'store'])
    ->middleware(['auth', 'idempotent:transactional']);

// ou, com outras opções:
HandleIdempotencyKey::using(required: true, transactional: true);
```

Nesse modo, "em processamento" quer dizer "nada confirmado". Isso muda as
falhas:

- **status que congela:** o commit leva a escrita e a conclusão juntas;
- **`5xx` ou outro status que não congela:** a transação é **desfeita**
  (nada do que a rota gravou fica) e a chave é liberada;
- **falha ao gravar a conclusão:** a transação inteira é desfeita, a resposta
  vira `500` (o cliente nunca recebe um `201` de algo que não ficou) e a
  chave é liberada. Se nem a liberação passar, porque o banco está fora, a
  linha espera `IDEMPOTENCY_LOCK_SECONDS`;
- **processo morto no meio:** nada foi confirmado. Depois de
  `IDEMPOTENCY_LOCK_SECONDS` (padrão 120 s, maior que a requisição mais
  lenta da rota), a repetição retoma a chave **com segurança** e executa uma
  vez.

Condições para usar:

- o efeito da rota é **todo no banco**, na **mesma conexão** da tabela de
  chaves. Com `IDEMPOTENCY_CONNECTION` apontando para outra conexão, a rota
  recusa com erro de montagem;
- nada de efeito externo que a transação não desfaz: arquivo no disco,
  chamada HTTP de saída, e-mail. Job da fila só com `afterCommit`;
- a rota não abre transação própria em **outra** conexão. Uma
  `DB::transaction` interna, na mesma conexão, vira um ponto de salvamento e
  funciona.

As rotas da API v1 do kit continuam no modo comum, com a retomada desligada
(o padrão): `POST /uploads` grava arquivo no disco, e a criação de chave
consome o token de ação sensível e pode avisar por e-mail.

### A corrida

Duas requisições simultâneas com a mesma chave: o banco decide. A primeira
coisa que o middleware faz é um `INSERT … ON CONFLICT DO NOTHING` da linha "em
processamento" sobre a unicidade (`scope_hash`, `key_hash`) — fora de
transação do aplicativo, comprometido na hora. O PostgreSQL deixa **uma**
inserir; a outra recebe 0 linhas, lê a linha da primeira e responde `409` (ou
espera, com `IDEMPOTENCY_WAIT_MS`). Não há trava de cache, nem "consulta e
depois insere". Retomar uma linha vencida ou abandonada é um `UPDATE`
condicionado ao dono atual (`owner`). Finalizar e liberar também são, então
uma execução antiga que termina tarde não sobrescreve a nova.

### Trilha e correlation id

- O **replay** tem o próprio `X-Correlation-Id` (a requisição de replay é uma
  requisição nova, com a linha dela em `request_logs`) e aponta a original em
  `X-Original-Correlation-Id`. O corpo é o da original, com o `correlation_id`
  dela quando for um envelope de erro congelado.
- Canal `request_log`: `api.idempotency.replayed` (info, com o
  `original_correlation_id`), `api.idempotency.refused` (warning, com o
  `reason`: `key_reused`, `in_progress`, `invalid_key`, `missing_key`),
  `api.idempotency.released`, `api.idempotency.scope_missing`,
  `api.idempotency.abandoned_taken_over`, `api.idempotency.replay_unreadable`
  e `api.idempotency.persist_failed`. Sempre com o `correlation_id` e a
  impressão curta da chave, nunca a chave.
- O status da recusa (`409`, `422`, `400`) fica na linha da requisição em
  `request_logs`, como qualquer resposta.

### Configuração

| Variável | Padrão | O que faz |
|---|---|---|
| `IDEMPOTENCY_METHODS` | `POST,PATCH` | métodos em que a chave é lida |
| `IDEMPOTENCY_TTL_HOURS` | `24` | validade da chave, desde a primeira execução |
| `IDEMPOTENCY_ABANDONED_TAKEOVER_SECONDS` | `0` | rotas comuns: prazo para retomar uma execução sem resultado registrado; `0` = nunca (falha fechada) |
| `IDEMPOTENCY_LOCK_SECONDS` | `120` | modo transacional: prazo para retomar uma execução abandonada (segura: nada confirmado) |
| `IDEMPOTENCY_WAIT_MS` | `0` | espera curta da repetição concorrente (teto 10 s); 0 = `409` na hora |
| `IDEMPOTENCY_KEY_MIN_LENGTH` / `IDEMPOTENCY_KEY_MAX_LENGTH` | `16` / `255` | tamanho aceito da chave |
| `IDEMPOTENCY_FREEZE_VALIDATION_ERRORS` | `false` | guarda o `422` de validação |
| `IDEMPOTENCY_FREEZE_CLIENT_ERRORS` | vazio | outros `4xx` guardados |
| `IDEMPOTENCY_MAX_RESPONSE_BYTES` | `262144` | teto do corpo guardado |
| `IDEMPOTENCY_CONNECTION` | vazio (a padrão) | conexão da tabela |
| `IDEMPOTENCY_PRUNE_SCHEDULE` | `35 * * * *` | cron da poda; vazio desliga, com aviso no log |

**Dados pessoais:** a tabela não tem o corpo da requisição, e a resposta
cifrada sai na poda, no máximo `IDEMPOTENCY_TTL_HOURS` + 1 h depois da
primeira execução. Excluir uma conta não apaga as linhas dela na hora (o
escopo é um hash); elas vencem no mesmo prazo.

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
| Quem o limite conta | `TenantRateLimitSubject` (a chave, ou a conta com `RATE_LIMIT_API_BY=tenant`) |
| Conta atual da requisição | o `ResolveTenant` define a conta da chave como conta atual (o escopo das contas filtra por ela) e a desfaz no fim da requisição |
| De quem é a `Idempotency-Key` | `TenantIdempotencyScope` (conta + chave de API; na sessão, conta + pessoa) — ver [Idempotência](#idempotência-idempotency-key) |

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
vínculo, 429 por chave, inatividade, dono não verificado, pepper), o
isolamento entre contas (pessoa em duas contas, chave de cada conta, chave que
sobrevive à saída de quem a criou, contexto nos jobs), a migração da 1.x, os
nomes antigos, as traduções e a arquitetura do pacote.

`tests/Feature/ApiKeys/` + `tests/Feature/Tenancy/` do starter (Pest): geração/hash (só
hash no banco, formato por ambiente, pepper), ciclo criar/usar/revogar,
rotação com e sem grace (`travel()`), scopes (exato/wildcards/negado = 403 com
mensagem), validade por data, inatividade (aviso 1x, expiração, rearme,
config off), isolamento de tenant (invisibilidade total + 404 uniforme),
chave inválida = 401 + request log sem tenant, last_used_at throttled e
timing-safe estrutural (`hash_equals`).

Idempotência: a regra inteira em
`packages/foundation/tests/Feature/Idempotency/` e
`packages/foundation/tests/Unit/Idempotency/` (forma canônica, formato da
chave, replay, recusas, escopo, vencimento com o relógio congelado, falhas,
cifra, `withhold`, espera curta, retomada, poda); a API v1 em
`packages/accounts/tests/Protections/ApiIdempotencyTest.php` (inclusive a
secreta nunca guardada nem reexibida); a requisição de verdade nos starters
em `tests/Feature/Api/IdempotencyTest.php` e a **corrida real** em
`tests/Feature/Api/IdempotencyConcurrencyTest.php` (dois processos com
`pcntl_fork` no PostgreSQL, numa schema isolada; roda com
`-c phpunit.pgsql.xml`, como no CI).
