# Logs e LGPD

## Redaction (LGPD)

`Twstec\Kit\Foundation\Logging\Redactor` mascara antes de persistir:

- chaves sensíveis por nome exato (`password`, `token`, `api_key`, `secret`, `authorization`,
  `card_number`, `cvv`...) ou sufixo (`_token`, `_secret`, `_password`, `_api_key`) → `[REDACTED]`;
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
| `storage/logs/*.log` (todos os canais) e agregadores (`stderr`, `syslog`, `papertrail`, `slack`) | tap `MaskCardNumbersInLogs` em `config/logging.php`: envolve o formatter de cada canal e mascara a **linha já formatada** — mensagem, contexto e a **exceção** (uma `QueryException` carrega os valores do INSERT). Canal novo precisa do mesmo tap (há teste que confere os existentes) |
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
| Arquivo (sobrevive a falha do banco) | `storage/logs/request-YYYY-MM-DD.log` | JSON estruturado, 1 linha por evento (`request.started`, `request.finished`, `security.blocked`, `security.observed`, `request.throttled`, `request.unmatched.sampled_out`, `audit.event`, `audit.persist_failed`); número de cartão sai mascarado (ver [Redaction](#redaction-lgpd)) |
| Borda | access log do nginx | tudo, inclusive health checks |

O `correlation_id` conecta as camadas: resposta (`X-Correlation-Id`), linha do banco e linhas de
arquivo da mesma requisição. Também entra no contexto compartilhado do Monolog
(`Log::shareContext`) — todo `Log::*` emitido durante a requisição o carrega.

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
| `context` | de onde partiu: `admin` e `console` hoje; `panel` e `api` já previstos (`AuditContext`) |
| `actor_uuid`, `actor_is_admin` | quem agiu e se era admin naquele momento (nulo no console) |
| `action` | nome estável `<tipo>.<verbo>`: `user.blocked`, `api_key.revoked`, `product.created`, `setting.changed`, `user.two_factor_enabled`, `user.admin_granted`... |
| `outcome` | `success` ou `denied` (tentativa recusada por guarda de servidor) |
| `subject_type`, `subject_uuid` | o registro afetado (`user`, `api_key`, `product`...) |
| `changes` | resumo do que mudou, `{campo: {before, after}}`, **já redigido** (abaixo) |
| `reason` | o motivo da recusa, quando `denied` (passa pelo Redactor) |
| `correlation_id` | o MESMO da linha de `request_logs` da requisição (nulo no console) |
| `ip`, `user_agent` | como a trilha de requisições trata: IP resolvido pelo TrustProxies, User-Agent em 500 caracteres. No console, o `user_agent` leva o comando e o usuário do sistema operacional |

Índices para as três perguntas de auditoria, todas por período: o que FULANO fez
(`actor_uuid`), o que aconteceu com ESTE registro (`subject_type` + `subject_uuid`) e onde aconteceu
ESTA ação (`action`).

**Como a gravação é central.** `App\Filament\Support\AdminAudit` se pendura no gancho `call` do
Livewire: toda chamada de componente do painel (o "Criar"/"Salvar" das páginas, toda Action de
tabela, cabeçalho ou modal) roda com um escopo de auditoria aberto — contexto, quem está logado,
correlação, IP e User-Agent. Enquanto ele está aberto, `Twstec\Kit\Foundation\Audit\AuditTrail` grava cada
`created`/`updated`/`deleted` de model Eloquent. Um resource novo, portanto, já nasce auditado,
sem nenhuma linha de código de auditoria. O verbo sai do nome da Action (`block` → `blocked`,
`revoke` → `revoked`; nome fora do mapa vira snake_case) ou, no CRUD, do evento do model. O teste
de arquitetura `tests/Feature/Architecture/AdminAuditTest.php` reprova o build nas portas que o
gancho não fecha: escrita que não dispara evento de model (query em massa, `DB::`, `*Quietly`,
`withoutEvents`), recusa montada à mão sem registro, componente fora da cobertura e resource com
escrita sobre model ignorado.

**Recusas.** Guarda de servidor que recusa uma ação chama `AdminAudit::denied()`, que registra a
linha `denied` com o motivo **e** mostra a notificação ao operador — uma chamada só, para não
existir recusa sem registro. Exemplos: bloquear/excluir/editar conta demo, excluir ou bloquear a
si mesmo, tirar o acesso do último admin, senha de transação ou código errados ao ligar/desligar o
segundo fator, `user:make-admin` em conta demo ou e-mail inexistente (o e-mail digitado **não** vai
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
liga a flag local `tws.audit_prune` só dentro da transação de cada lote. Em produção, complementar
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
