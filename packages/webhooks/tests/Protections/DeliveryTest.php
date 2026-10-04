<?php

declare(strict_types=1);

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Foundation\Tracing\Models\OutboundHttpLog;
use Twstec\Kit\Webhooks\Delivery\DeliverySender;
use Twstec\Kit\Webhooks\Enums\AttemptOutcome;
use Twstec\Kit\Webhooks\Enums\DeliveryStatus;
use Twstec\Kit\Webhooks\Models\WebhookDelivery;
use Twstec\Kit\Webhooks\Models\WebhookDeliveryAttempt;
use Twstec\Kit\Webhooks\Models\WebhookEndpoint;
use Twstec\Kit\Webhooks\Security\ValidatedDestination;
use Twstec\Kit\Webhooks\Webhooks;

// =============================================================================
// ENTREGA DE VERDADE — para o receptor de teste em 127.0.0.1 (servidor
// embutido do PHP), com o cURL do cliente Http do Laravel. O receptor confere
// a assinatura com o exemplo da documentação.
// =============================================================================

/**
 * @return array{0: WebhookEndpoint, 1: string}
 */
function receiverEndpoint(mixed $test, mixed $owner, string $path = '/ok', string $host = 'receptor.invalid'): array
{
    $receiver = $test->receiver();
    $test->dns->records[$host] = ['127.0.0.1'];

    ['endpoint' => $endpoint, 'secret' => $secret] = $test->createEndpoint($owner, ['url' => $receiver->url($path, $host)]);
    $receiver->expectSecrets([$secret]);

    return [$endpoint, $secret];
}

function deliveryOf(WebhookEndpoint $endpoint): WebhookDelivery
{
    return Accounts::asSystem('teste', fn () => WebhookDelivery::query()->where('webhook_endpoint_id', $endpoint->id)->latest('id')->firstOrFail());
}

it('entrega o evento assinado: o receptor confere a assinatura com o exemplo da documentação', function (): void {
    $owner = $this->owner();
    [$endpoint] = receiverEndpoint($this, $owner);

    $event = Webhooks::dispatch($this->accountOf($owner), 'order.created', ['order' => ['id' => 'ped-123', 'total' => 1990]]);

    $requests = $this->receiver->requests();

    expect($requests)->toHaveCount(1)
        ->and($requests[0]['method'])->toBe('POST')
        ->and($requests[0]['verified_with'])->toBe([0])
        ->and($requests[0]['headers']['x-webhook-id'])->toBe($event->uuid)
        ->and($requests[0]['headers']['x-webhook-event'])->toBe('order.created')
        ->and($requests[0]['headers'])->not->toHaveKey('x-correlation-id')
        ->and(json_decode($requests[0]['body'], true))->toMatchArray([
            'id' => $event->uuid,
            'type' => 'order.created',
            'account' => $this->accountOf($owner)->uuid,
            'project' => null,
            'data' => ['order' => ['id' => 'ped-123', 'total' => 1990]],
        ]);

    $delivery = deliveryOf($endpoint);
    $attempt = Accounts::asSystem('teste', fn () => WebhookDeliveryAttempt::query()->where('webhook_delivery_id', $delivery->id)->sole());

    expect($delivery->status)->toBe(DeliveryStatus::Succeeded)
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->response_status)->toBe(200)
        ->and($attempt->outcome)->toBe(AttemptOutcome::Succeeded)
        ->and($attempt->response_excerpt)->toBe('{"received":true}')
        ->and($attempt->destination_ip)->toBe('127.0.0.1')
        ->and($attempt->duration_ms)->toBeInt();
});

it('conecta no IP CONFERIDO, sem resolver o nome de novo: um nome que o DNS do sistema não conhece (.invalid) é entregue', function (): void {
    $owner = $this->owner();
    [$endpoint] = receiverEndpoint($this, $owner, '/ok', 'so-o-pacote-resolve.invalid');

    $lookupsBefore = $this->dns->lookups['so-o-pacote-resolve.invalid'];

    Webhooks::dispatch($this->accountOf($owner), 'order.created', ['n' => 1]);

    // O pacote resolveu UMA vez no envio (revalidação); o cURL não resolveu
    // (o sistema não conhece `.invalid` — sem o IP fixado, seria
    // "Could not resolve host").
    expect($this->dns->lookups['so-o-pacote-resolve.invalid'])->toBe($lookupsBefore + 1)
        ->and($this->receiver->requests())->toHaveCount(1)
        ->and(deliveryOf($endpoint)->status)->toBe(DeliveryStatus::Succeeded);
});

it('REBINDING: o nome muda para a rede interna entre o cadastro e o envio → bloqueado, nada sai, registrado', function (): void {
    $logged = new ArrayObject;
    Event::listen(MessageLogged::class, fn (MessageLogged $message) => $logged->append($message));

    $owner = $this->owner();
    $this->receiver();
    $this->dns->records['rebind.example.com'] = ['93.184.215.14'];

    ['endpoint' => $endpoint] = $this->createEndpoint($owner, ['url' => 'http://rebind.example.com:'.$this->receiver->port.'/ok']);

    // Depois do cadastro, o DNS passa a responder o endereço de metadados.
    $this->dns->records['rebind.example.com'] = ['169.254.169.254'];

    Webhooks::dispatch($this->accountOf($owner), 'order.created', ['n' => 1]);

    $delivery = deliveryOf($endpoint);
    $attempt = Accounts::asSystem('teste', fn () => $delivery->attemptLog()->sole());

    expect($this->receiver->requests())->toBe([])
        ->and($attempt->outcome)->toBe(AttemptOutcome::Blocked)
        ->and($attempt->error)->toStartWith('metadata_address')
        ->and($delivery->status)->toBe(DeliveryStatus::Retrying);

    $blocked = collect($logged->getArrayCopy())->filter(fn (MessageLogged $m): bool => $m->message === 'webhooks.destination_blocked');

    expect($blocked)->toHaveCount(1)
        ->and($blocked->first()->level)->toBe('warning')
        ->and($blocked->first()->context['reason'])->toBe('metadata_address')
        ->and($blocked->first()->context['host'])->toBe('rebind.example.com')
        ->and($blocked->first()->context['address'])->toBe('169.254.169.254')
        ->and($blocked->first()->context['endpoint'])->toBe($endpoint->uuid);
});

it('rebinding para loopback também é bloqueado (a lista liberada não vale para quem não está nela)', function (): void {
    $owner = $this->owner();
    $this->receiver();
    config(['webhooks.destination.allowed_private_networks' => []]);
    $this->dns->records['rebind2.example.com'] = ['93.184.215.14'];

    ['endpoint' => $endpoint] = $this->createEndpoint($owner, ['url' => 'http://rebind2.example.com:'.$this->receiver->port.'/ok']);
    $this->dns->records['rebind2.example.com'] = ['127.0.0.1'];

    Webhooks::dispatch($this->accountOf($owner), 'order.created', ['n' => 1]);

    expect($this->receiver->requests())->toBe([])
        ->and(Accounts::asSystem('teste', fn () => deliveryOf($endpoint)->attemptLog()->sole())->outcome)->toBe(AttemptOutcome::Blocked);
});

it('REDIRECT para IP privado não é seguido: a tentativa falha com o 302, o receptor recebe uma requisição só', function (): void {
    $owner = $this->owner();
    [$endpoint] = receiverEndpoint($this, $owner, '/redirect');

    Webhooks::dispatch($this->accountOf($owner), 'order.created', ['n' => 1]);

    $delivery = deliveryOf($endpoint);
    $attempt = Accounts::asSystem('teste', fn () => $delivery->attemptLog()->sole());

    expect($this->receiver->requests())->toHaveCount(1)
        ->and($this->receiver->requests()[0]['path'])->toBe('/redirect')
        ->and($attempt->outcome)->toBe(AttemptOutcome::Failed)
        ->and($attempt->response_status)->toBe(302)
        ->and($attempt->error)->toBe(__('webhooks.errors.redirect_not_followed', ['status' => 302]))
        ->and($delivery->status)->toBe(DeliveryStatus::Retrying);
});

it('a conferência antes do primeiro byte aborta conexão em IP diferente do conferido ou não permitido', function (): void {
    $sender = app(DeliverySender::class);
    $check = (fn (ValidatedDestination $destination) => $this->connectionCheck($destination))->call($sender, new ValidatedDestination('https://hooks.example.com/', 'https', 'hooks.example.com', 443, '93.184.215.14', ['93.184.215.14']));
    $handle = curl_init();

    expect($check($handle, '93.184.215.14', '10.0.0.2', 443, 50000))->toBe(CURL_PREREQFUNC_OK)
        ->and($check($handle, '10.0.0.9', '10.0.0.2', 443, 50000))->toBe(CURL_PREREQFUNC_ABORT)
        ->and($check($handle, '93.184.215.14', '10.0.0.2', 8443, 50000))->toBe(CURL_PREREQFUNC_ABORT);

    $loopback = (fn (ValidatedDestination $destination) => $this->connectionCheck($destination))->call($sender, new ValidatedDestination('https://x.example.com/', 'https', 'x.example.com', 443, '127.0.0.1', ['127.0.0.1']));

    expect($loopback($handle, '127.0.0.1', '127.0.0.1', 443, 50000))->toBe(CURL_PREREQFUNC_ABORT);
});

it('tempo curto: receptor lento vira tentativa falha, sem segurar o worker', function (): void {
    config(['webhooks.destination.timeout' => 1]);

    $owner = $this->owner();
    [$endpoint] = receiverEndpoint($this, $owner, '/slow');

    $started = microtime(true);
    Webhooks::dispatch($this->accountOf($owner), 'order.created', ['n' => 1]);

    $attempt = Accounts::asSystem('teste', fn () => deliveryOf($endpoint)->attemptLog()->sole());

    expect(microtime(true) - $started)->toBeLessThan(2.9)
        ->and($attempt->outcome)->toBe(AttemptOutcome::Failed)
        ->and($attempt->error)->toContain('ConnectionException');
});

it('a resposta vai para o log CORTADA e REDIGIDA: o segredo ecoado, a senha e o e-mail não ficam', function (): void {
    $owner = $this->owner();
    [$endpoint, $secret] = receiverEndpoint($this, $owner, '/echo');

    Webhooks::dispatch($this->accountOf($owner), 'order.created', ['n' => 1]);

    $excerpt = (string) Accounts::asSystem('teste', fn () => deliveryOf($endpoint)->attemptLog()->sole())->response_excerpt;

    expect(strlen($excerpt))->toBeLessThanOrEqual(1024 + 64)
        ->and($excerpt)->not->toContain($secret)
        ->and($excerpt)->not->toContain('p4ss-do-receptor')
        ->and($excerpt)->not->toContain('maria@example.com')
        ->and($excerpt)->toContain('[REDACTED]');
});

it('resposta em texto também é redigida (segredo e chave=valor)', function (): void {
    $owner = $this->owner();
    [$endpoint, $secret] = receiverEndpoint($this, $owner, '/text');

    Webhooks::dispatch($this->accountOf($owner), 'order.created', ['n' => 1]);

    $excerpt = (string) Accounts::asSystem('teste', fn () => deliveryOf($endpoint)->attemptLog()->sole())->response_excerpt;

    expect($excerpt)->not->toContain($secret)
        ->and($excerpt)->not->toContain('abc123def')
        ->and($excerpt)->toContain('token=[REDACTED]');
});

it('a chamada sai na trilha de saída sem segredo, assinatura, corpo nem URL inteira', function (): void {
    $owner = $this->owner();
    [$endpoint, $secret] = receiverEndpoint($this, $owner);

    Webhooks::dispatch($this->accountOf($owner), 'order.created', ['cliente' => 'Fulano Segredo']);

    $line = OutboundHttpLog::query()->sole();
    $row = json_encode(DB::table('outbound_http_logs')->first());

    expect($line->host)->toBe('receptor.invalid:'.$this->receiver->port)
        ->and($line->http_status)->toBe(200)
        ->and($line->tenant_uuid)->toBe($this->accountOf($owner)->uuid)
        ->and($line->request_body)->toBeNull()
        ->and($row)->not->toContain($secret)
        ->and($row)->not->toContain('Fulano Segredo')
        ->and($row)->not->toContain('v1=');

    $attempt = Accounts::asSystem('teste', fn () => deliveryOf($endpoint)->attemptLog()->sole());

    expect($attempt->correlation_id)->toBe($line->correlation_id);
});

it('com Http::fake, o envio segue as mesmas regras (sem redirect, assinatura no cabeçalho)', function (): void {
    Http::fake(['*' => Http::response('', 204)]);

    $owner = $this->owner();
    ['endpoint' => $endpoint, 'secret' => $secret] = $this->createEndpoint($owner);

    Webhooks::dispatch($this->accountOf($owner), 'order.created', ['n' => 1]);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://hooks.example.com/webhooks'
        && str_starts_with($request->header('X-Webhook-Signature')[0], 't=')
        && verify_webhook_signature($request->body(), $request->header('X-Webhook-Signature')[0], $secret));

    expect(deliveryOf($endpoint)->status)->toBe(DeliveryStatus::Succeeded);
});
