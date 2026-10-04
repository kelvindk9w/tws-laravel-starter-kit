<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Twstec\Kit\Accounts\Account\Models\AccountMembership;
use Twstec\Kit\Accounts\Accounts;
use Twstec\Kit\Foundation\Audit\Enums\AuditOutcome;
use Twstec\Kit\Foundation\Audit\Models\AuditEvent;
use Twstec\Kit\Webhooks\Actions\RevealSecret;
use Twstec\Kit\Webhooks\Actions\RotateSecret;
use Twstec\Kit\Webhooks\Delivery\DeliverWebhook;
use Twstec\Kit\Webhooks\Models\WebhookEndpoint;
use Twstec\Kit\Webhooks\Signing\SecretGenerator;
use Twstec\Kit\Webhooks\Support\WebhookPanel;
use Twstec\Kit\Webhooks\Webhooks;

// =============================================================================
// O SEGREDO: gerado no servidor, mostrado uma vez, guardado CIFRADO — e nunca
// em log, request log, trilha de auditoria, trilha de saída ou fila.
// =============================================================================

it('gera um segredo forte no servidor, com o prefixo, e o guarda CIFRADO', function (): void {
    $owner = $this->owner();
    ['endpoint' => $endpoint, 'secret' => $secret] = $this->createEndpoint($owner);

    $raw = DB::table('webhook_endpoints')->where('id', $endpoint->id)->value('secret');

    expect($secret)->toStartWith(SecretGenerator::PREFIX)
        ->and(strlen($secret))->toBe(strlen(SecretGenerator::PREFIX) + 43)
        ->and($raw)->not->toContain($secret)
        ->and(Crypt::decryptString($raw))->toBe($secret)
        ->and(SecretGenerator::generate())->not->toBe(SecretGenerator::generate());
});

it('o segredo não sai na serialização do model nem no que as telas recebem', function (): void {
    $owner = $this->owner();
    ['endpoint' => $endpoint, 'secret' => $secret] = $this->createEndpoint($owner);

    $panel = $this->inAccountOf($owner, fn (): array => WebhookPanel::endpoints());

    expect(json_encode($endpoint->toArray()))->not->toContain($secret)
        ->and($endpoint->toArray())->not->toHaveKeys(['secret', 'previous_secret'])
        ->and(json_encode($panel))->not->toContain($secret)
        ->and(json_encode($panel))->not->toContain('eyJpdiI6');
});

it('SEGREDO NUNCA EM LOG, REQUEST LOG, TRILHA DE AUDITORIA, TRILHA DE SAÍDA OU PAYLOAD DA FILA', function (): void {
    $logs = sys_get_temp_dir().'/kit-webhooks-logs-'.uniqid();
    File::ensureDirectoryExists($logs);
    config([
        'logging.default' => 'single',
        'logging.channels.single' => ['driver' => 'single', 'path' => $logs.'/laravel.log', 'level' => 'debug'],
        'logging.channels.request_log' => ['driver' => 'single', 'path' => $logs.'/request.log', 'level' => 'debug'],
        'queue.default' => 'database',
    ]);
    Log::forgetChannel('single');
    Log::forgetChannel('request_log');

    $owner = $this->owner();
    $receiver = $this->receiver();
    $this->dns->records['segredo.invalid'] = ['127.0.0.1'];

    ['endpoint' => $endpoint, 'secret' => $secret] = $this->createEndpoint($owner, ['url' => $receiver->url('/echo', 'segredo.invalid')]);
    $receiver->expectSecrets([$secret]);

    $rotated = $this->inAccountOf($owner, fn (): string => app(RotateSecret::class)->handle($owner, $endpoint->uuid, 60, $this->sensitiveToken($owner)));
    $receiver->expectSecrets([$rotated, $secret]);

    Webhooks::dispatch($this->accountOf($owner), 'order.created', ['pedido' => 'conteudo-do-evento-123']);

    // O job está na tabela `jobs`: o payload da fila é cifrado e só tem ids.
    $queued = (string) DB::table('jobs')->value('payload');

    expect($queued)->not->toBe('')
        ->and($queued)->not->toContain($secret)
        ->and($queued)->not->toContain($rotated)
        ->and($queued)->not->toContain('conteudo-do-evento-123')
        ->and($queued)->not->toContain('segredo.invalid');

    $command = json_decode($queued, true)['data']['command'];
    $job = unserialize(Crypt::decrypt($command));

    expect($job)->toBeInstanceOf(DeliverWebhook::class)
        ->and(array_keys(get_object_vars($job)))->not->toContain('secret');

    // Roda o worker de verdade.
    $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true])->assertSuccessful();

    expect($receiver->requests())->toHaveCount(1)
        ->and($receiver->requests()[0]['verified_with'])->toBe([0, 1]);

    $everything = implode("\n", [
        (string) @file_get_contents($logs.'/laravel.log'),
        (string) @file_get_contents($logs.'/request.log'),
        json_encode(AuditEvent::query()->get()->toArray()),
        json_encode(DB::table('outbound_http_logs')->get()),
        json_encode(DB::table('webhook_delivery_attempts')->get()),
        json_encode(DB::table('webhook_deliveries')->get()),
        json_encode(DB::table('webhook_events')->get()),
        json_encode(DB::table('failed_jobs')->get()),
    ]);

    expect($everything)->not->toContain($secret)
        ->and($everything)->not->toContain($rotated)
        ->and($everything)->not->toContain('conteudo-do-evento-123')
        ->and(AuditEvent::query()->where('action', 'webhook_endpoint.created')->exists())->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'webhook_endpoint.secret_rotated')->exists())->toBeTrue();

    File::deleteDirectory($logs);
});

it('o corpo do evento fica CIFRADO no banco (outbox)', function (): void {
    Queue::fake();

    $owner = $this->owner();
    $this->createEndpoint($owner);

    $event = Webhooks::dispatch($this->accountOf($owner), 'order.created', ['pedido' => 'texto-claro-do-evento']);
    $raw = (string) DB::table('webhook_events')->where('id', $event->id)->value('payload');

    expect($raw)->not->toContain('texto-claro-do-evento')
        ->and(json_decode(Crypt::decryptString($raw), true))->toBe(['pedido' => 'texto-claro-do-evento']);

    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->manual === false && is_int($job->deliveryId));
});

it('REVELAR o segredo é ação sensível e fica na trilha (sem o valor)', function (): void {
    $owner = $this->owner();
    ['endpoint' => $endpoint, 'secret' => $secret] = $this->createEndpoint($owner);

    expect(fn () => $this->inAccountOf($owner, fn () => app(RevealSecret::class)->handle($owner, $endpoint->uuid, 'token-invalido')))
        ->toThrow(AuthorizationException::class);

    $denied = AuditEvent::query()->where('action', 'webhook_endpoint.secret_revealed')->where('outcome', AuditOutcome::Denied->value)->sole();

    $revealed = $this->inAccountOf($owner, fn (): string => app(RevealSecret::class)->handle($owner, $endpoint->uuid, $this->sensitiveToken($owner)));
    $row = AuditEvent::query()->where('action', 'webhook_endpoint.secret_revealed')->where('outcome', AuditOutcome::Success->value)->sole();

    expect($revealed)->toBe($secret)
        ->and($denied->subject_uuid)->toBe($endpoint->uuid)
        ->and($row->actor_uuid)->toBe($owner->uuid)
        ->and($row->subject_uuid)->toBe($endpoint->uuid)
        ->and(json_encode($row->toArray()))->not->toContain($secret);
});

it('ROTACIONAR com convivência: os dois segredos assinam até o prazo; depois, só o novo', function (): void {
    $this->freezeSecond();

    $owner = $this->owner();
    $receiver = $this->receiver();
    $this->dns->records['rotacao.invalid'] = ['127.0.0.1'];

    ['endpoint' => $endpoint, 'secret' => $old] = $this->createEndpoint($owner, ['url' => $receiver->url('/ok', 'rotacao.invalid')]);

    $new = $this->inAccountOf($owner, fn (): string => app(RotateSecret::class)->handle($owner, $endpoint->uuid, 30, $this->sensitiveToken($owner)));
    $receiver->expectSecrets([$old, $new]);

    Webhooks::dispatch($this->accountOf($owner), 'order.created', ['n' => 1]);

    // A convivência acaba (o prazo passa). O relógio do receptor é o de
    // verdade: em vez de viajar no tempo, o prazo vai para o passado.
    $endpoint->forceFill(['previous_secret_expires_at' => now()->subSecond()])->save();
    Webhooks::dispatch($this->accountOf($owner), 'order.created', ['n' => 2]);

    $requests = $receiver->requests();

    expect($new)->not->toBe($old)
        ->and($endpoint->refresh()->secret)->toBe($new)
        ->and(substr_count($requests[0]['headers']['x-webhook-signature'], 'v1='))->toBe(2)
        ->and($requests[0]['verified_with'])->toBe([0, 1])
        ->and(substr_count($requests[1]['headers']['x-webhook-signature'], 'v1='))->toBe(1)
        ->and($requests[1]['verified_with'])->toBe([1]);
});

it('rotação sem convivência mata o anterior na hora; convivência acima do máximo é recusada', function (): void {
    $owner = $this->owner();
    ['endpoint' => $endpoint, 'secret' => $old] = $this->createEndpoint($owner);

    $new = $this->inAccountOf($owner, fn (): string => app(RotateSecret::class)->handle($owner, $endpoint->uuid, 0, $this->sensitiveToken($owner)));

    expect($endpoint->refresh()->signingSecrets())->toBe([$new])
        ->and(fn () => $this->inAccountOf($owner, fn () => app(RotateSecret::class)->handle($owner, $endpoint->uuid, 999999, $this->sensitiveToken($owner))))
        ->toThrow(ValidationException::class);
});

it('membro (sem papel de gestão) não revela nem rotaciona — recusa na trilha', function (): void {
    $owner = $this->owner();
    ['endpoint' => $endpoint] = $this->createEndpoint($owner);
    $member = $this->owner();
    AccountMembership::query()->create(['account_id' => $this->accountOf($owner)->id, 'user_id' => $member->id, 'role' => 'member']);

    expect(fn () => $this->inAccountOf($member, fn () => app(RevealSecret::class)->handle($member, $endpoint->uuid, $this->sensitiveToken($member)), $this->accountOf($owner)))
        ->toThrow(AuthorizationException::class);

    expect(AuditEvent::query()->where('action', 'webhook_endpoint.secret_revealed')->where('outcome', 'denied')->where('actor_uuid', $member->uuid)->exists())->toBeTrue()
        ->and(Accounts::asSystem('teste', fn () => WebhookEndpoint::query()->count()))->toBe(1);
});
