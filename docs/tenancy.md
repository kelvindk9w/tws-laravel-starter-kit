# Tenancy e projetos

Módulo `Tenancy` do pacote **`twstec/kit-accounts`**
([`packages/accounts`](../packages/accounts/README.md)): contexto do tenant,
projetos, o serviço de projetos e a visão geral da conta. Nesta versão o
**dono é a pessoa** (o usuário dono da chave); contas com membros chegam numa
fase futura.

## Tenancy

Middleware **`resolve.tenant`** (`ResolveTenant`, grupo `api/v1`; alias
instalado pelo pacote): valida o par
pk_/sk_ (existência → hash timing-safe → status → validade → grace de rotação
→ inatividade) → resolve o **tenant** (usuário dono da chave) no container
(helpers `tenant()` / `tenantKey()`, `TenantContext`) e no user resolver da
request → **vincula o request log ao tenant** (`tenant_uuid` = uuid do dono)
→ atualiza `last_used_at` **throttled** (máx. 1 escrita a cada
`API_KEYS_LAST_USED_THROTTLE_SECONDS`, padrão 60s).

**Chave inválida = 401 padronizado** (mensagem única, não oracular) e o request
log permanece **SEM tenant** — exatamente o sinal de ataque/tentativa de burla
(o log INICIADA é gravado antes, sem vínculo; a identificação falhou).

**Limite de requisições por chave.** O `throttle:api` conta pela chave de API
autenticada (`RATE_LIMIT_API`, 60/min; `RATE_LIMIT_API_BY=tenant` soma as chaves
do mesmo dono), então integrações diferentes atrás do mesmo IP não disputam o
mesmo orçamento. Falhas de autenticação contam por IP + chave pública
(`RATE_LIMIT_API_AUTH_FAILURES`, 20/min — só aquela credencial, naquele IP, recebe 429) e têm
um teto por IP (`RATE_LIMIT_API_AUTH_FAILURES_PER_IP`, 100/min) que não barra chave já
autenticada daquele IP: o erro de um vizinho de NAT não derruba integração legítima. Todo 429
sai no envelope de erro padrão (`too_many_requests`) com `Retry-After`. Detalhes em
[Limite de requisições](seguranca.md#limite-de-requisições-rate-limit-e-contenção-da-trilha).

## Projetos (multi-empresa organizacional)

`projects` (PRJ-xxxxxx): 1 login gerencia N projetos; nascem só com nome. O
vínculo chave↔projeto é **N:N** e **opcional**. No MVP são metadados
organizacionais — a custódia segue uma por conta.

**A regra do vínculo (vale para toda a API v1):**

| | Chave **sem vínculo** (conta toda) | Chave **vinculada** a projetos |
|---|---|---|
| Listar / ver / alterar / excluir projeto | todos os do dono | só os vinculados; os demais, **mesmo do mesmo dono**, são 404 uniforme |
| Criar projeto | sim (`projects:create`) | **403** — é operação de conta |
| Listar, criar, vincular chaves | sim (scopes `api-keys:*`) | **403** — uma chave vinculada que pudesse se vincular a "nada" viraria conta toda |
| Revogar / rotacionar chave | qualquer chave do dono | **só a si mesma** (nenhuma das duas amplia acesso; a rotação herda scopes **e** restrição) |

O vínculo **limita** o scope, nunca o contrário: a chave vinculada criada com o padrão `*:*`
continua sem poder fazer operação de conta.

**Restrição é estado da chave, não da lista** (`api_keys.restricted_to_projects`). Excluir um
projeto remove o vínculo em cascata (sem linha órfã) e a chave segue restrita aos projetos que
sobraram — **ou a nenhum**, se era o último: ela passa a não enxergar projeto algum, e não
"promovida" à conta toda, como acontecia quando a regra era deduzida da lista vazia. Voltar à
conta toda é sempre uma ação explícita de quem gerencia as chaves: salvar a seleção vazia no
painel ou mandar `project_uuids: []` com uma chave de conta. O campo `project_access` do
recurso da chave (`account` | `projects`) mostra o estado.

No painel e na API, só se vincula projeto **do próprio dono** — uuid de outro usuário é erro de
validação e nada muda (`ApiKeyService::resolveProjectIds`). Uploads (`POST /uploads`) não se
ligam a projeto: gravam no nível da conta e não leem dado de nenhum projeto.

Testes: `tests/Feature/Tenancy/ApiKeyProjectBindingTest.php`.

**Um serviço só para o CRUD de projetos.** A tela do painel (`/projects`) e a
API v1 chamam o `Twstec\Kit\Accounts\Tenancy\Services\ProjectService`; nenhuma das duas
lê ou grava `Project` direto (trava em
`tests/Unit/Architecture/BusinessLogicPlacementTest.php`). O serviço tem os dois
recortes de posse: `listForUser`/`findForUser` (a pessoa logada, no painel) e
`paginateForApiKey`/`findForApiKey` (a chave, com o vínculo e a marca de
restrição — `Project::visibleToApiKey()`). Fora do recorte é sempre a mesma
`ModelNotFoundException` (404 uniforme). `create`, `update` (só `name` e
`status`) e `delete` recebem o dono ou o projeto já achado por um recorte. Um
front novo usa o mesmo serviço, com o recorte da pessoa. Testes:
`tests/Feature/Tenancy/ProjectServiceTest.php`.
