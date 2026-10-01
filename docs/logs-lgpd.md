# Logs e LGPD

## Redaction (LGPD)

`Twstec\Kit\Foundation\Logging\Redactor` mascara antes de persistir:

- chaves sensíveis por nome exato (`password`, `token`, `api_key`, `secret`, `authorization`,
  `card_number`, `cvv`, `signature` — a assinatura de URL assinada abre o recurso dentro da
  validade...) ou sufixo (`_token`, `_secret`, `_password`, `_api_key`) → `[REDACTED]`;
- CPF/CNPJ em qualquer string → `123.***.***-09` (3 primeiros + 2 últimos dígitos);
- e-mails → `k***@dominio.com`;
- **número de cartão (PAN) em qualquer string** → `**** **** **** 1111` (só os 4 últimos
  dígitos, pontuação preservada) — detalhes abaixo;
- os **nomes dos campos** passam pelas mesmas máscaras (um cartão mandado como nome de campo
  não escapa por ser chave);
- strings gigantes são truncadas (logs não são storage de payload).

**Cartão em texto livre (PCI DSS).** Antes, o cartão só era mascarado quando vinha num campo
com nome conhecido (`card_number`); digitado num `message`, ia em claro para `request_logs`.
Agora qualquer sequência de **13 a 19 dígitos**, corrida ou com **um** espaço/hífen entre os
dígitos, que passe no **algoritmo de Luhn** (o dígito verificador de todo cartão) é mascarada.
O Luhn é o que deixa em paz telefone, protocolo e número de pedido comuns — mas ele não é
infalível: cerca de 1 em cada 10 sequências arbitrárias nessa faixa passa, e aí o número é
mascarado mesmo sem ser cartão. Foi escolhido errar para o lado seguro. Telefones brasileiros
com DDD têm 10–11 dígitos e ficam fora da faixa; com `+55` chegam a 13 e dependem do Luhn.

Onde a regra vale (`Redactor::maskCardNumbers`, usada por todos os pontos abaixo):

| Destino | Como |
|---|---|
| `request_logs.payload` (INICIADA, BLOQUEADA) e `error_message` | `Redactor::redactArray`/`redactString`, como CPF/e-mail |
| `storage/logs/*.log` (todos os canais) e agregadores (`stderr`, `syslog`, `papertrail`, `slack`) | tap `MaskCardNumbersInLogs` em `config/logging.php` (e no canal `request_log`, que vem do pacote foundation — ver `Logging\RequestLogChannel`): envolve o formatter de cada canal e mascara a **linha já formatada** — mensagem, contexto e a **exceção** (uma `QueryException` carrega os valores do INSERT). Canal novo precisa do mesmo tap (há teste que confere os existentes) |
| `form_submissions` (contato e forms demo) | `FormSubmissionGuard`, só a regra de cartão — ver abaixo |
| E-mail do formulário de contato (e o job na fila) | o controller envia o texto já gravado, portanto mascarado |

**Por que `form_submissions` também mascara, sendo "gravado cru" por decisão de auditoria.**
A gravação crua existe para preservar a evidência de ATAQUE — o payload de XSS/SQLi como veio.
Um número de cartão não é evidência de ataque, e PCI DSS (requisito 3) proíbe armazenar PAN
legível sem necessidade de negócio; uma mensagem de contato nunca tem essa necessidade. Então:
a detecção de ataque roda **antes**, sobre o texto original (mascarar não esconde ataque — há
teste), e só o texto persistido perde os dígitos do cartão. CPF e e-mail continuam crus ali:
são o dado do próprio contato, necessário para respondê-lo.

## Onde ver os logs

| Camada | Onde | Conteúdo |
|---|---|---|
| Banco (principal) | tabela `request_logs` | ciclo INICIADA→CONCLUIDA/ERRO/BLOQUEADA, payload redigido (neutralizado quando há `attack_type`), duração, IP, tenant |
| Banco (ações) | tabela `audit_events` | quem fez o quê, em qual registro, o antes/depois redigido e se foi executada ou recusada — ver [Trilha de auditoria de ações](#trilha-de-auditoria-de-ações-audit_events) |
| Banco (saída) | tabela `outbound_http_logs` | cada chamada HTTP de saída (uma linha por tentativa): destino normalizado, método, status, duração, tamanhos, `correlation_id` e conta — ver [Rastreio de ponta a ponta](#rastreio-de-ponta-a-ponta-fila-e-http-de-saída) |
| Arquivo (sobrevive a falha do banco) | `storage/logs/request-YYYY-MM-DD.log` | JSON estruturado, 1 linha por evento (`request.started`, `request.finished`, `security.blocked`, `security.observed`, `request.throttled`, `request.unmatched.sampled_out`, `audit.event`, `audit.persist_failed`, `http.outbound`, `http.outbound.persist_failed`); número de cartão sai mascarado (ver [Redaction](#redaction-lgpd)) |
| Borda | access log do nginx | tudo, inclusive health checks |

O `correlation_id` conecta as camadas: resposta (`X-Correlation-Id`), linha do banco e linhas de
arquivo da mesma requisição. Também entra no contexto compartilhado do Monolog
(`Log::shareContext`, chaves `correlation_id` e `correlation_origin`) — todo `Log::*` emitido
durante a requisição o carrega. E ele **segue a operação**: o job que a requisição despachou, o
job que esse job despachou e a chamada HTTP que qualquer um deles fez a um serviço externo levam o
mesmo id — ver [Rastreio de ponta a ponta](#rastreio-de-ponta-a-ponta-fila-e-http-de-saída).

**Ações de admin.** A linha de `request_logs` de uma ação no `/admin` é o update do Livewire, com
payload resumido (só o nome do componente) — ela prova que houve a requisição, não QUAL ação foi
feita. Quem diz isso é a [trilha de auditoria de ações](#trilha-de-auditoria-de-ações-audit_events),
ligada a ela pelo mesmo `correlation_id`.

## Trilha de auditoria de ações (`audit_events`)

Decisão do dono: **toda ação de admin que altera dado fica registrada no banco**. A tabela
`audit_events` guarda uma linha por ação:

| Coluna | Conteúdo |
|---|---|
| `uuid`, `occurred_at` | identificador e momento (UTC) |
| `context` | de onde partiu: `admin`, `panel` (os eventos de conta do painel) e `console`; `api` já previsto (`AuditContext`) |
| `actor_uuid`, `actor_is_admin` | quem agiu e se era admin naquele momento (nulo no console) |
| `action` | nome estável `<tipo>.<verbo>`: `user.blocked`, `api_key.revoked`, `product.created`, `setting.changed`, `user.two_factor_enabled`, `user.admin_granted`... |
| `outcome` | `success` ou `denied` (tentativa recusada por guarda de servidor) |
| `subject_type`, `subject_uuid` | o registro afetado (`user`, `api_key`, `product`, `account_invitation`...) |
| `tenant_uuid` | a CONTA em que a ação aconteceu (o mesmo valor de `request_logs.tenant_uuid`); nulo quando a ação não é de uma conta |
| `changes` | resumo do que mudou, `{campo: {before, after}}`, **já redigido** (abaixo) |
| `reason` | o motivo da recusa, quando `denied` (passa pelo Redactor) |
| `correlation_id` | o MESMO da linha de `request_logs` da requisição; num job ou numa tarefa agendada, o da operação que o originou; nulo num comando avulso |
| `ip`, `user_agent` | como a trilha de requisições trata: IP resolvido pelo TrustProxies, User-Agent em 500 caracteres. No console, o `user_agent` leva o comando e o usuário do sistema operacional |

Índices para as quatro perguntas de auditoria, todas por período: o que FULANO fez
(`actor_uuid`), o que aconteceu com ESTE registro (`subject_type` + `subject_uuid`), onde aconteceu
ESTA ação (`action`) e o que aconteceu NESTA conta (`tenant_uuid`).

**Eventos de conta.** Convites (criado, reenviado, revogado, aceito, recusado), membros (papel
alterado, removido, saiu), propriedade transferida e conta criada/renomeada/excluída gravam uma
linha cada, pelas Actions do pacote de contas, na mesma transação da mudança (falha fechada) — e
cada recusa como `denied`, com o motivo. Lista e regras em [tenancy.md](tenancy.md#trilha-de-auditoria-das-contas).

**Eventos de uploads (LGPD).** Quando a pessoa ou a conta é excluída, os uploads dela saem do
banco e do disco ([uploads.md](uploads.md#exclusão-o-arquivo-sai-junto-lgpd)) e a trilha registra,
sempre só com **contagens e motivo** — nunca o caminho do arquivo nem o conteúdo:

| Ação | Quando | O que guarda |
| --- | --- | --- |
| `upload.erased` | os registros saíram, na transação da exclusão (no `/admin`, com o operador como ator) | quantos, o motivo (`person_deleted` / `account_deleted`) e a conta (`tenant_uuid`) na exclusão de conta |
| `upload.files_deleted` | o job da fila apagou os arquivos, depois do commit (contexto `console`) | quantos eram, quantos saíram, quantos já não existiam, o motivo |
| `upload.orphans_pruned` | a limpeza `uploads:prune-orphans` apagou algo (contexto `console`) | quantos órfãos, fotos pessoais sem uso e arquivos sem registro |
| `upload.erasure_refused` (`denied`) | a exclusão do dono pediu o apagamento de um upload sob **guarda legal**: ele ficou, desvinculado ([uploads.md](uploads.md#retenção-legal-guardar-até)) | uma linha por upload: o arquivo (`subject_uuid`), a conta de onde saiu e, no motivo, o código, o prazo e o motivo da guarda |
| `upload.legal_hold_placed` / `upload.legal_hold_released` | pôr/mudar ou tirar a guarda legal | o prazo e o motivo, antes e depois (e o motivo de tirar) |
| `upload.erased` com motivo `legal_hold_expired` | o `uploads:erase-expired-holds` apagou o desvinculado cuja guarda venceu | quantos |
| `upload.confidential_url_issued`, `upload.confidential_viewed`, `upload.confidential_downloaded` | gerar a URL, visualizar e baixar um upload **confidencial** — e as recusas (`denied`) | quem, a conta, o arquivo, o contexto (`panel`, `api`, `admin`), IP e User-Agent ([uploads.md](uploads.md#entrega-e-trilha-de-acesso)) |
| `upload.reencrypted` | a rotação `uploads:reencrypt` recifrou arquivos (contexto `console`) | quantos, quantos falharam, quantos faltam e o id (não a chave) da chave atual |

**Exclusão × apagamento.** "Exclusão" é o pedido sobre o titular (a pessoa, a conta); "apagamento"
é sumir com registro e arquivo. A exclusão pede o apagamento do que é do titular, menos o que a
lei manda guardar ([guarda legal](uploads.md#retenção-legal-guardar-até)) — e um registro do
aplicativo pode impedir a exclusão inteira ([impedimentos de exclusão](tenancy.md#impedimentos-de-exclusão)),
com a recusa na trilha.

Falha definitiva ao apagar arquivo (tentativas esgotadas) vai para o log de aplicação
(`upload.files_delete_failed`, só contagens); o arquivo que sobrou sai na limpeza seguinte. No
`/admin`, a Auditoria filtra pela conta (`tenant_uuid`).

**Como a gravação é central.** `Twstec\Kit\Admin\Support\AdminAudit` (pacote twstec/kit-admin) se pendura no gancho `call` do
Livewire: toda chamada de componente do painel (o "Criar"/"Salvar" das páginas, toda Action de
tabela, cabeçalho ou modal) roda com um escopo de auditoria aberto — contexto, quem está logado,
correlação, IP e User-Agent. Enquanto ele está aberto, `Twstec\Kit\Foundation\Audit\AuditTrail` grava cada
`created`/`updated`/`deleted` de model Eloquent. Um resource novo, portanto, já nasce auditado,
sem nenhuma linha de código de auditoria. O verbo sai do nome da Action (`block` → `blocked`,
`revoke` → `revoked`; nome fora do mapa vira snake_case) ou, no CRUD, do evento do model. O teste
de arquitetura (`tests/Architecture/AdminAuditTest.php` do pacote e `tests/Feature/Architecture/AdminAuditTest.php` do starter) reprova o build nas portas que o
gancho não fecha: escrita que não dispara evento de model (query em massa, `DB::`, `*Quietly`,
`withoutEvents`), recusa montada à mão sem registro, componente fora da cobertura e resource com
escrita sobre model ignorado.

**Recusas.** Guarda de servidor que recusa uma ação chama `AdminAudit::denied()`, que registra a
linha `denied` com o motivo **e** mostra a notificação ao operador — uma chamada só, para não
existir recusa sem registro. Exemplos: bloquear/excluir/editar conta demo, excluir ou bloquear a
si mesmo, tirar o acesso do último admin, senha de transação ou código errados ao ligar/desligar o
segundo fator, foto de perfil apontando para upload de outra pessoa, `user:make-admin` em conta demo ou e-mail inexistente (o e-mail digitado **não** vai
para a trilha).

**O que fica fora da captura automática** (`config/audit.php`, `ignored_models`): `request_logs`
(é a outra trilha), `settings` (o `SettingsManager` grava ele mesmo `setting.changed`, com a chave e
o de/para legíveis — nulo = sem sobreposição, vale o `.env`) e os códigos/tokens temporários do
fluxo de confirmação (o que se audita é o resultado, ex.: `user.two_factor_enabled`). Também fica
fora o que não é ação do painel: a troca de idioma (rota própria, preferência da própria conta) e
as telas de login.

**Redação do `changes` (LGPD).** `Twstec\Kit\Foundation\Audit\AuditChanges`:

- **segredo → `[REDACTED]`**: todo campo que o Redactor trata como segredo (`password`, `token`,
  `code`, `api_key`...), todo campo cujo nome contenha `password`/`secret`/`token`/`hash`/`pepper`/
  `salt`/`private`/`otp`/`cvv`/`cvc` e todo atributo `$hidden` do model (ex.: `secret_hash` da chave
  de API, `remember_token`). O campo aparece — fica registrado QUE a senha mudou —, o valor não;
- **dado pessoal cifrado em repouso** (cast `encrypted*`, como o nome do usuário) → só as iniciais:
  `Maria Silva` → `M*** S***`;
- **e-mail, CPF/CNPJ e cartão** em qualquer texto → mascarados pelo Redactor (`k***@dominio.com`);
- `id`, `created_at` e `updated_at` ficam de fora; textos longos são cortados.

**Falha fechada.** O painel liga `databaseTransactions()`: toda Action e todo Criar/Salvar roda numa
transação, e a linha da trilha é gravada dentro dela (as páginas próprias — Configurações e Perfil —
e o `user:make-admin` abrem a transação à mão). Se o INSERT em `audit_events` falhar, a mudança é
desfeita e o operador vê o erro: ação de admin sem registro não acontece. `Halt`/`Cancel` (o fluxo
das recusas) confirmam a transação, então a linha `denied` fica.

**Segunda camada no arquivo (decisão).** Depois do commit, a mesma ação vira a linha `audit.event`
no canal `request_log`, com o `correlation_id`, o `uuid` da linha do banco e só os **nomes** dos
campos alterados — os valores ficam no banco. Mantida porque cobre o que o banco sozinho não cobre:
quem tem acesso de dono à tabela (pode desligar gatilho ou apagar a tabela) e a coleta por
agregadores de log. Se o próprio INSERT falhar, o arquivo recebe `audit.persist_failed` com a ação
que não pôde ser registrada. A antiga linha `admin.action` (`AdminAuditTrail`, só arquivo) saiu.

```bash
grep '"audit.event"' storage/logs/request-*.log
```

**Tela.** `/admin/audit-events` (grupo "Segurança e auditoria"): somente leitura, filtros por ação,
resultado, origem, quem agiu, registro afetado (tipo + uuid), correlation_id e período; detalhe com o
resumo como foi gravado, link para o registro afetado (quando ainda existe) e para a linha da trilha
de requisições pelo `correlation_id`. O detalhe da requisição aponta de volta para as ações dela.

**Retenção.** `AUDIT_RETENTION_DAYS` (padrão **365**; `0` = nunca podar). O `audit:prune` roda
diariamente (`routes/console.php`, `onOneServer`), como a poda da `failed_jobs`, e deixa rastro:
quando apaga alguma coisa, grava `audit_event.pruned` (contexto `console`) com quantas linhas saíram
e a data de corte. Rodar à mão: `php artisan audit:prune` (ou `--days=N`).

## Rastreio de ponta a ponta (fila e HTTP de saída)

O primeiro passo de qualquer investigação é seguir UMA operação de ponta a ponta: a requisição →
o job que ela despachou → a chamada ao serviço externo → o retorno. O pacote `twstec/kit-foundation`
faz o `correlation_id` acompanhar tudo isso sozinho, sem o aplicativo lembrar de nada
(`config/tracing.php`; módulo `Tracing`).

### De onde vem o id que vale agora

O id nasce uma vez e segue a operação. `CorrelationId::current()` devolve o que vale no momento;
`CorrelationId::origin()` diz onde ele nasceu:

| Origem | Quando |
|---|---|
| `http` | a requisição (gerado pelo servidor no `RequestLogging` — nunca vem do cliente). Rotas leves, fora da trilha em banco (health check, assets), também ganham id |
| `scheduler` | cada tarefa do agendador (`schedule:run`) ganha um id próprio; um `command()`/`exec()` roda em outro processo, e o id chega a ele pelo Context do Laravel (escondido, `hidden`) e é adotado no boot |
| `queue` | job que chegou ao worker SEM id no payload (enfileirado antes desta versão, ou por um produtor de fora do Laravel) |
| `console` | comando artisan avulso: o id nasce na primeira vez que for preciso (primeiro job despachado, primeira chamada de saída) e vale para o resto do processo |

O id corrente fica no contexto compartilhado do log (`correlation_id`, `correlation_origin`): todo
`Log::*` de dentro de um job sai com o id da requisição que o despachou.

### Fila

Todo job despachado — em qualquer driver (`database`, `redis`, `sync`) — leva no payload a chave
`twsCorrelation` = `{id, origin}`. **Só isso**: nenhum dado da requisição, nenhum segredo. No worker,
antes do job, o id é restaurado (contexto do log, trilhas, chamadas de saída); depois do job — com
sucesso, exceção, falha ou devolução à fila —, desfeito: o job seguinte não herda nada. Um id que
não é UUID (payload adulterado no Redis ou no banco) é descartado e o job ganha um novo, de origem
`queue` — o valor do payload nunca vai ao log como veio.

`Bus::chain` e `Bus::batch` herdam sozinhos: o próximo elo da cadeia é despachado de dentro do job
anterior (com o id restaurado), os jobs do lote são enfileirados juntos no contexto de quem
despachou, e os callbacks do lote (`then`/`catch`/`finally`) rodam no worker do último job.

Ler o id num job:

```php
use Twstec\Kit\Foundation\Logging\CorrelationId;

public function handle(): void
{
    $id = CorrelationId::current();           // o mesmo da requisição que despachou
    $origem = CorrelationId::origin()?->value; // 'http', 'scheduler', 'queue' ou 'console'

    Log::info('cobrança processada');          // já sai com correlation_id no contexto
}
```

A trilha de auditoria de ações também usa o id: uma linha gravada por um job (contexto `console`)
leva o `correlation_id` da operação que o originou.

### Chamadas HTTP de saída

Um middleware **global** do cliente `Http` do Laravel (instalado pelo pacote com
`Http::globalMiddleware`) cuida de toda chamada — `Http::post(...)`, `retry()`, `pool()`, `async()`,
e também com `Http::fake()` nos testes:

- **Cabeçalho** `X-Correlation-Id` (nome em `TRACING_HTTP_HEADER_NAME`) com o id corrente. Não
  sobrescreve o mesmo cabeçalho posto pela própria chamada. Desligável **por destino**
  (`TRACING_HTTP_HEADER_EXCEPT_HOSTS`, aceita curinga `*.exemplo.com`) e **por chamada**:
  `Http::withoutCorrelationHeader()->post(...)`. O id é um UUID v7 interno; um destino que não deve
  recebê-lo vai na lista.
- **Trilha** `outbound_http_logs`: uma linha **por tentativa**, gravada quando a resposta (ou a
  falha de conexão) chega.

| Coluna | Conteúdo |
|---|---|
| `uuid`, `created_at` | identificador e momento (UTC) |
| `correlation_id`, `correlation_origin` | a operação de origem — junta com `request_logs` e `audit_events` |
| `tenant_uuid` | a conta em nome da qual a chamada foi feita (com o pacote de contas instalado; nulo fora de conta) |
| `method`, `host` | método e host (minúsculo; porta só quando não é a padrão; **sem** usuário/senha da URL) |
| `path` | a rota **normalizada**: segmento que parece valor vira marcador — número, CPF/CNPJ/cartão e qualquer segmento com 6+ dígitos → `{n}`, UUID → `{uuid}`, e-mail → `{email}`, token/hash/chave → `{token}`. `/v1/customers/123.456.789-09/charges` → `/v1/customers/{n}/charges` |
| `query_keys` | só os **nomes** dos parâmetros da query (`["access_token", "page"]`) — nunca os valores |
| `attempt` | a tentativa (1, 2, 3… no `retry()`) |
| `http_status`, `duration_ms` | status (nulo na falha de conexão) e duração |
| `request_bytes`, `response_bytes` | tamanho do corpo enviado e recebido |
| `error` | na falha de conexão: o tipo do erro e a mensagem redigida, **sem a URL** (a mensagem do cURL repete a URL inteira, com a query) |
| `request_body`, `response_body` | nulos — salvo com `Http::withBodyInTrail()`, e então **redigidos** pelo `Redactor` (segredos por nome de campo, CPF/CNPJ, e-mail e cartão em qualquer texto); corpo que não é JSON nem formulário, ou acima de 64 KB, entra só como tipo e tamanho |

**O que nunca entra:** cabeçalho nenhum (`Authorization`, `Cookie`, chaves de API), valor de
parâmetro da query, usuário/senha da URL e — sem o pedido explícito — o corpo. A segunda camada no
arquivo (`http.outbound` no canal `request_log`) tem as mesmas colunas, nunca o corpo.

**Fail-open só da trilha.** Qualquer erro ao montar ou gravar a linha é engolido e vira
`http.outbound.persist_failed` (`critical`) no canal de arquivo; a chamada nunca é afetada — a
resposta, ou a exceção de conexão, chega ao aplicativo exatamente como chegaria sem o pacote. O
contrário (a chamada falhar porque a trilha falhou) derrubaria pagamentos por causa de um log.

**Transação.** A linha é gravada na conexão padrão, dentro da transação em que a chamada
aconteceu: se ela for desfeita, a linha do banco some junto (a do arquivo fica). Para a linha
sobreviver ao rollback, aponte `TRACING_HTTP_TRAIL_CONNECTION` para uma conexão própria (o mesmo
banco, outra conexão em `config/database.php`).

**Tentativa.** O Laravel refaz a chamada passando de novo pelo middleware, sem dizer qual é a
tentativa; a regra reproduz a do próprio `retry()`: mesmo método e mesma URL logo depois de uma
falha = tentativa seguinte. Se o aplicativo trocar as opções globais do cliente depois do boot
(`Http::globalOptions(...)`), o contador não chega e `attempt` fica nulo — o resto da linha não muda.

**Decisão: tabela própria só-acréscimo, não o canal `request_log`.** O volume é de uma linha por
chamada de saída — da ordem do de `request_logs` ou menor, e com o mesmo perfil de crescimento — e
as perguntas de investigação são consultas, não buscas em texto: tudo desta operação
(`correlation_id`), tudo deste destino num período (`host` + `created_at`, para medir
indisponibilidade de um parceiro) e tudo desta conta (`tenant_uuid` + `created_at`). Isso pede
índice e junção com `request_logs`/`audit_events`, o que o arquivo diário não dá; e a trilha
precisa da mesma garantia de imutabilidade das outras. O arquivo continua como segunda camada.

**Só-acréscimo, nas mesmas três camadas de `audit_events`:** o model e o builder recusam
UPDATE/DELETE (`AppendOnlyViolationException`) e, no PostgreSQL, o gatilho `OutboundHttpLogTrigger`
recusa UPDATE, TRUNCATE e todo DELETE fora da poda. A linha também fica fora da captura automática
da auditoria de ações (é efeito de uma ação, não ação).

**Retenção.** `TRACING_HTTP_RETENTION_DAYS` (padrão **90**; `0` = nunca podar). A poda
`outbound-http:prune` é agendada **pelo próprio pacote** (`TRACING_HTTP_PRUNE_SCHEDULE`, padrão
`20 3 * * *`, `onOneServer`; vazio desliga, com aviso no log) e deixa rastro na auditoria
(`outbound_http_log.pruned`, com quantas linhas saíram e a data de corte). À mão:
`php artisan outbound-http:prune` (ou `--days=N`).

```sql
-- Tudo de uma operação: a requisição, as ações e as chamadas de saída.
SELECT * FROM request_logs       WHERE correlation_id = :id;
SELECT * FROM audit_events       WHERE correlation_id = :id;
SELECT * FROM outbound_http_logs WHERE correlation_id = :id ORDER BY id;

-- Saúde de um parceiro na última hora.
SELECT http_status, count(*), avg(duration_ms)
  FROM outbound_http_logs
 WHERE host = 'api.parceiro.com' AND created_at > now() - interval '1 hour'
 GROUP BY http_status;
```

### Desligar (opt-out explícito)

| Variável | Efeito |
|---|---|
| `TRACING_QUEUE=false` | jobs sem o id de quem despachou |
| `TRACING_HTTP_HEADER=false` | chamadas de saída sem o cabeçalho |
| `TRACING_HTTP_TRAIL=false` | chamadas de saída sem a trilha |
| `TRACING_HTTP_PRUNE_SCHEDULE=` | poda não agendada (o comando continua) |

Os três primeiros deixam aviso no log a cada boot em produção; o último, em todo ambiente.

## O que significa um log INICIADA "órfão"

Log que **permanece em INICIADA** = a requisição não chegou ao terminate: processo morto no meio,
timeout fatal, bug que derrubou o worker ou ataque que explorou falha. **É sinal de incidente —
investigar.** Consulta rápida:

```sql
SELECT * FROM request_logs WHERE status = 'INICIADA' AND created_at < now() - interval '5 minutes';
```

Da mesma forma, log com **`attack_type`** = tentativa de ataque registrada — **BLOQUEADA** no
modo `block`, CONCLUIDA/ERRO no modo `observe` (ver `attack_type`, `ip`, `endpoint`) —, e log **sem `tenant_uuid`** (credencial inválida/ausente — o tenant não foi
resolvido) = possível tentativa de acesso sem credencial válida.

## Append-only

`request_logs` é imutável pela aplicação: `update()`/`delete()` via Eloquent lançam
`AppendOnlyViolationException`. As únicas mutações são as transições controladas do model
(`markFinished()`, `bindTenant()`/`bindTenantByCorrelationId()` — usado pelo middleware
`resolve.tenant` para vincular o tenant ao log quando a secret key é resolvida).
Em produção, complementar com
`REVOKE UPDATE, DELETE` da role da aplicação no PostgreSQL.

`audit_events` é imutável em **três camadas**:

1. **instância**: `updating`/`deleting` do model lançam `AppendOnlyViolationException`;
2. **query em massa pelo Eloquent**: o builder próprio (`AuditEventBuilder`) recusa `update`,
   `delete`, `upsert`, `increment`, `touch`, `truncate` e `updateOrInsert` — o buraco que os eventos
   de model não veem;
3. **banco (PostgreSQL)**: o gatilho `AuditEventTrigger` recusa UPDATE, TRUNCATE e todo DELETE fora
   da poda — vale também para `DB::table()`, psql e qualquer cliente externo.

A única remoção é a poda por idade (`AuditEvent::pruneOlderThan()`, usada pelo `audit:prune`): ela
liga a flag local `tws.audit_prune` só dentro da transação de cada lote. `outbound_http_logs` segue
as mesmas três camadas (gatilho `OutboundHttpLogTrigger`, flag `tws.outbound_http_prune`, poda
`outbound-http:prune`). Em produção, complementar
com separação de papéis no PostgreSQL — a aplicação conectando com uma role que **não é dona** do
esquema (dono pode desligar gatilho e apagar tabela) e `REVOKE UPDATE ON audit_events` dessa role. O
`DELETE` continua concedido, porque é a própria aplicação que poda; quem quiser tirá-lo também
precisa rodar o `audit:prune` com uma role de manutenção própria.

## Health check

`GET /api/health` → `{data: {status, version, correlation_id}}` (via `BaseResource`).
**Decisão**: excluído do request log em banco para não poluir a trilha (health checks são
barulhentos) — configurável em `REQUEST_LOG_EXCLUDED_PATHS`. Continua protegido por validação
de segurança, headers e rate limit (o `/up` também passa pelo teto da borda — uma sonda a cada
poucos segundos fica muito abaixo dele), e fica no access log do nginx.
