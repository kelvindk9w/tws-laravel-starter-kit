# Contas, isolamento e projetos

Pacote **`twstec/kit-accounts`** ([`packages/accounts`](../packages/accounts/README.md)).
A partir da 2.0 o dono dos dados é a **conta**, não a pessoa: projetos e chaves
de API pertencem a uma conta, e uma pessoa pode estar em várias contas, com um
papel em cada. Toda consulta de dado de conta sai **filtrada sozinha** pela
conta atual — e, sem conta atual, **dá erro em vez de devolver tudo**.

No painel, o **seletor de conta** mostra em todo o painel a conta atual (e o
papel da pessoa nela) e troca de conta; a **página da conta** (`/account`)
reúne membros, convites, transferência de propriedade, saída e exclusão; o
**convite** chega por e-mail e o link aceita — criando o acesso de quem ainda
não tem conta. O `/admin` opera em modo sistema (com a lista "Contas", só
leitura) e a API usa a conta da chave. Toda a regra mora em **Actions** do
pacote (`Account\Actions`), com as respostas HTTP em contratos: o starter
Livewire usa as Actions; outro front (o React) reaproveita as mesmas.

## O modelo

| Tabela | O que guarda |
| --- | --- |
| `accounts` | A conta: `uuid`, código público `ACC-xxxxxx`, `name` (nulo na conta pessoal) e `personal_user_id` (preenchido só na conta pessoal) |
| `account_memberships` | Pessoa × conta, com o papel (`owner`, `admin`, `member`); uma pessoa aparece uma vez por conta |
| `projects`, `api_keys`, `uploads` | `account_id` (a dona) e `created_by` (quem criou — pode ter saído da conta; o dado continua da conta). Nos uploads, a foto de perfil é a exceção: é da pessoa, sem conta — ver [`docs/uploads.md`](uploads.md) |

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
(ver [Transferir a propriedade](#transferir-a-propriedade)). Os gatilhos ficam em `Account\Support\AccountDatabaseGuards`
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
| Convidar, remover e mudar papel de membros — `members.manage` (quem mexe em quem: abaixo) | ✓ | ✓ | — |
| Renomear a conta — `account.update` | ✓ | ✓ | — |
| Transferir a propriedade — `account.transfer` | ✓ | — | — |
| Excluir a conta — `account.delete` | ✓ | — | — |

Quem confere é a tela; os serviços (`ProjectService`, `ApiKeyService`) não
repetem a pergunta. As telas de chaves e projetos dos starters conferem pelo
`Account\Support\AccountResourceGuard` (o 403 de `Accounts::authorize()`,
com a recusa na trilha — ver "Recusas das telas de chaves e projetos",
abaixo); `Accounts::authorize(...)` responde o mesmo 403 **sem** trilha, para
código que decide sozinho o que fazer com a recusa.

**O papel é consultado uma vez por requisição.** `Accounts::roleOf()`,
`can()`, `authorize()` e o Gate guardam o papel de cada conta + pessoa até o
fim da requisição (`CurrentAccount::roleFor`): uma tela que esconde seis
botões faz uma consulta, não seis. O papel guardado é esquecido na hora em que
um vínculo de membro muda (criado, alterado ou apagado — inclusive em massa,
na exclusão de conta ou de pessoa), no fim de cada requisição HTTP e a cada
job da fila (o worker é um processo longo): papel velho nunca vale de uma
requisição para outra. As Actions de conta continuam lendo o papel direto do
banco (`$account->roleOf()`), dentro da transação da mudança. No painel Livewire
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
| `AccountService` (excluir conta; arrumar o que uma pessoa excluída deixou; ler as chaves que ela deixa órfãs em contas alheias) | mexe numa conta que não é a atual |
| `InvitationTokens` (achar o convite pelo token; marcá-lo como aceito/recusado) | o link não diz de que conta é o convite — e quem aceita ainda não é membro dela |
| `/admin` (`OperateAdminPanelAsSystem`) | o operador vê todas as contas; modo sistema **da requisição** (`Accounts::systemModeForRequest`), porque o Livewire reaplica os middlewares persistentes antes do componente, num pipeline à parte |
| `DatabaseSeeder` e os seeders da demonstração | gravam em várias contas |
| `SecureUploadService::handlePersonal` (`twstec/kit-uploads`) | grava a foto de perfil, que é da pessoa (sem conta) |
| `HasAvatar` (`twstec/kit-uploads`) | lê a foto de perfil — restrita ao upload que a própria pessoa aponta, foto pessoal ou da conta pessoal dela |
| `UploadEraser` (`twstec/kit-uploads`) | exclusão da pessoa ou da conta (LGPD): lê e apaga os uploads de contas que somem |
| `uploads:prune-orphans` (`twstec/kit-uploads`) | limpeza agendada dos uploads sem dono, de todas as contas |

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
| Dona só de contas sem outros membros (a pessoal, no caso de hoje) | As contas saem **com os dados** (projetos, chaves, vínculos, **uploads** — registro e arquivo no disco) — o mesmo efeito da 1.x, quando projetos e chaves eram da pessoa. A foto de perfil e as fotos pessoais que ela enviou saem também |
| Admin ou member de outra conta | Deixa de ser membro; a conta e os dados ficam (inclusive os uploads que ela enviou lá), e o `created_by` do que criou fica vazio |
| Conta protegida (extensão de proteção, como a demonstração) | Continua protegida: a proteção recusa antes de qualquer efeito |

**Eventos para quem guarda dado das contas fora do pacote.** `PersonDeleting`
(no `deleting` da pessoa, depois de a regra das contas não recusar — **só para
ler**: outra guarda ainda pode recusar), `PersonDeleted` (a pessoa saiu; antes
de o pacote arrumar as contas) e `AccountDeleting` (em
`AccountService::deleteAccount`, dentro da transação, antes de qualquer linha
sair). O `twstec/kit-uploads` usa os três para apagar os arquivos (ver
[Exclusão em `docs/uploads.md`](uploads.md#exclusão-o-arquivo-sai-junto-lgpd)).

**Chaves de quem saiu.** A chave é da conta e continua valendo quando quem a
criou sai da conta ou é excluído. A pessoa por trás da chave (o que é por
pessoa, como o token de ação sensível, e o `$request->user()` da rota) é quem a
criou enquanto for membro ativo; depois, o dono da conta. Quando a pessoa sai
ou é removida pela página da conta, o dono e os admins recebem o
[aviso de chave órfã](#aviso-de-chave-órfã).

**Conta bloqueada.** Dono da conta bloqueado, pendente ou sem e-mail confirmado
derruba as chaves da conta (401) — na conta pessoal, exatamente como a regra da
1.x para a pessoa.

## Membros

**Quem mexe em quem** (`Account\Support\MemberRules` — a mesma regra esconde o
botão na tela e recusa a Action no servidor):

| Quem age | Convidar | Mudar papel (member ↔ admin) | Remover | Sair |
| --- | --- | --- | --- | --- |
| owner | como admin ou member | de qualquer admin ou member | qualquer admin ou member | não — transfere antes |
| admin | como admin ou member | só promove member a admin | só member | sim |
| member | — | — | — | sim |

- Ninguém mexe no **dono** (papel ou remoção): a propriedade só muda por
  transferência, que é do próprio dono.
- Admin não mexe em **outro admin** (rebaixar ou remover um admin é decisão do
  dono). Um admin pode promover um member — depois disso, só o dono mexe nele.
- Ninguém muda o **próprio** papel nem se remove: para isso existe "sair da
  conta".
- Pessoa que não é membro da conta (ou não existe) é **404** — o uuid não
  confirma nada.

**A conta pessoal também recebe membros.** Decisão: o modelo não distingue as
duas no que importa (papéis, isolamento, trilha), a conta pessoal é a de toda
pessoa migrada da 1.x (quem já tem projetos e chaves nela pode chamar alguém
sem precisar mover nada para uma empresa) e um bloqueio aqui seria regra a mais
sem ganho de segurança. O que a conta pessoal **não** faz: não é renomeada (o
nome é o da pessoa, dado cifrado que não é copiado), não é transferida (é da
pessoa) e não é excluída pela página (sai junto com a pessoa — e a exclusão da
pessoa segue recusada enquanto a conta tiver outros membros).

## Convites

`Account\Actions\InviteMember` (dono e admin), `ResendInvitation`,
`RevokeInvitation`, `AcceptInvitation`, `RegisterAndAcceptInvitation`,
`DeclineInvitation`.

- **Papel** admin ou member — nunca owner.
- **Token** de 32 bytes aleatórios (64 hex), que só existe em claro no e-mail;
  no banco, só o **hash SHA-256** (`account_invitations.token_hash`, fora da
  serialização). O caminho `/invitations/{token}` vai para a trilha de
  requisições como o **padrão** da rota — o token não é gravado.
- **Uso único** (`accepted_at` gravado com UPDATE condicional: dois aceites ao
  mesmo tempo não viram dois membros), **validade** configurável, **reenvio**
  (gera link novo — o antigo morre — e renova a validade), **revogação**,
  **recusa** pela pessoa convidada.
- **Recusas** na criação: e-mail inválido, quem já é membro, convite pendente
  para o mesmo e-mail (reenvie), limite de pendentes da conta, intervalo por
  conta e por pessoa (novos e reenvios contam juntos).
- **Sem enumeração:** o convite para um e-mail que já tem conta na plataforma e
  para um que não tem é criado igual, com a mesma resposta e o mesmo e-mail — é
  a tela do link que decide entre "entrar" e "criar a conta".
- **Aceite** só pela pessoa com o **mesmo e-mail** (sem diferença de caixa).
  Logada com outro e-mail: recusa clara (`wrong_email`), **sem nenhum dado da
  conta**. Deslogada com conta: entra e volta ao convite. Deslogada **sem
  conta**: o aceite **cria a conta** (nome + senha pela política do kit; o
  e-mail é o do convite) já **verificada** — o link só chega a quem lê aquela
  caixa. A conta logada que ainda não tinha o e-mail confirmado passa a ter.
- O convite é **dado da conta** (escopo da conta atual). Achar o convite pelo
  token e marcá-lo como aceito/recusado são as únicas operações em modo
  sistema (`Account\Invitations\InvitationTokens`, revisada na trava de
  arquitetura): o link não diz de que conta é o convite até ele ser achado.

| Variável | Padrão | O que faz |
| --- | --- | --- |
| `ACCOUNTS_INVITATION_EXPIRES_HOURS` | 168 | Validade do link |
| `ACCOUNTS_INVITATION_MAX_PENDING` | 20 | Convites pendentes por conta |
| `ACCOUNTS_INVITATION_THROTTLE_PER_ACCOUNT` / `_PER_PERSON` / `_MINUTES` | 30 / 20 / 60 | Intervalo dos convites |
| `ACCOUNTS_MAX_OWNED` | 10 | Contas de empresa de que uma pessoa pode ser dona |

## Transferir a propriedade

`Account\Actions\TransferOwnership`: só o **dono**, para um **membro** da
conta, com o **token de ação sensível** da pessoa — emitido só depois da
**senha de transação** e do **código por e-mail** (o mesmo mecanismo da
rotação de chave), consumido pela Action (uso único). Sem token válido, nada
muda (403 + `denied`). A troca é uma transação (`AccountService::transferOwnership`):
sai o vínculo do novo dono, o vínculo de dono troca de pessoa e o antigo dono
volta como **admin** — nunca zero nem dois donos (o índice e os gatilhos do
PostgreSQL continuam valendo). A conta pessoal não se transfere.

## Excluir a conta

`Account\Actions\DeleteAccount`: só o **dono**, com o token de ação sensível;
a conta sai com projetos, chaves, vínculos, convites e **uploads** (registro
na transação, arquivo no disco por job depois do commit); os membros ficam com
as contas deles. A conta pessoal não se exclui por aqui.

## Aviso de chave órfã

Quando alguém **sai** ou é **removido** da conta, as chaves de API que criou e
que ainda autenticam continuam valendo, e o **dono e os admins** (menos quem
saiu) recebem o e-mail "chave órfã" no idioma de cada um: quais chaves (nome,
código público, chave pública — **nunca** a secreta nem o hash) e um botão que
abre a tela de chaves **já na conta certa** (URL **assinada**: ninguém monta
um link que troca a conta de outra pessoa). A **exclusão da pessoa** também
avisa, por qualquer caminho (o `/admin`, um comando, o model direto): antes de
ela sair do banco, o pacote lê as chaves que ela criou em cada conta de que era
admin ou member (`AccountService::orphanedKeysOnPersonExit`, o terceiro ponto de
modo sistema do serviço) e, depois da exclusão, avisa o dono e os admins de
cada uma — uma vez por conta, pela fila, **só depois do commit** (exclusão
desfeita não avisa). Quem já saiu pela página não é mais membro na hora da
exclusão: não há aviso duplicado. Exclusão recusada (dona de conta com outros
membros) não avisa ninguém.

## Trilha de auditoria das contas

Todo evento de conta grava uma linha em `audit_events` (contexto `panel`),
**na mesma transação da mudança** — se a linha não grava, a mudança é desfeita
(falha fechada, como no `/admin`) —, com quem agiu, a conta (`tenant_uuid`), o
alvo (a pessoa, o convite ou a conta), o antes/depois redigido (e-mail
mascarado), IP, User-Agent e `correlation_id`. Cada **recusa** (papel, regra
de quem mexe em quem, token, limite, convite inválido/expirado/de outro
e-mail) grava a mesma ação com `outcome = denied` e o motivo, antes de a
exceção sair.

**Pré-checagem de papel também fica na trilha.** As telas conferem o papel
ANTES de abrir uma confirmação ou de mandar o código de uma ação sensível —
quem não pode nem começa. Essa conferência é a da própria Action:
`authorize($pessoa)` em `TransferOwnership`, `DeleteAccount`, `RenameAccount`,
`RemoveMember` e `RevokeInvitation`. A recusa é o mesmo 403, com a mesma
mensagem de `handle()`, e grava `denied` (a mesma ação, o motivo e quem
tentou); quem pode passa sem linha nenhuma (a linha de sucesso é da
operação). Os dois starters usam esse caminho: no Livewire, ao abrir renomear,
remover, revogar, transferir e excluir; no React, no pedido do código e no
envio de transferir e de excluir.

**Recusas das telas de chaves e projetos.** As telas de chaves de API e de
projetos não passam por uma Action de conta; passam pelo
`Account\Support\AccountResourceGuard`, e cada recusa grava `denied` com a
ação **tentada**, quem, a conta e o alvo:

| Recusa | Resposta (a de sempre) | Linha na trilha |
| --- | --- | --- |
| O papel não permite (`authorize()`) | 403, `accounts.authorization.denied` | a ação tentada, motivo = a mesma mensagem |
| Chave ou projeto fora da conta atual (`apiKey()`, `project()`) | 404 comum — **idêntico** para "de outra conta" e "não existe" (a guarda não consulta outra conta para distinguir) | a ação tentada, o uuid tentado (só se for uuid), motivo `accounts.authorization.not_found` |
| Projeto de fora da conta no vínculo da chave (`foreignProjects()`) | erro de validação `api_keys.projects.invalid` | `api_key.created` ou `api_key.projects_synced` |

As ações: `api_key.created`, `api_key.rotated`, `api_key.revoked`,
`api_key.projects_synced` (`ApiKeys\Enums\ApiKeyAttempt`) e
`project.created`, `project.updated`, `project.deleted`
(`Tenancy\Enums\ProjectAttempt`). Quem pode passa sem linha nenhuma.

**Na API v1 (por chave)**, a chave **autenticada** que tenta além do que
pode também fica na trilha, no contexto `api`, com a própria chave como alvo:
`api_key.scope_denied` (escopo que ela não tem — `EnsureApiKeyScope`) e
`api_key.account_key_required` (chave vinculada a projetos em operação de
conta — `EnsureAccountWideApiKey`). A resposta é o mesmo 403. **Ficam fora
da trilha**, de propósito, os **401** (credencial ausente ou inválida: não há
quem registrar) e os **404** (recurso de outra conta ou inexistente): os dois
são o sinal de quem varre a API, já vão para o `request_logs` (com o status
e, no 404, a conta da chave) e, gravados um a um em `audit_events`,
inflariam a trilha com o volume de um ataque.

| Ação (`AccountAuditEvent`) | Quando |
| --- | --- |
| `account.created` / `account.renamed` / `account.deleted` | conta criada, renomeada, excluída |
| `account.invitation_created` / `_resent` / `_revoked` | convite criado, reenviado, revogado |
| `account.invitation_accepted` / `_declined` | convite aceito (inclusive criando a conta), recusado pela pessoa |
| `account.member_role_changed` / `account.member_removed` / `account.member_left` | papel alterado, membro removido, membro saiu |
| `account.ownership_transferred` | propriedade transferida |
| `account.switched` | só a recusa (troca para conta de que a pessoa não é membro) |

## Telas (starter Livewire)

- **Seletor de conta** (`<x-account-switcher>`, no topo da coluna do painel e
  no topo do conteúdo no celular): conta atual e papel; as contas da pessoa
  com o papel em cada; troca por `POST /accounts/{uuid}/switch` (controller do
  pacote, que só aceita conta de que a pessoa é membro — senão 403 e `denied`).
- **Página da conta** (`/account`): dados (nome, código, tipo), membros
  (tabela ou cartões — `<x-view-toggle>`), convidar, convites em aberto
  (reenviar, revogar), transferir, sair, excluir. Ações de linha só com ícone
  colorido + tooltip (`<x-icon-button>`). O botão que o papel não permite
  **some** e a ação chamada por fora é **recusada no servidor** (403).
- **Criar conta** (`/accounts/create`).
- **Tela do convite** (`/invitations/{token}`, pública) com os estados de erro
  (expirado, revogado, já usado, recusado, e-mail diferente, já é membro,
  link inválido).

Um front novo reaproveita as Actions, o `Account\Queries\AccountDirectory`
(as leituras das telas), o `Account\Invitations\InvitationPreview` (o que a
tela do link pode mostrar) e os controllers do pacote com os contratos de
resposta (`Account\Contracts\Responses`: aceite, recusa, convite
indisponível, troca de conta — padrão com `bindIf`, o do aplicativo vence).

## /admin

"Contas" (`Twstec\Kit\Admin\Resources\Accounts`), **somente leitura**: lista
todas as contas (tipo, dono, membros, projetos, chaves; filtro por tipo;
busca por nome, código e e-mail do dono) e o detalhe com os membros e papéis
e os projetos e chaves da conta (só identificadores públicos). Nada de editar
membros pelo `/admin`: isso é da própria conta, com a regra do dono e a trilha.

Nas outras telas, a conta de cada linha aparece e filtra: projetos, chaves e
**uploads** mostram a conta (código, com filtro por conta); os uploads mostram
também quem enviou e o tipo (da conta, foto pessoal, órfão). A **Auditoria**
filtra pela conta em que a ação aconteceu (`audit_events.tenant_uuid`).

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
