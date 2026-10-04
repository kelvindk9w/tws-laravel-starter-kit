# Webhooks de saída

Pacote **`twstec/kit-webhooks`** ([`packages/webhooks`](../packages/webhooks)),
namespace `Twstec\Kit\Webhooks\`. Avisa outros sistemas quando algo acontece
numa conta: o aplicativo dispara um evento e o pacote o entrega, assinado, a
cada endpoint da conta que o assina.

> **Módulo opcional** (exige o `twstec/kit-accounts`: o endpoint pertence a
> uma conta). Sem ele, a tela de webhooks não existe e o menu não a mostra —
> ver [instalação e módulos](instalacao.md).

O pacote não tem telas. Ele traz os models, a migration, as Actions (criar,
alterar, excluir, ativar/desativar, revelar e rotacionar o segredo, enviar
teste, reenviar), o envio, a política de destino, os comandos agendados e as
traduções. As telas são dos starters, as duas sobre as mesmas Actions:
`App\Livewire\Webhooks\Index` (Livewire) e
`App\Http\Controllers\Panel\WebhooksController` + `pages/webhooks/index.tsx`
(React), no endereço `/webhooks` do painel.

## Disparar um evento

Declare os eventos que o aplicativo emite (o catálogo) e dispare dentro da
transação da mudança de negócio:

```php
// config/webhooks.php (publicado) ou WEBHOOKS_EVENTS=order.created,order.shipped
'events' => ['order.created', 'order.shipped'],
```

```php
use Twstec\Kit\Webhooks\Webhooks;

DB::transaction(function () use ($conta, $pedido) {
    $pedido->save();

    Webhooks::dispatch($conta, 'order.created', [
        'order' => ['id' => $pedido->uuid, 'status' => 'created'],
    ]);
});

// Evento de um projeto: vai para os endpoints da conta sem projeto e para
// os daquele projeto.
Webhooks::dispatch($conta, 'order.shipped', [...], $projeto);
```

- Evento fora do catálogo lança `InvalidArgumentException` (erro de
  programação — nunca um envio silencioso). O evento de teste
  (`webhook.ping`) é do pacote e só sai pelo botão "Enviar teste".
- **Outbox:** o evento e uma entrega por endpoint ativo que o assina são
  gravados no banco, na transação em volta. Desfeita a transação, não sobra
  evento nem envio. Confirmada, as tentativas vão para a fila depois do
  commit. Se a fila estiver fora, a entrega fica pendente no banco e o
  `webhooks:dispatch-pending` (agendado a cada minuto) a põe na fila; a
  falha da fila nunca sobe para quem disparou.
- O corpo do evento é **cifrado** no banco (`APP_KEY`). Não ponha nele o que
  o receptor não deve ver.
- Sem endpoint que assine o evento, nada é gravado e a chamada devolve
  `null`.

## O que o receptor recebe

```http
POST /seu/endpoint HTTP/1.1
Content-Type: application/json
User-Agent: TWS-Kit-Webhooks/2
X-Webhook-Id: 0199a0c4-5d1e-7a3b-9f10-2b6c8d4e7a11
X-Webhook-Event: order.created
X-Webhook-Signature: t=1767225600,v1=5f2b…

{"id":"0199a0c4-5d1e-7a3b-9f10-2b6c8d4e7a11","type":"order.created","created_at":"2026-01-01T00:00:00Z","account":"…","project":null,"data":{"order":{"id":"…","total":1990}}}
```

- **`id`** (corpo e `X-Webhook-Id`): o identificador do EVENTO, o mesmo em
  toda tentativa e em todo reenvio manual. **Deduplique por ele**: a entrega
  é "pelo menos uma vez" (uma tentativa cuja resposta se perdeu sai de novo).
- **Resposta esperada:** qualquer `2xx`. Redirect (`3xx`) **não é seguido** e
  conta como falha. Responda rápido (o envio desiste em 10 s) e processe
  depois.
- Nenhum cabeçalho interno vai para o receptor (nem o correlation id do kit).

## Assinatura

`X-Webhook-Signature: t=<timestamp>,v1=<assinatura>[,v1=<assinatura>]`

- **Conteúdo assinado:** `"{timestamp}.{corpo}"` — o carimbo de tempo (os
  segundos Unix do `t=`), um ponto e o **corpo bruto**, exatamente os bytes
  recebidos (nunca o JSON decodificado e codificado de novo).
- **Algoritmo:** HMAC-SHA256 com o segredo do endpoint (o texto inteiro, com
  o prefixo `whsk_`), em hexadecimal minúsculo.
- **Dois `v1`:** depois de uma rotação, durante a convivência, cada envio
  traz uma assinatura por segredo vigente. O receptor aceita se **qualquer
  uma** conferir — e troca o segredo dele sem perder evento.
- **Contra replay:** recuse carimbo fora de uma tolerância (5 minutos), e
  deduplique pelo `id`.
- **Tempo constante:** compare com `hash_equals` / `timingSafeEqual`, nunca
  com `==`.

### Exemplo de verificação (PHP)

O mesmo código está em
[`packages/webhooks/examples/verify-signature.php`](../packages/webhooks/examples/verify-signature.php)
e é o que o receptor de teste da suíte do pacote usa (um teste confere que o
trecho abaixo é igual ao arquivo):

```php
function verify_webhook_signature(string $payload, string $header, string $secret, int $tolerance = 300, ?int $now = null): bool
{
    $timestamp = null;
    $signatures = [];

    foreach (explode(',', $header) as $part) {
        [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

        if ($key === 't' && ctype_digit($value)) {
            $timestamp = (int) $value;
        } elseif ($key === 'v1' && $value !== '') {
            $signatures[] = $value;
        }
    }

    if ($timestamp === null || $signatures === []) {
        return false;
    }

    if (abs(($now ?? time()) - $timestamp) > $tolerance) {
        return false;
    }

    $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            return true;
        }
    }

    return false;
}
```

Uso num controller Laravel:

```php
$ok = verify_webhook_signature($request->getContent(), (string) $request->header('X-Webhook-Signature'), config('services.loja.webhook_secret'));

abort_unless($ok, 400);

// Deduplicação: um registro por id de evento (chave única); repetido = já tratado.
if (EventoRecebido::query()->insertOrIgnore(['id' => $request->json('id')]) === 0) {
    return response()->noContent();
}
```

### Exemplo de verificação (Node)

O mesmo do receptor do E2E dos starters (`tests/e2e/support/webhook-receiver.*`):

```js
import { createHmac, timingSafeEqual } from 'node:crypto';

export function verifyWebhookSignature(rawBody, header, secret, toleranceSeconds = 300) {
    let timestamp = Number.NaN;
    const signatures = [];

    for (const part of String(header).split(',')) {
        const [key, value = ''] = part.trim().split(/=(.*)/s);

        if (key === 't') {
            timestamp = Number(value);
        } else if (key === 'v1' && value !== '') {
            signatures.push(value);
        }
    }

    if (!Number.isInteger(timestamp) || signatures.length === 0) {
        return false;
    }

    if (Math.abs(Math.floor(Date.now() / 1000) - timestamp) > toleranceSeconds) {
        return false;
    }

    const expected = Buffer.from(createHmac('sha256', secret).update(`${timestamp}.${rawBody}`).digest('hex'));

    return signatures.some((signature) => {
        const given = Buffer.from(signature);

        return given.length === expected.length && timingSafeEqual(given, expected);
    });
}
```

## O segredo

- **Gerado no servidor** (`random_bytes`, 256 bits, `whsk_` + base64url) na
  criação do endpoint. Ninguém escolhe o segredo.
- **Mostrado uma vez:** na resposta imediata da criação, da revelação ou da
  rotação — no React, como dado de uma resposta só do Inertia (fora das props
  e do histórico do navegador); no Livewire, até a pessoa clicar em "Já
  guardei". Nunca na listagem.
- **Guardado CIFRADO** (cast `encrypted`, `APP_KEY`), não em hash: o HMAC
  precisa do segredo em claro para assinar. O atributo em memória, num dump
  ou num log do model é o texto cifrado; fora da serialização (`$hidden`).
  **Troca da `APP_KEY`:** ponha a anterior em `APP_PREVIOUS_KEYS`, senão os
  segredos (e os corpos dos eventos pendentes) não decifram.
- **Revelar** e **rotacionar** são **ações sensíveis** (senha de transação →
  código por e-mail → token de uso único), na trilha de auditoria (quem,
  quando, de onde — nunca o valor). Criar e alterar o endpoint também são:
  um destino novo é um caminho novo para os dados da conta saírem.
- **Rotação com convivência:** o segredo anterior continua assinando junto
  com o novo pelos minutos escolhidos (padrão 24 h, máximo
  `WEBHOOKS_SECRET_MAX_OVERLAP_MINUTES`, 7 dias). Zero encerra o anterior na
  hora. Uma rotação durante outra descarta o mais antigo (nunca três).
- **Nunca** em log, trilha de requisições, trilha de auditoria, trilha de
  saída (`outbound_http_logs`) ou payload da fila — a suíte do pacote prova
  isso de ponta a ponta, com o worker de verdade.

## Proteção contra SSRF

Um webhook é uma requisição que o SERVIDOR faz para um endereço que o
USUÁRIO escolheu: sem cuidado, vira um jeito de alcançar a rede interna (o
banco, o Redis, o endpoint de metadados da nuvem com as credenciais da
máquina). A política de destino (`Security\DestinationPolicy`) roda no
**cadastro**, na **edição**, na **reativação** e de novo em **cada envio**.

| Regra | Por quê |
|---|---|
| Só `https` em produção (`http` só fora dela, com `WEBHOOKS_REQUIRE_HTTPS=false`) | dado da conta não trafega em claro |
| Recusa loopback (`127.0.0.0/8`, `::1`), redes privadas (`10/8`, `172.16/12`, `192.168/16`, `fc00::/7`), CGNAT, link-local (`169.254/16`, `fe80::/10`), "esta rede", multicast, reservados, documentação e tudo que o PHP não considera global (`FILTER_FLAG_GLOBAL_RANGE`) | a rede interna não é destino |
| Recusa metadados de nuvem (`169.254.169.254`, `169.254.170.2`, `100.100.100.200`, `168.63.129.16`, `192.0.0.192`, `fd00:ec2::254`) — nem a lista de desenvolvimento libera | é onde ficam as credenciais da máquina |
| Recusa as formas de transição do IPv6 que carregam um IPv4 (`::ffff:0:0/96`, `::/96`, NAT64, 6to4, Teredo) | o IPv4 de dentro poderia ser privado |
| Recusa host que não é nome DNS em ASCII nem IP canônico (`2130706433`, `0x7f.1`, `0177.0.0.1`, `127.1`, último rótulo numérico) | o resolvedor do sistema leria como IP e a conferência veria outro endereço |
| Recusa usuário/senha e `@` na URL, barra invertida, espaço, caractere de controle, não ASCII | cada leitor de URL entende essas formas de um jeito |
| **Todos** os endereços que o nome resolve precisam passar (um `A` público e outro privado = recusado) | o cliente HTTP poderia escolher o privado |
| **DNS resolvido de novo na hora do envio** | um nome que apontava para fora no cadastro e passou a apontar para dentro (DNS rebinding) é recusado no envio |
| **Conexão no IP conferido:** `CURLOPT_RESOLVE host:porta:ip` (o cURL não resolve o nome de novo) e, antes do primeiro byte, `CURLOPT_PREREQFUNCTION` confere que a conexão aberta é a do IP conferido (e que ele continua permitido) — senão aborta | fecha a janela entre conferir e conectar |
| URL **remontada** das partes conferidas (o cURL nunca recebe o texto digitado) | o que foi conferido é o que é chamado |
| Sem redirect (`allow_redirects` desligado), sem proxy (nem o do ambiente: `HTTP_PROXY` resolveria o nome por fora), só o esquema conferido (`protocols`), conexão nova sem reaproveitar, 3 s para conectar e 10 s no total | nada que o receptor responda leva a outro lugar ou segura o worker |

**Recusado e registrado:** no cadastro, a recusa é erro de validação no campo
da URL e linha `denied` na trilha de auditoria (`webhook_endpoint.created`,
com o motivo). No envio, a tentativa sai como `blocked` no log de entregas
(nada é enviado) e o log da aplicação recebe `webhooks.destination_blocked`
com o host, o endereço e o motivo — conta como falha seguida.

**Desenvolvimento:** para um receptor na rede do Docker (o E2E dos
starters), o `.env` de desenvolvimento libera `http` e a rede privada:
`WEBHOOKS_REQUIRE_HTTPS=false` e
`WEBHOOKS_ALLOWED_PRIVATE_NETWORKS=172.16.0.0/12`. **Em produção as duas são
ignoradas**, com aviso no log a cada boot. Metadados e link-local nunca são
liberados.

**Limites conhecidos:** o envio não usa proxy de saída (um ambiente que só
sai por proxy não entrega webhooks — o proxy resolveria o nome e a conexão
no IP conferido perderia o sentido); URL com `@` (até na query) é recusada.

## Entrega, novas tentativas e desativação

- Uma tentativa por job. O job carrega **só ids** (a entrega e, no reenvio,
  quem pediu) e vai **cifrado** para a fila (`ShouldBeEncrypted`).
- Antes de enviar, a tentativa **reserva** a entrega no banco (UPDATE
  condicional): dois jobs para a mesma entrega não enviam duas vezes. Uma
  entrega "em andamento" cujo processo morreu volta depois de
  `WEBHOOKS_LOCK_SECONDS`.
- **Novas tentativas** com backoff exponencial configurável
  (`WEBHOOKS_BACKOFF`, padrão **1 min, 5 min, 30 min, 2 h, 12 h** — seis
  tentativas ao todo); depois, a entrega fica `failed`.
- **Desativação:** depois de `WEBHOOKS_DISABLE_AFTER_FAILURES` (padrão 20)
  tentativas falhas **seguidas** no endpoint (qualquer sucesso zera), ele é
  desativado (`disabled_reason = failures`), as entregas em aberto dele saem
  como falhas, a desativação vai para a trilha de auditoria e para o log, e
  o **dono e os administradores da conta recebem um e-mail** (só o nome e o
  host do endpoint). Reativar pela tela confere o destino de novo e zera as
  falhas.
- **Reenvio manual** (dono/admin): o mesmo evento, uma tentativa agora, com
  os segredos vigentes e o destino conferido de novo; **auditado**
  (`webhook_delivery.resent`, com quem pediu) e a tentativa guarda quem
  pediu. Recusado com o endpoint desativado, com uma tentativa em andamento
  ou acima de 30 por minuto na conta. O "Enviar teste" tem limite de 10 por
  minuto.

## Log de entregas

Cada entrega (evento × endpoint) guarda o estado, as tentativas, a próxima
tentativa, o status e a duração da última; cada **tentativa** guarda o
resultado (`succeeded`, `failed`, `blocked`), o status HTTP, a duração, o IP
em que conectou, o erro sem URL e o **trecho da resposta**:

- **cortado** em `WEBHOOKS_RESPONSE_EXCERPT_BYTES` (1024) — o resto nem é lido
  da rede;
- **redigido:** o segredo do endpoint ecoado vira `[REDACTED]`; JSON passa
  pelo `Redactor` campo a campo (senha, token, segredo, assinatura…); texto
  passa pelas máscaras de CPF/CNPJ, e-mail e cartão e pela de `chave=valor`.

E o `correlation_id` da tentativa, o mesmo da linha da chamada em
`outbound_http_logs` (a trilha de saída do foundation, que grava host e rota
normalizada, status e duração — nunca cabeçalho, assinatura ou corpo).

## Quem pode o quê

| | Dono | Admin | Membro |
|---|---|---|---|
| Ver endpoints e o log de entregas | sim | sim | sim |
| Criar, alterar, excluir, ativar/desativar, enviar teste, reenviar | sim | sim | não (403, na trilha) |
| Revelar e rotacionar o segredo | sim (ação sensível) | sim (ação sensível) | não |

Endpoint ou entrega de outra conta responde o mesmo 404 do inexistente, com
a tentativa na trilha. O `/admin` não tem tela de webhooks (eles são da
conta, geridos por quem é dela).

## Exclusão

- **Conta excluída** (caminho único `AccountDeletion`): os endpoints (com o
  segredo), os eventos (com o corpo), as entregas e as tentativas dela saem
  na mesma transação (ouvinte de `AccountDeleting`; no PostgreSQL, quando a
  conta sai junto com a pessoa, as chaves estrangeiras `ON DELETE CASCADE`
  fazem o mesmo). Um job atrasado não acha a entrega e não envia nada.
  Webhooks **não impedem** exclusão.
- **Projeto excluído:** os endpoints e eventos dele saem (cascata).
- **Pessoa excluída:** nas contas que ficam, o que ela criou ou pediu fica
  sem autor (`created_by` nulo).
- **Ficam**, de propósito: a trilha de auditoria e a trilha de saída
  (registro do que aconteceu, com poda por idade própria). Nenhuma guarda
  segredo, assinatura ou corpo de evento.
- **Retenção:** `webhooks:prune` (agendado, 04:20 UTC) apaga eventos com mais
  de `WEBHOOKS_PRUNE_DAYS` (30) dias sem entrega em aberto.

## Configuração

`config/webhooks.php` (publique com `php artisan vendor:publish
--tag=webhooks-config`). Variáveis no `.env.example` dos starters:

| Variável | Padrão | |
|---|---|---|
| `WEBHOOKS_EVENTS` | vazio | o catálogo (separado por vírgula) |
| `WEBHOOKS_REQUIRE_HTTPS` | `true` | ignorado em produção (sempre HTTPS) |
| `WEBHOOKS_ALLOWED_PRIVATE_NETWORKS` | vazio | CIDRs liberados fora de produção |
| `WEBHOOKS_CONNECT_TIMEOUT` / `WEBHOOKS_TIMEOUT` | 3 / 10 s | |
| `WEBHOOKS_BACKOFF` | `60,300,1800,7200,43200` | espera antes de cada nova tentativa |
| `WEBHOOKS_DISABLE_AFTER_FAILURES` | 20 | falhas seguidas até desativar |
| `WEBHOOKS_RESPONSE_EXCERPT_BYTES` | 1024 | trecho da resposta no log |
| `WEBHOOKS_SECRET_MAX_OVERLAP_MINUTES` / `..._DEFAULT_...` | 10080 / 1440 | convivência na rotação |
| `WEBHOOKS_QUEUE` / `WEBHOOKS_QUEUE_CONNECTION` | padrão | fila dos envios |
| `WEBHOOKS_OUTBOX_SCHEDULE` | `* * * * *` | `webhooks:dispatch-pending` (vazio desliga, com aviso) |
| `WEBHOOKS_PRUNE_SCHEDULE` / `WEBHOOKS_PRUNE_DAYS` | `20 4 * * *` / 30 | `webhooks:prune` |

A fila e o agendador precisam estar rodando (no Docker do kit, os serviços
`queue` e `scheduler`).

## Testes

- **Pacote** (`packages/webhooks`): a política de destino endereço a
  endereço; envio de verdade (cURL do cliente `Http`) para um receptor em
  `127.0.0.1` — o servidor embutido do PHP — que confere a assinatura com o
  exemplo acima; a conexão no IP conferido (um nome `.invalid`, que o DNS do
  sistema não conhece, é entregue); rebinding; redirect para IP privado;
  tempo esgotado; resposta cortada e redigida; o segredo fora de log, trilhas
  e fila (com o worker de verdade e a fila no banco); outbox com a fila fora;
  reserva contra envio duplo; backoff com o relógio congelado; desativação
  com aviso; reenvio auditado; exclusão.
- **Starters:** as telas (Livewire e React) sobre as Actions — confirmação
  sensível de verdade, segredo uma vez, SSRF recusado antes de pedir o
  código, papel, conta alheia, log e reenvio.
- **E2E** (`tests/e2e/webhooks.spec.*`): o fluxo inteiro no navegador, com o
  receptor num servidor HTTP do próprio Playwright, alcançado pelo worker da
  fila pela rede do Docker. Precisa do `.env` de desenvolvimento com
  `WEBHOOKS_REQUIRE_HTTPS=false` e
  `WEBHOOKS_ALLOWED_PRIVATE_NETWORKS=172.16.0.0/12` (sem eles, o spec pula
  com o motivo); `E2E_WEBHOOK_RECEIVER_HOST` força o IP do host.
