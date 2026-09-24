# Logs e LGPD

## Redaction (LGPD)

`App\Core\Logging\Redactor` mascara antes de persistir:

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
| Arquivo (sobrevive a falha do banco) | `storage/logs/request-YYYY-MM-DD.log` | JSON estruturado, 1 linha por evento (`request.started`, `request.finished`, `security.blocked`, `security.observed`, `request.throttled`, `request.unmatched.sampled_out`, `admin.action`); número de cartão sai mascarado (ver [Redaction](#redaction-lgpd)) |
| Borda | access log do nginx | tudo, inclusive health checks |

O `correlation_id` conecta as camadas: resposta (`X-Correlation-Id`), linha do banco e linhas de
arquivo da mesma requisição. Também entra no contexto compartilhado do Monolog
(`Log::shareContext`) — todo `Log::*` emitido durante a requisição o carrega.

**Ações de admin.** A linha do banco de uma ação no `/admin` é o update do Livewire, com payload
resumido (só o nome do componente) — ela prova que houve a requisição, não QUAL ação foi feita.
Ação de admin que muda dado de uma conta grava também a linha `admin.action` no arquivo
(`App\Filament\Support\AdminAuditTrail`): nome estável da ação (ex.:
`user.email_marked_verified`), `actor_uuid`, `target_type`, `target_uuid` e o mesmo
`correlation_id` da linha do banco. Nunca e-mail nem nome — o uuid basta para chegar à conta.

```bash
grep '"admin.action"' storage/logs/request-*.log
```

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

## Health check

`GET /api/health` → `{data: {status, version, correlation_id}}` (via `BaseResource`).
**Decisão**: excluído do request log em banco para não poluir a trilha (health checks são
barulhentos) — configurável em `REQUEST_LOG_EXCLUDED_PATHS`. Continua protegido por validação
de segurança, headers e rate limit (o `/up` também passa pelo teto da borda — uma sonda a cada
poucos segundos fica muito abaixo dele), e fica no access log do nginx.
