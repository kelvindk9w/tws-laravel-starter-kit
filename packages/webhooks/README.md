# twstec/kit-webhooks

> **Parte do [TWS Laravel Starter Kit](https://github.com/kelvindk9w/tws-laravel-starter-kit).** O código, as issues e os
> pull requests ficam no monorepo
> [kelvindk9w/tws-laravel-starter-kit](https://github.com/kelvindk9w/tws-laravel-starter-kit) (pasta `packages/webhooks`); este
> repositório é o espelho só-leitura publicado a cada versão.
> Documentação: [docs/webhooks.md](https://github.com/kelvindk9w/tws-laravel-starter-kit/blob/desenvolvimento/docs/webhooks.md) (saída) e
> [docs/webhooks-entrada.md](https://github.com/kelvindk9w/tws-laravel-starter-kit/blob/desenvolvimento/docs/webhooks-entrada.md) (entrada) · Segurança:
> [SECURITY.md](SECURITY.md) · Licença: MIT ([LICENSE](LICENSE)).

Webhooks de saída do **TWS Laravel Starter Kit**, como pacote Laravel **sem
telas**: o aplicativo dispara um evento para uma conta e o pacote o entrega,
**assinado** (HMAC-SHA256 sobre `timestamp.corpo`), a cada endpoint da conta
que o assina — com outbox durável, fila cifrada, novas tentativas com backoff
exponencial, desativação só depois de falhas seguidas E de um prazo sem
sucesso (com aviso por e-mail; as entregas ficam pausadas e saem ao
reativar),
log de entregas redigido, reenvio auditado e **proteção contra SSRF** (DNS
revalidado em cada envio e conexão no IP conferido, sem redirect nem proxy).

E **webhooks de entrada**: o aplicativo declara uma **fonte** por provedor
(nome, verificador, handler) e o pacote recebe em `POST /webhooks/inbound/<fonte>`
— sem sessão nem CSRF, com limite por fonte e IP —, confere a allowlist de
IP e o tamanho do corpo, **verifica sobre o corpo cru** (token em
cabeçalho, HMAC configurável, Standard Webhooks ou chave pública RSA/EC, com
rotação de segredo e janela de tempo), **deduplica no banco** por (fonte, id
do evento) com INSERT de conflito ignorado, responde 202/200 na hora e
entrega o evento ao handler na **fila cifrada**, com novas tentativas. O
corpo e os cabeçalhos de assinatura não vão para log nem trilha.

Depende do [`twstec/kit-accounts`](https://github.com/kelvindk9w/tws-laravel-starter-kit/tree/desenvolvimento/packages/accounts) (o endpoint é da
conta), do [`twstec/kit-auth`](https://github.com/kelvindk9w/tws-laravel-starter-kit/tree/desenvolvimento/packages/auth) (ação sensível) e do
[`twstec/kit-foundation`](https://github.com/kelvindk9w/tws-laravel-starter-kit/tree/desenvolvimento/packages/foundation) (trilhas, redação, correlation id) — e
de nada acima deles; um teste de arquitetura na suíte do pacote garante isso.

- **Requisitos:** PHP 8.4+ com `curl`, Laravel 13, `twstec/kit-accounts`,
  `twstec/kit-auth` e `twstec/kit-foundation` 2.x; fila e agendador rodando.
- **Licença:** MIT.

## O que o pacote traz

| Peça | O que faz |
| --- | --- |
| `Webhooks::dispatch($conta, $evento, $dados, $projeto = null)` | A porta de entrada: grava o evento e as entregas (outbox) na transação em volta e põe as tentativas na fila depois do commit |
| `Models\WebhookEndpoint`, `WebhookEvent`, `WebhookDelivery`, `WebhookDeliveryAttempt` | Da conta (`BelongsToAccount`). O segredo e o corpo do evento são cifrados (`APP_KEY`) e ficam fora da serialização |
| `Actions\*` | `CreateEndpoint`, `UpdateEndpoint`, `DeleteEndpoint`, `SetEndpointStatus`, `RevealSecret`, `RotateSecret`, `SendTestEvent`, `ResendDelivery` e `CheckEndpointInput` — papel (dono/admin), destino, ação sensível (o token é consumido pela própria Action) e trilha de auditoria |
| `Security\DestinationPolicy`, `IpRanges`, `HostResolver` | A política de destino (SSRF): esquema, host canônico, todos os endereços resolvidos permitidos, metadados de nuvem nunca, redes privadas liberadas só fora de produção |
| `Signing\Signature`, `SecretGenerator` | O cabeçalho `X-Webhook-Signature: t=…,v1=…` (um `v1` por segredo vigente) e o segredo `whsk_…` de 256 bits |
| `Delivery\DeliverySender`, `DeliverWebhook`, `DeliveryQueue`, `Outbox`, `RetrySchedule`, `EndpointHealth`, `ResponseExcerpt` | O envio (reserva no banco, destino conferido de novo, `CURLOPT_RESOLVE` + `CURLOPT_PREREQFUNCTION`, sem redirect nem proxy), o backoff, a desativação com aviso e o trecho da resposta cortado e redigido |
| `Support\WebhookPanel`, `WebhookAccess`, `WebhookAudit`, `EventCatalog`, `WebhookLifecycle` | O que as telas mostram (nunca o segredo), quem pode o quê, a trilha, o catálogo e a exclusão com a conta e a pessoa |
| `Console\DispatchPendingDeliveries`, `PruneWebhookEvents` | `webhooks:dispatch-pending` (outbox de volta à fila, a cada minuto) e `webhooks:prune` (retenção; os eventos sem entrega — todo evento é gravado, com ou sem endpoint — com prazo próprio), agendados pelo pacote |
| `Mail\EndpointDisabledMail` | O aviso de endpoint desativado, ao dono e aos admins da conta |
| `Inbound\InboundWebhooks`, `InboundSource`, `InboundEvent`, `Contracts\InboundWebhookHandler` | Webhooks de ENTRADA: registrar uma fonte (pela configuração `webhooks.inbound_sources` ou pelo código), o evento que o handler recebe (id, tipo, ocorrido em, payload decodificado) e o contrato do handler |
| `Inbound\Verifiers\SharedSecretHeader`, `HmacSignature`, `StandardWebhooks`, `PublicKeySignature` (`Contracts\Verifier`) | Os verificadores plugáveis (drivers `header_token`, `hmac`, `standard_webhooks`, `public_key`): corpo cru, tempo constante, rotação, janela de tempo, recusa sem segredo configurado |
| `Inbound\InboundReceiver`, `ProcessInboundWebhook`, `Models\InboundWebhookEvent`, `Console\PruneInboundWebhooks` | O recebimento (404/403/413/401/400, deduplicação, 202/200, 503 com a fila fora), o job cifrado e idempotente, o registro de deduplicação (sem o corpo, ou com ele cifrado) e o `webhooks:prune-inbound` agendado |
| `examples/verify-signature.php` | O exemplo de verificação para o receptor (tolerância de tempo, tempo constante) — o mesmo código que a suíte usa |

## Instalação

```bash
composer require "twstec/kit-webhooks:~2.0"
php artisan migrate
```

Ou pelo instalador do kit: `php artisan tws:add webhooks` (num aplicativo que
já tem o `twstec/kit-accounts`) ou `php artisan tws:install --with=webhooks`.

Depois, para a **saída**:

1. Declare os eventos que o aplicativo emite (`WEBHOOKS_EVENTS` ou
   `config/webhooks.php` do aplicativo só com a chave `events` — ver
   [personalização](../../docs/personalizacao.md)).
2. Dispare com `Webhooks::dispatch(...)` dentro da transação da mudança.
3. Mantenha a fila e o agendador rodando.
4. Dê às contas uma tela sobre as Actions (os starters do kit trazem as
   deles, em Livewire e em React).

Para a **entrada**:

1. Declare as fontes no `config/webhooks.php` do aplicativo, só com a chave
   `inbound_sources` (segredos por variável de ambiente, dois na rotação):

   ```php
   'inbound_sources' => [
       'pagamentos' => [
           'verifier' => ['driver' => 'header_token', 'header' => 'x-provedor-token', 'secrets' => [env('PAGAMENTOS_WEBHOOK_TOKEN'), env('PAGAMENTOS_WEBHOOK_TOKEN_PREVIOUS')]],
           'handler' => App\Webhooks\PagamentosHandler::class,
           'event' => ['id' => 'id', 'type' => 'event', 'occurred_at' => 'dateCreated'],
       ],
   ],
   ```

2. Escreva o handler (`Inbound\Contracts\InboundWebhookHandler`): idempotente,
   valida o payload e, com verificação fraca (token fixo), reconsulta a API
   do provedor antes de mudar estado.
3. Cadastre no provedor `https://<domínio>/webhooks/inbound/pagamentos`.

Exemplos de HMAC, Standard Webhooks e chave pública em
[docs/webhooks-entrada.md](https://github.com/kelvindk9w/tws-laravel-starter-kit/blob/desenvolvimento/docs/webhooks-entrada.md).

## O que o receptor confere

```php
require 'verify-signature.php';

$ok = verify_webhook_signature($corpoBruto, $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '', $segredo);
```

E deduplica pelo `id` do evento (o mesmo em toda tentativa e reenvio). A
ordem de chegada não é garantida: compare o `created_at` do corpo ou consulte
o estado atual do recurso antes de agir.
Detalhes, o exemplo em Node, a tabela do SSRF e as decisões em
[docs/webhooks.md](https://github.com/kelvindk9w/tws-laravel-starter-kit/blob/desenvolvimento/docs/webhooks.md).

## Testes

```bash
./vendor/bin/pest
```

A suíte sobe uma aplicação Laravel limpa (Testbench) com o foundation, o auth,
o accounts e este pacote. Os envios de verdade vão para um receptor em
`127.0.0.1` (o servidor embutido do PHP) — nada sai da máquina — e o DNS é de
mentira (`HostResolver`), o que permite provar o rebinding e a conexão no IP
conferido.
