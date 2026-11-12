# Webhooks de entrada

Pacote **`twstec/kit-webhooks`** ([`packages/webhooks`](../packages/webhooks)),
namespace `Twstec\Kit\Webhooks\Inbound\`. Recebe webhooks de provedores
(gateway de pagamento, mensageria, assinatura de software) com a parte de
segurança pronta: o aplicativo declara a **fonte** (nome, verificador,
handler) e recebe no handler só o evento **verificado e não duplicado**, na
fila.

> Os webhooks de **saída** (o aplicativo avisando outros sistemas) estão em
> [webhooks.md](webhooks.md). Os dois lados moram no mesmo pacote, mas não
> dependem um do outro: dá para usar só a entrada.

## O caminho de uma requisição

Uma rota para todas as fontes: **`POST /webhooks/inbound/<fonte>`**
(prefixo em `WEBHOOKS_INBOUND_PATH`), fora do grupo `web` — **sem sessão,
cookie nem CSRF** — e com limite de requisições por fonte e IP. A pilha
global do kit continua valendo (proxies confiáveis, limite de borda, filtro
de ataques nos cabeçalhos e no caminho, trilha `request_logs`).

| Passo | Falhou? | Resposta |
| --- | --- | --- |
| 1. Fonte conhecida e bem configurada | desconhecida / mal configurada | `404` / `503` (motivo no log) |
| 2. Allowlist de IP da fonte (se houver) | IP fora | `403` |
| 3. Tamanho do corpo (`Content-Length` e o que chegou) | acima do limite | `413` |
| 4. **Verificação** sobre o **corpo cru**, antes de qualquer decodificação | assinatura/token inválido, ausente, carimbo fora da janela | `401` (sem o motivo) |
| 5. Leitura do evento (JSON objeto, por padrão) | corpo ilegível | `400` |
| 6. **Deduplicação** no banco por (fonte, id do evento) | já recebido | `200 {"status":"duplicate"}` |
| 7. Fila (job cifrado) | fila recusou | `202` — o registro fica esperando a fila e a rodada `webhooks:requeue-inbound` o põe lá (nunca `503`) |
| — | tudo certo | `202 {"status":"accepted"}` |

O motivo de cada recusa vai **só para o log interno**
(`webhooks.inbound.rejected`, com `source`, `reason`, `ip` e
`correlation_id`, passado pelo `Redactor`) — quem tenta forjar não aprende o
que faltou. Nenhum log do pacote leva corpo, cabeçalho ou segredo.

## Configurar uma fonte

As fontes ficam no `config/webhooks.php` **do aplicativo**, só com a chave
`inbound_sources` (as outras continuam vindo do pacote — ver
[personalização](personalizacao.md)). Segredos sempre por variável de
ambiente, e **dois por fonte** durante uma rotação (o atual e o anterior):

```php
<?php

declare(strict_types=1);

return [
    'inbound_sources' => [
        'pagamentos' => [
            'verifier' => [
                'driver' => 'header_token',
                'header' => 'x-provedor-token',
                'secrets' => [env('PAGAMENTOS_WEBHOOK_TOKEN'), env('PAGAMENTOS_WEBHOOK_TOKEN_PREVIOUS')],
            ],
            'handler' => App\Webhooks\PagamentosHandler::class,
            // De onde vêm o id (deduplicação), o tipo e a data do evento.
            'event' => ['id' => 'id', 'type' => 'event', 'occurred_at' => 'dateCreated', 'timezone' => 'America/Sao_Paulo'],
            // Opcionais (nulo = o padrão do pacote):
            'allowed_ips' => array_filter(explode(',', (string) env('PAGAMENTOS_WEBHOOK_IPS', ''))),
            'max_body_bytes' => null,
            'store_payload' => null,
            'tries' => null,
            'backoff' => null,
            'queue' => null,
            'connection' => null,
            'rate_limit_per_minute' => null,
        ],
    ],
];
```

A URL que vai no painel do provedor é `https://<seu-domínio>/webhooks/inbound/pagamentos`.
O nome da fonte é o da URL: minúsculas, dígitos, `-` e `_`, até 64.

Tudo na configuração é texto, número e lista: vale com o `config:cache`.

### O evento (`event`)

O parser padrão (`Inbound\JsonEventParser`) lê o corpo como JSON (objeto na
raiz, profundidade até 64) e tira dele:

- `id` — o id do evento no provedor, em notação de ponto (`id`,
  `data.object.id`) ou `header:<nome>`. **É o que deduplica**, junto com a
  fonte. Sem `id` configurado, vale o id que o próprio esquema assina (o
  `webhook-id` do Standard Webhooks) e, sem ele, `sha256:<hash do corpo>`.
- `type` — o tipo do evento (padrão: `type`).
- `occurred_at` — a data: segundos ou milissegundos Unix, ou texto de data
  no fuso `timezone` (padrão `UTC`). Ilegível ou ausente, vale o carimbo
  assinado (ou nada).

Corpo em outro formato (formulário, XML, envelope próprio): `parser` com uma
classe do aplicativo que implementa `Inbound\Contracts\EventParser` (com um
`fromConfig(array $opcoes)` estático, ela recebe as opções de `event`).

## Os verificadores

Cada fonte escolhe uma estratégia em `verifier.driver`. Os quatro do pacote
têm nome genérico — nenhum provedor no núcleo; o que muda de um provedor
para outro é configuração. Todos:

- verificam sobre o **corpo cru** (os bytes que chegaram — um JSON
  decodificado e recodificado não confere);
- comparam em **tempo constante** (`hash_equals`; `openssl_verify` na chave
  pública);
- aceitam **mais de um segredo/chave vigente** (rotação);
- **recusam** quando não há segredo configurado (variável de ambiente
  vazia não vira "aceita tudo");
- com carimbo de tempo no esquema, recusam fora da **janela**
  (`WEBHOOKS_INBOUND_TOLERANCE_SECONDS`, padrão 300 s, para os dois lados:
  velho e futuro; `tolerance` dentro do `verifier` muda por fonte).

Um verificador próprio entra pelo nome da classe em `driver`
(`Inbound\Contracts\Verifier`; com `fromConfig(array $opcoes)` estático,
recebe o bloco `verifier`).

### Token fixo em cabeçalho (`header_token`)

Para provedor que manda um segredo fixo num cabeçalho, sem assinar o corpo
(ex.: o Asaas, com `asaas-access-token`):

```php
'verifier' => [
    'driver' => 'header_token',
    'header' => 'asaas-access-token',
    'prefix' => '',            // ex.: 'Bearer ' para Authorization
    'secrets' => [env('ASAAS_WEBHOOK_TOKEN'), env('ASAAS_WEBHOOK_TOKEN_PREVIOUS')],
],
'event' => ['id' => 'id', 'type' => 'event', 'occurred_at' => 'dateCreated', 'timezone' => 'America/Sao_Paulo'],
```

É a forma **mais fraca**: o token não cobre o corpo nem tem carimbo de tempo
— prova só que quem mandou conhece o segredo. Por isso: HTTPS sempre, a
allowlist de IP quando o provedor publica as faixas, e o **handler
reconsulta a API do provedor** antes de mudar estado (o corpo diz "olhe
este recurso", não "acredite neste valor"). A deduplicação pelo id continua
valendo.

### HMAC do corpo (`hmac`, formato `plain`)

Assinatura HMAC do corpo num cabeçalho, com prefixo (ex.: a Meta, com
`X-Hub-Signature-256: sha256=<hex>`):

```php
'verifier' => [
    'driver' => 'hmac',
    'header' => 'X-Hub-Signature-256',
    'prefix' => 'sha256=',
    'algorithm' => 'sha256',   // sha256 | sha384 | sha512 | sha1 (só legado)
    'encoding' => 'hex',       // hex | base64
    'secrets' => [env('META_APP_SECRET'), env('META_APP_SECRET_PREVIOUS')],
],
```

### HMAC com carimbo de tempo (`hmac`, formato `keyed`)

Cabeçalho com pares `chave=valor` — o carimbo e uma ou mais assinaturas de
`{timestamp}.{corpo}` (ex.: a Stripe, com
`Stripe-Signature: t=…,v1=…,v1=…`):

```php
'verifier' => [
    'driver' => 'hmac',
    'header' => 'Stripe-Signature',
    'format' => 'keyed',
    'timestamp_key' => 't',
    'signature_key' => 'v1',
    // 'signed_content' => '{timestamp}.{body}',  // o padrão quando há carimbo
    'secrets' => [env('STRIPE_WEBHOOK_SECRET'), env('STRIPE_WEBHOOK_SECRET_PREVIOUS')],
],
'event' => ['id' => 'id', 'type' => 'type', 'occurred_at' => 'created'],
```

As outras opções do `hmac`, para os esquemas que variam:

| Opção | O que é |
| --- | --- |
| `format` | `plain` (o valor inteiro, depois do `prefix`), `keyed` (`t=…,v1=…`) ou `versioned` (`v1,<assinatura>` separadas por espaço) |
| `signed_content` | O modelo do conteúdo assinado, com `{body}` obrigatório e, se o esquema assina, `{timestamp}` e `{id}` (ex.: `{id}.{timestamp}.{body}`) |
| `timestamp_header` | Carimbo num cabeçalho próprio (em vez do `timestamp_key`) |
| `timestamp_unit` | `seconds` (padrão) ou `milliseconds` |
| `id_header` | O cabeçalho do id do evento, quando o esquema o assina |
| `secret_prefix` | Tirado do segredo antes de usar (ex.: `whsec_`) |
| `secret_encoding` | `raw` (padrão) ou `base64` |
| `tolerance` | A janela desta fonte, em segundos |

### Standard Webhooks (`standard_webhooks`)

A especificação [Standard Webhooks](https://www.standardwebhooks.com):
cabeçalhos `webhook-id`, `webhook-timestamp` e `webhook-signature`
(`v1,<base64>`, várias separadas por espaço na rotação), HMAC-SHA256 de
`{id}.{timestamp}.{corpo}`, segredo `whsec_<base64>`:

```php
'verifier' => [
    'driver' => 'standard_webhooks',
    'secrets' => [env('FORNECEDOR_WEBHOOK_SECRET'), env('FORNECEDOR_WEBHOOK_SECRET_PREVIOUS')],
],
'event' => ['type' => 'type'],   // o id é o webhook-id, assinado
```

Só a assinatura simétrica (`v1`); a assimétrica (`v1a`, Ed25519) não está
coberta — um verificador próprio resolve, se precisar.

### Chave pública (`public_key`, RSA ou EC)

O provedor assina com a chave privada dele e publica a pública (ex.: a
Woovi, com `x-webhook-signature` em base64, RSA-SHA256 do corpo). Aqui só
fica a pública — vazar a configuração não deixa ninguém forjar:

```php
'verifier' => [
    'driver' => 'public_key',
    'header' => 'x-webhook-signature',
    'algorithm' => 'sha256',   // sha256 | sha384 | sha512
    'encoding' => 'base64',    // base64 (aceita base64url) | hex
    // PEM no texto (a variável de ambiente pode trazer `\n` escrito) ou
    // `file://` com o caminho. Duas na rotação.
    'public_keys' => [env('WOOVI_WEBHOOK_PUBLIC_KEY'), env('WOOVI_WEBHOOK_PUBLIC_KEY_PREVIOUS')],
],
```

`signed_content`, `timestamp_header`, `timestamp_unit`, `id_header` e
`tolerance` funcionam como no `hmac` (padrão: só o corpo).

## O handler

```php
<?php

declare(strict_types=1);

namespace App\Webhooks;

use Twstec\Kit\Webhooks\Inbound\Contracts\InboundWebhookHandler;
use Twstec\Kit\Webhooks\Inbound\InboundEvent;

final class PagamentosHandler implements InboundWebhookHandler
{
    public function handle(InboundEvent $event): void
    {
        // $event->source      'pagamentos'
        // $event->id          o id do evento no provedor (o que deduplicou)
        // $event->type        'PAYMENT_RECEIVED'
        // $event->occurredAt  CarbonImmutable|null (UTC)
        // $event->payload     o corpo decodificado (array)
        // $event->get('payment.id')
        // $event->receipt     uuid do registro de recebimento
        // $event->attempt     1 na primeira tentativa
    }
}
```

O handler **roda na fila**, resolvido pelo container, e só recebe evento
verificado e não duplicado. Regras:

- **Seja idempotente.** Em falha (exceção), o job tenta de novo com o
  backoff da fonte; e o provedor pode reenviar um evento que falhou de vez.
- **Valide o payload.** O corpo não passou pelo filtro de ataques do kit (é
  texto de terceiros, ver abaixo) — trate como entrada não confiável.
- **Reconsulte o provedor** antes de mudar estado quando a verificação é
  fraca (token fixo) — e, de preferência, sempre que o evento só aponta
  para um recurso.
- **Não há conta corrente.** O registro do recebimento não é de uma conta:
  use `Accounts::actingAs(...)` ou `Accounts::asSystem('motivo', ...)` para
  tocar dado de conta.
- Trabalho pesado ou de várias etapas: o handler pode disparar os próprios
  jobs.

## Registrar pelo código

Para montar a fonte em código (verificador próprio, segredo vindo de outro
lugar), registre num provider — o worker da fila também sobe os providers,
então o registro vale nos dois lados. A fonte registrada vence a da
configuração com o mesmo nome:

```php
use Twstec\Kit\Webhooks\Inbound\InboundSource;
use Twstec\Kit\Webhooks\Inbound\InboundWebhooks;
use Twstec\Kit\Webhooks\Inbound\JsonEventParser;
use Twstec\Kit\Webhooks\Inbound\Verifiers\SharedSecretHeader;

public function boot(): void
{
    InboundWebhooks::register(new InboundSource(
        name: 'pagamentos',
        verifier: new SharedSecretHeader('x-provedor-token', [config('services.pagamentos.webhook_token')]),
        handler: PagamentosHandler::class,
        parser: new JsonEventParser(idPath: 'id', typePath: 'event', occurredAtPath: 'dateCreated'),
        allowedIps: ['203.0.113.0/24'],
    ));
}
```

Classes públicas: `InboundWebhooks` (`register`, `source`, `path`),
`InboundSource`, `InboundEvent`, `Contracts\InboundWebhookHandler`,
`Contracts\Verifier`, `Contracts\EventParser`, `VerificationResult`,
`Rejection`, `JsonEventParser`, `ParsedEvent`, `InboundRequest` e os
verificadores `Verifiers\SharedSecretHeader`, `HmacSignature`,
`StandardWebhooks` e `PublicKeySignature`.

## Deduplicação

A tabela `webhook_inbound_events` tem **`UNIQUE (source, event_id)`**. O
recebimento grava com `INSERT … ON CONFLICT DO NOTHING` (no SQLite,
`INSERT OR IGNORE`) e só depois lê o registro: sob corrida (o provedor
reenviando enquanto a primeira ainda está em curso), o banco escolhe um, e
os outros recebem `200 duplicate` — nunca "consulta e depois insere", que
deixaria dois passarem. A suíte prova com quatro processos ao mesmo tempo
no PostgreSQL.

- O mesmo id em **outra fonte** não é repetição.
- O mesmo id com **outro corpo** continua repetição, e deixa aviso
  (`webhooks.inbound.duplicate_mismatch`): o provedor reaproveitou o id ou
  alguém tenta passar outro conteúdo por um id conhecido.
- Evento que **falhou de vez** (`failed`): o reenvio do provedor com o mesmo
  id o põe na fila de novo — uma vez por reenvio (troca condicional).
- A janela da deduplicação é a retenção do registro
  (`WEBHOOKS_INBOUND_RETENTION_DAYS`, padrão 30 dias). Nos esquemas com
  carimbo, uma repetição velha já cai na janela de tempo antes.

Estados (`Enums\InboundStatus`): `received` (na fila, esperando nova
tentativa ou — com `queued_at` nulo — esperando a fila voltar), `processing` (com um worker, com prazo), `processed`, `failed`.

## Allowlist de IP

`allowed_ips` da fonte aceita IP exato e CIDR, IPv4 e IPv6 (lista ou texto
separado por vírgula); vazia = qualquer origem. Faixa inválida é **erro de
configuração** (503), nunca "libera tudo".

A comparação é com `$request->ip()`, que respeita os **proxies confiáveis**
do kit (`TRUSTED_PROXIES`, [segurança](seguranca.md)): sem proxy declarado,
um `X-Forwarded-For` forjado não muda nada; atrás de CDN/load balancer,
declare o proxy — e liste na fonte os IPs do **provedor**, nunca o do seu
proxy.

## Corpo, log e trilha

- O prefixo da rota entra **sozinho** nos caminhos de corpo assinado do
  filtro de ataques (`security.validation.signed_body_paths`,
  [segurança](seguranca.md#webhook-recebido-com-corpo-assinado-security_validation_signed_body_paths)),
  mesmo quando o aplicativo escreveu a chave `validation` dele: o corpo **não
  é inspecionado** (texto de terceiros que parece código não é recusado — o
  provedor reenviaria e acabaria descartando o evento) e **nunca vai para a
  trilha** `request_logs` (só tamanho e formato). Cabeçalhos inspecionados,
  caminho e o teto de inspeção continuam valendo.
- O **log** do pacote leva fonte, motivo, IP, correlation id e o uuid do
  registro — nunca corpo, cabeçalho de assinatura ou segredo; o contexto
  passa pelo `Redactor`. A falha do handler registra só a **classe** da
  exceção (`last_error`), não a mensagem.
- O **corpo não fica no banco** por padrão: viaja só no job, cifrado. Com
  `store_payload` (`WEBHOOKS_INBOUND_STORE_PAYLOAD`), fica **cifrado**
  (`APP_KEY`), fora da serialização, e o `webhooks:prune-inbound` (agendado,
  `35 4 * * *`) o apaga depois de `WEBHOOKS_INBOUND_PAYLOAD_RETENTION_DAYS`
  (padrão 7) — o registro de deduplicação fica até
  `WEBHOOKS_INBOUND_RETENTION_DAYS`.
- `payload_hash` guarda o SHA-256 do corpo cru (para o aviso de id com corpo
  diferente).

## Fila

O job `Inbound\ProcessInboundWebhook` vai **cifrado** para a fila
(`ShouldBeEncrypted`) e leva o id do registro e — quando a fonte não guarda
o corpo — o payload decodificado; nunca o corpo cru nem cabeçalho.

- Antes de chamar o handler, **reserva** o registro com uma troca condicional
  (`received`/`failed` → `processing`, com prazo): dois workers nunca
  processam o mesmo evento; reservado por outro, o job volta para a fila
  depois do prazo; já processado, não chama o handler.
- Falha do handler: o registro volta a `received` e o job tenta de novo, até
  `tries` (padrão 5), com `backoff` (padrão 10 s, 1 min, 5 min, 30 min).
  Esgotado: `failed` — e o job fica no `failed_jobs` do Laravel (cifrado),
  de onde `queue:retry` o repõe.
- Com a fila `sync` (desenvolvimento), o handler roda dentro da requisição:
  se ele falhar, a resposta é `202` e o registro vai a `failed`, como na fila
  assíncrona; o reenvio do provedor tenta de novo.

### Fila fora do ar

Aviso autêntico gravado é **sempre `202`**: o registro no banco já é a prova
durável. Responder `503` faria o provedor reenviar e, depois de falhas
seguidas, **interromper a fila do endereço** (no Asaas, 15 falhas; os
pendentes ficam guardados por pouco tempo) até alguém retomar à mão.

- Se a fila recusar o job (Redis fora, conexão inexistente), o registro fica
  `received` com `queued_at` nulo e `last_error = queue_unavailable`
  (`webhooks.inbound.queue_unavailable` no log). Quando a fonte não guarda o
  corpo, ele é guardado **cifrado** só para esse reenvio e sai assim que o
  evento é processado (a retenção não o apaga enquanto ele espera).
- O `webhooks:requeue-inbound` (agendado a cada minuto,
  `WEBHOOKS_INBOUND_REQUEUE_SCHEDULE`) põe na fila os que esperam há mais de
  `WEBHOOKS_INBOUND_REQUEUE_AFTER` segundos (padrão 60), até
  `WEBHOOKS_INBOUND_REQUEUE_BATCH` por rodada. Cada um é reservado por troca
  condicional: a rodada e um reenvio do provedor nunca põem o mesmo duas
  vezes. Fila ainda fora: a rodada para na primeira recusa e sai com falha.
- O reenvio do provedor de um aviso que espera a fila (passado o mesmo prazo)
  o põe na fila na hora.
- Fonte tirada da configuração: o registro vai a `failed`
  (`last_error = unknown_source`).
- A fila precisa ser durável (Redis com persistência ou a fila `database`):
  é nela que o evento espera.

## Configuração

| Variável | Padrão | O que faz |
| --- | --- | --- |
| `WEBHOOKS_INBOUND_REQUEUE_SCHEDULE` | `* * * * *` | Cron da rodada que põe na fila os avisos recebidos com a fila fora (vazio desliga, com aviso no log) |
| `WEBHOOKS_INBOUND_REQUEUE_AFTER` | `60` | Segundos que um aviso fora da fila espera antes de ser posto nela pela rodada ou por um reenvio |
| `WEBHOOKS_INBOUND_REQUEUE_BATCH` | `100` | Máximo de avisos por rodada |
| `WEBHOOKS_INBOUND_ENABLED` | `true` | `false` tira a rota |
| `WEBHOOKS_INBOUND_PATH` | `webhooks/inbound` | Prefixo da rota (só segmentos literais; com `*`, a rota não é registrada) |
| `WEBHOOKS_INBOUND_MAX_BODY_BYTES` | `262144` | Maior corpo aceito (o teto do filtro de ataques vale antes) |
| `WEBHOOKS_INBOUND_TOLERANCE_SECONDS` | `300` | Janela do carimbo de tempo |
| `WEBHOOKS_INBOUND_RATE_LIMIT` | `600` | Requisições por minuto, por fonte e IP |
| `WEBHOOKS_INBOUND_STORE_PAYLOAD` | `false` | Guardar o corpo, cifrado |
| `WEBHOOKS_INBOUND_TRIES` / `_BACKOFF` / `_TIMEOUT` | `5` / `10,60,300,1800` / `60` | O job |
| `WEBHOOKS_INBOUND_QUEUE` / `_QUEUE_CONNECTION` | — | Fila e conexão do job |
| `WEBHOOKS_INBOUND_RETENTION_DAYS` | `30` | Registro de deduplicação |
| `WEBHOOKS_INBOUND_PAYLOAD_RETENTION_DAYS` | `7` | Corpo guardado |
| `WEBHOOKS_INBOUND_PRUNE_SCHEDULE` | `35 4 * * *` | Agenda da retenção (vazio desliga, com aviso) |

## Testes

Na suíte do pacote (`tests/Inbound`): cada verificador (válido, inválido,
sem cabeçalho, carimbo velho e futuro, segredo/chave rotacionado,
configuração inválida), a rota inteira (404, sem sessão/cookie, 401 sem o
motivo, deduplicação e a corrida entre a verificação e o INSERT, allowlist
IPv4/IPv6 com e sem proxy confiável, 413, 429, fila fora do ar), que o
corpo e o token não aparecem em log nem na trilha, a fila cifrada, o job
idempotente, as novas tentativas e a retenção. No starter Livewire, contra
o PostgreSQL: o mesmo evento quatro vezes ao mesmo tempo, em processos
separados, processado uma vez
(`tests/Feature/Webhooks/InboundConcurrencyTest.php`).

Para testar o handler do aplicativo, poste o corpo cru com o cabeçalho que
o provedor mandaria (`$this->call('POST', '/webhooks/inbound/<fonte>', [], [], [], $server, $corpo)`)
e confira o efeito — o segredo de teste vem da configuração do teste, nunca
do `.env` real.
