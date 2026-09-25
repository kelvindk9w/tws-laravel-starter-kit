# Contas, isolamento e projetos

Pacote **`twstec/kit-accounts`** ([`packages/accounts`](../packages/accounts/README.md)).
A partir da 2.0 o dono dos dados é a **conta**, não a pessoa: projetos e chaves
de API pertencem a uma conta, e uma pessoa pode estar em várias contas, com um
papel em cada. Toda consulta de dado de conta sai **filtrada sozinha** pela
conta atual — e, sem conta atual, **dá erro em vez de devolver tudo**.

Nesta versão não há tela nova: o painel mostra a conta atual da pessoa (a
pessoal, até existir o seletor), o `/admin` opera em modo sistema e a API usa a
conta da chave. Seletor de conta, tela de membros, convites e transferência de
propriedade chegam numa versão seguinte, sobre o mesmo modelo.

## O modelo

| Tabela | O que guarda |
| --- | --- |
| `accounts` | A conta: `uuid`, código público `ACC-xxxxxx`, `name` (nulo na conta pessoal) e `personal_user_id` (preenchido só na conta pessoal) |
| `account_memberships` | Pessoa × conta, com o papel (`owner`, `admin`, `member`); uma pessoa aparece uma vez por conta |
| `projects`, `api_keys` | `account_id` (a dona) e `created_by` (quem criou — pode ter saído da conta; o dado continua da conta) |

**Conta pessoal.** Toda pessoa ganha a sua ao ser criada, como dona, com o
**mesmo uuid** dela — é isso que mantém a trilha de requisições
(`request_logs.tenant_uuid`) apontando para o identificador certo quando a
chave passa a ser da conta. O pacote liga isso ao evento de criação do model
de usuário do aplicativo, sem trait nenhuma no model.

**Exatamente um dono por conta**, garantido no código (`AccountMembership`,
`AccountService`) e no banco:

| Regra | Código (qualquer banco) | Banco |
| --- | --- | --- |
| No máximo um dono | recusa o segundo | índice único parcial (PostgreSQL e SQLite) |
| Conta nasce com dono | conta e dono na mesma transação | PostgreSQL: gatilho adiado confere no fim da transação |
| Dono não é rebaixado, ninguém vira dono por mudança de papel | recusa | PostgreSQL: gatilho |
| Dono não sai de conta com outros membros | recusa (transferir antes) | PostgreSQL: gatilho — inclusive apagando a pessoa por SQL |
| Vínculo não muda de conta | recusa | PostgreSQL: gatilho |
| Chave ↔ projeto só da mesma conta | escopo da conta na busca dos projetos | PostgreSQL: gatilho |

A propriedade muda por **transferência**, que troca a pessoa do vínculo de dono
(fase seguinte). Os gatilhos ficam em `Account\Support\AccountDatabaseGuards`
e são instalados pela migration; como os outros gatilhos do kit, não protegem
de quem é dono do esquema no PostgreSQL (separação de papéis é decisão de
infraestrutura).

## Papéis

Papéis **fixos**. A matriz (`AccountRole::allows()`), também disponível no Gate
do Laravel com o prefixo `accounts.` (ex.: `@can('accounts.api-keys.manage')`),
sempre avaliada na conta atual:

| Ação (`AccountAbility`) | owner | admin | member |
| --- | :-: | :-: | :-: |
| Ver a conta: visão geral, projetos, chaves (sem a secreta) — `view` | ✓ | ✓ | ✓ |
| Criar projeto — `projects.create` | ✓ | ✓ | ✓ |
| Renomear/arquivar projeto — `projects.update` | ✓ | ✓ | ✓ |
| Excluir projeto — `projects.delete` | ✓ | ✓ | — |
| Criar, rotacionar, revogar chave e definir o vínculo com projetos — `api-keys.manage` | ✓ | ✓ | — |
| Convidar, remover e mudar papel de membros — `members.manage` (telas na fase seguinte) | ✓ | ✓ | — |
| Transferir a propriedade — `account.transfer` | ✓ | — | — |
| Excluir a conta — `account.delete` | ✓ | — | — |

Quem confere é a tela (`Accounts::authorize(...)` responde 403); os serviços
(`ProjectService`, `ApiKeyService`) não repetem a pergunta. No painel Livewire
do starter, as ações que o papel não permite somem da tela **e** são recusadas
no servidor. O dono da conta pessoal — o único caso que existia na 1.x — pode
tudo, como antes.

A **chave de API não tem papel**: é uma credencial da conta, limitada pelos
escopos e pelo vínculo com projetos. A **senha de transação** e a **ação
sensível** continuam sendo da **pessoa**.

## A conta atual

`Account\CurrentAccount` (singleton), consultada por
`Twstec\Kit\Accounts\Accounts`:

| Onde | De onde vem a conta |
| --- | --- |
| Painel (web) | a conta **selecionada na sessão** (`accounts.current`), se a pessoa ainda é membro; senão a **conta pessoal**. Seleção que deixou de valer sai da sessão (middleware `ResolveCurrentAccount`, instalado pelo pacote no grupo `web`). `Accounts::switchTo($conta)` seleciona (só conta de que a pessoa é membro) |
| API v1 | a conta da **chave** (`ResolveTenant`), desfeita no fim da requisição |
| `/admin` | **modo sistema** (todas as contas), declarado pelo plugin |
| Comando, seeder, job | o que o código declarar: `Accounts::asSystem('motivo', fn () => …)` ou `Accounts::actingAs($conta, fn () => …)`; o job restaura o contexto de quem o enfileirou |
| Nada disso | **nenhuma** — e consulta de dado de conta lança `MissingAccountContextException` |

```php
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Accounts\Account\Enums\AccountAbility;

Accounts::current();                                 // a conta atual (ou null)
Accounts::authorize(AccountAbility::DeleteProjects); // 403 se o papel não permite
Accounts::asSystem('relatório noturno', fn () => Project::query()->count()); // todas as contas
Accounts::actingAs($conta, fn () => dispatch(new GerarFatura));             // uma conta explícita
```

## Isolamento automático

Os models da conta usam `Account\Concerns\BelongsToAccount`:

- **Leitura:** o escopo global `AccountScope` põe `account_id = conta atual` em
  toda consulta — lista, contagem, `update`/`delete` em massa e as
  subconsultas de relação (`withCount`, `whereHas`). Sem conta e fora do modo
  sistema: **exceção**, nunca a lista de todas as contas.
- **Gravação:** `account_id` vem da conta atual; outra conta é recusada
  (`CrossAccountWriteException`), e a conta de um registro não muda. Em modo
  sistema a conta tem de ser informada. `created_by` vem de quem está agindo
  (a pessoa logada ou a pessoa por trás da chave).

**Modo sistema** — a única forma de ler dado de mais de uma conta — é
explícito (sempre com um motivo, que `Accounts::systemReason()` devolve) e
auditável: uma trava de arquitetura
(`starters/livewire/tests/Unit/Architecture/AccountIsolationTest.php`) lista
cada chamada no starter e nos pacotes e reprova a que não foi revisada. Hoje:

| Onde | Por quê |
| --- | --- |
| `ResolveTenant` | a busca da chave pela pública, antes de saber de que conta ela é |
| `api-keys:process-inactivity` | varre as chaves de todas as contas |
| `AccountService` (excluir conta; arrumar o que uma pessoa excluída deixou) | mexe numa conta que não é a atual |
| `/admin` (`OperateAdminPanelAsSystem`) | o operador vê todas as contas; modo sistema **da requisição** (`Accounts::systemModeForRequest`), porque o Livewire reaplica os middlewares persistentes antes do componente, num pipeline à parte |
| `DatabaseSeeder` e os seeders da demonstração | gravam em várias contas |

A mesma trava reprova `withoutGlobalScope(s)`, `newQueryWithoutScopes()`,
`newModelQuery()`, `->getQuery()` e query builder cru nas tabelas das contas
fora das migrations e dos gatilhos; outra confere que todo model com
`account_id` carrega o escopo.

**Jobs.** O payload de todo job enfileirado leva o contexto de quem o
enfileirou (`twsAccountContext`: a conta, ou o modo sistema e o motivo) e o
worker o restaura antes do job e o desfaz depois — sucesso, exceção ou falha.
Job enfileirado sem conta roda sem conta (e falha visível se tocar em dado de
conta). `Accounts::actingAs($conta, fn () => dispatch(...))` enfileira dentro
do contexto mesmo com o `PendingDispatch` devolvido pelo callback.

**Processos longos.** O fim de toda requisição HTTP desfaz o modo sistema da
requisição e o cache da sessão; a API desfaz o contexto da chave; os quadros
explícitos são sempre desempilhados por quem os empilhou.

**Trilha de requisições.** `request_logs` é do foundation e não tem o escopo: a
visão geral da conta filtra por `tenant_uuid` = uuid da conta (na conta
pessoal, o uuid da pessoa, o mesmo gravado na 1.x).

## Exclusão de pessoa

| Situação | O que acontece |
| --- | --- |
| Dona de conta **com outros membros** | **Recusada**: nada muda. No `/admin`, a ação some e, forjada, é recusada com o motivo na trilha de auditoria (`denied`); por qualquer outro caminho, o model lança `OwnerOfSharedAccountException`; por SQL, o gatilho do PostgreSQL recusa. Transfira a propriedade antes |
| Dona só de contas sem outros membros (a pessoal, no caso de hoje) | As contas saem **com os dados** (projetos, chaves, vínculos) — o mesmo efeito da 1.x, quando projetos e chaves eram da pessoa |
| Admin ou member de outra conta | Deixa de ser membro; a conta e os dados ficam, e o `created_by` do que criou fica vazio |
| Conta protegida (extensão de proteção, como a demonstração) | Continua protegida: a proteção recusa antes de qualquer efeito |

**Chaves de quem saiu.** A chave é da conta e continua valendo quando quem a
criou sai da conta ou é excluído. A pessoa por trás da chave (o que é por
pessoa, como o token de ação sensível, e o `$request->user()` da rota) é quem a
criou enquanto for membro ativo; depois, o dono da conta. O aviso aos donos
chega com a tela de membros.

**Conta bloqueada.** Dono da conta bloqueado, pendente ou sem e-mail confirmado
derruba as chaves da conta (401) — na conta pessoal, exatamente como a regra da
1.x para a pessoa.

## Tenancy na API

Middleware **`resolve.tenant`** (`ResolveTenant`, grupo `api/v1`; alias
instalado pelo pacote): valida o par pk_/sk_ (existência → hash timing-safe →
status → validade → grace de rotação → inatividade) → confere o dono da conta
→ define a **conta da chave** como conta atual da requisição e o tenant no
container (`tenant()` devolve a conta; `tenantKey()` a chave) → **vincula o
request log** (`tenant_uuid` = uuid da conta) → atualiza `last_used_at`
**throttled** (máx. 1 escrita a cada `API_KEYS_LAST_USED_THROTTLE_SECONDS`,
padrão 60s).

**Chave inválida = 401 padronizado** (mensagem única, não oracular) e o request
log permanece **SEM tenant** — exatamente o sinal de ataque/tentativa de burla.

**Limite de requisições por chave.** O `throttle:api` conta pela chave de API
autenticada (`RATE_LIMIT_API`, 60/min; `RATE_LIMIT_API_BY=tenant` soma as chaves
da mesma **conta** — o balde é nomeado pelo uuid da conta, que na conta pessoal
é o da pessoa, como na 1.x). Falhas de autenticação contam por IP + chave
pública (`RATE_LIMIT_API_AUTH_FAILURES`, 20/min) e têm um teto por IP
(`RATE_LIMIT_API_AUTH_FAILURES_PER_IP`, 100/min) que não barra chave já
autenticada daquele IP. Todo 429 sai no envelope de erro padrão
(`too_many_requests`) com `Retry-After`. Detalhes em
[Limite de requisições](seguranca.md#limite-de-requisições-rate-limit-e-contenção-da-trilha).

## Projetos (multi-empresa organizacional)

`projects` (PRJ-xxxxxx): uma conta tem N projetos; nascem só com nome. O
vínculo chave↔projeto é **N:N** e **opcional**. No MVP são metadados
organizacionais.

**A regra do vínculo (vale para toda a API v1):**

| | Chave **sem vínculo** (conta toda) | Chave **vinculada** a projetos |
|---|---|---|
| Listar / ver / alterar / excluir projeto | todos os da conta | só os vinculados; os demais, **mesmo da mesma conta**, são 404 uniforme |
| Criar projeto | sim (`projects:create`) | **403** — é operação de conta |
| Listar, criar, vincular chaves | sim (scopes `api-keys:*`) | **403** — uma chave vinculada que pudesse se vincular a "nada" viraria conta toda |
| Revogar / rotacionar chave | qualquer chave da conta | **só a si mesma** (nenhuma das duas amplia acesso; a rotação herda scopes **e** restrição) |

O vínculo **limita** o scope, nunca o contrário: a chave vinculada criada com o
padrão `*:*` continua sem poder fazer operação de conta.

**Restrição é estado da chave, não da lista** (`api_keys.restricted_to_projects`).
Excluir um projeto remove o vínculo em cascata e a chave segue restrita aos
projetos que sobraram — **ou a nenhum**, se era o último. Voltar à conta toda é
sempre uma ação explícita de quem gerencia as chaves: salvar a seleção vazia no
painel ou mandar `project_uuids: []` com uma chave de conta. O campo
`project_access` do recurso da chave (`account` | `projects`) mostra o estado.

No painel e na API, só se vincula projeto **da mesma conta** — uuid de outra
conta é erro de validação e nada muda (`ApiKeyService::resolveProjectIds`, que
consulta pelo escopo da conta; no PostgreSQL o gatilho também recusa).

Testes: `tests/Feature/Tenancy/ApiKeyProjectBindingTest.php`.

**Um serviço só para o CRUD de projetos.** A tela do painel (`/projects`) e a
API v1 chamam o `Twstec\Kit\Accounts\Tenancy\Services\ProjectService`; nenhuma
das duas lê ou grava `Project` direto (trava em
`tests/Unit/Architecture/BusinessLogicPlacementTest.php`). O serviço trabalha na
**conta atual**: `list()`/`find()` (o painel) e
`paginateForApiKey()`/`findForApiKey()` (a chave, com o vínculo e a marca de
restrição — `Project::visibleToApiKey()`). Fora do recorte é sempre a mesma
`ModelNotFoundException` (404 uniforme). `create($criador, $nome)`, `update`
(só `name` e `status`) e `delete` recebem quem criou ou o projeto já achado por
um recorte. Testes: `tests/Feature/Tenancy/ProjectServiceTest.php`.

## Migração da 1.x

As migrations `2026_09_26_000001_create_accounts_tables` e
`2026_09_26_000002_move_projects_and_api_keys_to_accounts` (do pacote):

1. criam `accounts` e `account_memberships`;
2. criam, para cada pessoa, a **conta pessoal com o mesmo `id` e o mesmo
   `uuid`** dela (o `id` só não é o mesmo se já estiver ocupado por outra conta
   — numa base que ainda não tinha contas, nunca), com ela como dona;
3. passam projetos e chaves para a conta pessoal do dono (`created_by` = o
   dono), por faixas de id (`ACCOUNTS_MIGRATION_CHUNK`, padrão 1000);
4. conferem que nenhuma linha ficou sem conta (senão, falham e nada muda — no
   PostgreSQL a migration roda numa transação);
5. tornam `account_id` obrigatório, tiram `user_id` e instalam os gatilhos.

Idempotente (rodar de novo continua de onde parou) e reversível
(`migrate:rollback` devolve `user_id` = o dono da conta). A trilha
(`request_logs`, `audit_events`) não é reescrita. No SQLite, onde mudar coluna
recria a tabela, os vínculos chave ↔ projeto são guardados e devolvidos pela
própria migration.
