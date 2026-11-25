<?php

declare(strict_types=1);

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Twstec\Kit\Foundation\Logging\Models\RequestLog;
use Twstec\Kit\Webhooks\Enums\InboundMode;
use Twstec\Kit\Webhooks\Enums\InboundStatus;
use Twstec\Kit\Webhooks\Inbound\AuthorizationResponse;
use Twstec\Kit\Webhooks\Inbound\Contracts\InboundAuthorizationHandler;
use Twstec\Kit\Webhooks\Inbound\InboundEvent;
use Twstec\Kit\Webhooks\Inbound\InboundSource;
use Twstec\Kit\Webhooks\Inbound\InboundWebhooks;
use Twstec\Kit\Webhooks\Inbound\InvalidSourceConfiguration;
use Twstec\Kit\Webhooks\Inbound\JsonEventParser;
use Twstec\Kit\Webhooks\Inbound\ProcessInboundWebhook;
use Twstec\Kit\Webhooks\Inbound\Verifiers\SharedSecretHeader;
use Twstec\Kit\Webhooks\Models\InboundWebhookEvent;
use Twstec\Kit\Webhooks\Tests\Fixtures\RecordingAuthorizationHandler;
use Twstec\Kit\Webhooks\Tests\Fixtures\RecordingInboundHandler;

// =============================================================================
// O MODO AUTORIZAÇÃO (`'mode' => 'authorization'`): o provedor espera a
// resposta desta requisição. Antes do handler, as MESMAS recusas da fonte
// comum (fonte, IP, tamanho, limite por minuto, verificação com rotação,
// leitura, configuração inválida); depois, o handler roda na requisição, sem
// fila, e o código e o corpo dele vão sem mudança. Exceção vira 500 sem
// detalhe; o registro do recebimento fica sem o corpo; o tempo do handler vai
// para o log, com aviso acima do orçamento. A fonte comum não muda.
// =============================================================================

/**
 * A fonte `autorizacao` (token em cabeçalho, com rotação, e o handler de
 * autorização de teste) junto com as fontes comuns de sempre.
 *
 * @param  array<string, mixed>  $overrides
 */
function authorizationSource(array $overrides = []): void
{
    inboundSources(['autorizacao' => [
        'mode' => 'authorization',
        'verifier' => ['driver' => 'header_token', 'header' => 'x-provedor-token', 'secrets' => [INBOUND_TOKEN, INBOUND_TOKEN_PREVIOUS]],
        'handler' => RecordingAuthorizationHandler::class,
        'event' => ['id' => 'id', 'type' => 'event', 'occurred_at' => 'dateCreated', 'timezone' => 'America/Sao_Paulo'],
        ...$overrides,
    ]]);
}

/**
 * O log em entradas (nível, mensagem, contexto) — para conferir os números.
 *
 * @return ArrayObject<int, array{level: string, message: string, context: array<string, mixed>}>
 */
function captureLogEntries(): ArrayObject
{
    /** @var ArrayObject<int, array{level: string, message: string, context: array<string, mixed>}> $entries */
    $entries = new ArrayObject;

    Event::listen(MessageLogged::class, function (MessageLogged $event) use ($entries): void {
        $entries[] = ['level' => $event->level, 'message' => $event->message, 'context' => $event->context];
    });

    return $entries;
}

/**
 * @param  ArrayObject<int, array{level: string, message: string, context: array<string, mixed>}>  $entries
 * @return list<array{level: string, message: string, context: array<string, mixed>}>
 */
function logEntriesNamed(ArrayObject $entries, string $message): array
{
    return array_values(array_filter((array) $entries, fn (array $entry): bool => $entry['message'] === $message));
}

beforeEach(function (): void {
    RecordingAuthorizationHandler::reset();
    RecordingInboundHandler::reset();
    authorizationSource();
    Queue::fake();
});

describe('a resposta é a do handler', function (): void {
    it('o código e o corpo JSON do handler vão ao provedor sem mudança', function (int $status, array $body): void {
        RecordingAuthorizationHandler::$respond = fn (): AuthorizationResponse => new AuthorizationResponse($status, $body);

        $response = postInbound('autorizacao', inboundBody('evt_resp'), ['x-provedor-token' => INBOUND_TOKEN]);

        expect($response->getStatusCode())->toBe($status)
            ->and($response->getContent())->toBe(json_encode($body))
            ->and($response->headers->get('Content-Type'))->toBe('application/json')
            // Fora do grupo `web`: sem sessão nem cookie.
            ->and($response->headers->getCookies())->toBe([])
            ->and(RecordingAuthorizationHandler::$events)->toHaveCount(1);
    })->with([
        'aprovado (200)' => [200, ['status' => 'APPROVED']],
        'recusado (403), com motivo e estrutura aninhada' => [403, ['status' => 'REFUSED', 'reason' => 'limite', 'detalhe' => ['codigo' => 7, 'lista' => [1, 'dois', null, true, 1.5]]]],
        'erro do negócio (422), com acento e barra' => [422, ['mensagem' => 'Operação não permitida — saldo/limite']],
        'o handler pede para tentar depois (503)' => [503, ['retry' => true]],
        'no limite de cima dos 2xx (299)' => [299, ['limite' => 'baixo']],
        'no limite de cima (599)' => [599, ['limite' => 'alto']],
        'no limite de baixo dos 4xx (400)' => [400, ['limite' => '4xx']],
        'corpo vazio' => [200, []],
    ]);

    it('o handler recebe o evento verificado, na própria requisição', function (): void {
        postInbound('autorizacao', inboundBody('evt_evento'), ['x-provedor-token' => INBOUND_TOKEN])
            ->assertOk()
            ->assertExactJson(['status' => 'APPROVED', 'id' => 'evt_evento']);

        $event = RecordingAuthorizationHandler::$events[0];
        $record = InboundWebhookEvent::query()->sole();

        expect($event)->toBeInstanceOf(InboundEvent::class)
            ->and($event->source)->toBe('autorizacao')
            ->and($event->id)->toBe('evt_evento')
            ->and($event->type)->toBe('PAYMENT_RECEIVED')
            ->and($event->occurredAt?->toIso8601String())->toBe('2026-11-07T13:15:00+00:00')
            ->and($event->get('payment.id'))->toBe('pay_123')
            ->and($event->receipt)->toBe($record->uuid)
            ->and($event->attempt)->toBe(1)
            ->and(print_r($event, true))->not->toContain('MARCADOR-DO-CORPO-7f3a');
    });
});

it('nada vai para a fila — nem com a fila fora do ar', function (): void {
    postInbound('autorizacao', inboundBody('evt_sem_fila'), ['x-provedor-token' => INBOUND_TOKEN])->assertOk();

    Queue::assertNothingPushed();

    // A fila de verdade, numa conexão que não existe: qualquer envio lançaria.
    Queue::swap(Queue::getFacadeRoot()->queue);
    config(['queue.default' => 'fila-que-nao-existe']);

    postInbound('autorizacao', inboundBody('evt_sem_fila_2'), ['x-provedor-token' => INBOUND_TOKEN])
        ->assertOk()
        ->assertExactJson(['status' => 'APPROVED', 'id' => 'evt_sem_fila_2']);

    expect(RecordingAuthorizationHandler::$events)->toHaveCount(2)
        ->and(InboundWebhookEvent::query()->whereNotNull('queued_at')->count())->toBe(0);
});

it('o registro do recebimento fica SEM o corpo — mesmo com store_payload ligado no pacote', function (): void {
    config(['webhooks.inbound.store_payload' => true]);
    $marcador = 'MARCADOR-AUTORIZACAO-REGISTRO-3e1f';

    postInbound('autorizacao', inboundBody('evt_registro', $marcador), ['x-provedor-token' => INBOUND_TOKEN])->assertOk();

    $record = InboundWebhookEvent::query()->sole();
    $raw = (array) DB::table('webhook_inbound_events')->sole();

    expect($record->source)->toBe('autorizacao')
        ->and($record->event_id)->toBe('evt_registro')
        ->and($record->event_type)->toBe('PAYMENT_RECEIVED')
        ->and($record->occurred_at?->utc()->format('Y-m-d H:i:s'))->toBe('2026-11-07 13:15:00')
        ->and($record->status)->toBe(InboundStatus::Processed)
        ->and($record->attempts)->toBe(1)
        ->and($record->processed_at)->not->toBeNull()
        ->and($record->queued_at)->toBeNull()
        ->and($record->last_error)->toBeNull()
        ->and($record->payload)->toBeNull()
        ->and($raw['payload'])->toBeNull()
        ->and($record->payload_hash)->toBe(hash('sha256', inboundBody('evt_registro', $marcador)))
        ->and(json_encode($raw))->not->toContain($marcador);
});

it('a mesma chamada chega de novo ao handler: o kit não deduplica nem guarda a resposta', function (): void {
    // O handler decide de novo a cada chamada (aqui, de propósito, diferente
    // na segunda): a resposta que sai é sempre a desta chamada.
    RecordingAuthorizationHandler::$respond = fn (InboundEvent $event): AuthorizationResponse => $event->attempt === 1
        ? new AuthorizationResponse(200, ['status' => 'APPROVED'])
        : new AuthorizationResponse(409, ['status' => 'REFUSED', 'attempt' => $event->attempt]);

    postInbound('autorizacao', inboundBody('evt_repetida'), ['x-provedor-token' => INBOUND_TOKEN])
        ->assertOk()
        ->assertExactJson(['status' => 'APPROVED']);

    // A repetição, com o token anterior (rotação), também chega.
    postInbound('autorizacao', inboundBody('evt_repetida'), ['x-provedor-token' => INBOUND_TOKEN_PREVIOUS])
        ->assertStatus(409)
        ->assertExactJson(['status' => 'REFUSED', 'attempt' => 2]);

    $record = InboundWebhookEvent::query()->sole();

    expect(RecordingAuthorizationHandler::$events)->toHaveCount(2)
        ->and(RecordingAuthorizationHandler::$events[1]->id)->toBe('evt_repetida')
        ->and(RecordingAuthorizationHandler::$events[1]->receipt)->toBe($record->uuid)
        ->and($record->attempts)->toBe(2)
        ->and($record->status)->toBe(InboundStatus::Processed);
});

describe('antes do handler, as mesmas recusas da fonte comum', function (): void {
    it('cada recusa: o mesmo código, sem corpo, motivo só no log — e o handler não roda', function (array $overrides, string $source, ?string $body, array $headers, array $server, int $status, ?string $logged): void {
        authorizationSource($overrides);
        $logs = captureLogs();

        $response = postInbound($source, $body ?? inboundBody('evt_recusa'), $headers, $server);

        expect($response->getStatusCode())->toBe($status)
            ->and($response->getContent())->toBe('')
            ->and(RecordingAuthorizationHandler::$events)->toBe([])
            ->and(InboundWebhookEvent::query()->count())->toBe(0);

        if ($logged !== null) {
            expect(implode("\n", (array) $logs))->toContain($logged);
        }

        Queue::assertNothingPushed();
    })->with([
        'fonte desconhecida' => [[], 'autorizacao-inexistente', null, ['x-provedor-token' => INBOUND_TOKEN], [], 404, null],
        'IP fora da allowlist' => [['allowed_ips' => ['203.0.113.0/24']], 'autorizacao', null, ['x-provedor-token' => INBOUND_TOKEN], ['REMOTE_ADDR' => '198.51.100.9'], 403, '"reason":"ip_not_allowed"'],
        'corpo acima do limite' => [['max_body_bytes' => 200], 'autorizacao', inboundBody('evt_grande', str_repeat('x', 300)), ['x-provedor-token' => INBOUND_TOKEN], [], 413, '"reason":"body_too_large"'],
        'token ausente' => [[], 'autorizacao', null, [], [], 401, '"reason":"missing_signature"'],
        'token errado' => [[], 'autorizacao', null, ['x-provedor-token' => 'tok_errado'], [], 401, '"reason":"invalid_signature"'],
        'corpo verificado ilegível' => [[], 'autorizacao', 'MARCADOR-texto', ['x-provedor-token' => INBOUND_TOKEN], [], 400, 'webhooks.inbound.malformed'],
    ]);

    it('dentro da allowlist, passa', function (): void {
        authorizationSource(['allowed_ips' => ['203.0.113.0/24', '2001:db8:abcd::/48']]);

        postInbound('autorizacao', inboundBody('evt_ip4'), ['x-provedor-token' => INBOUND_TOKEN], ['REMOTE_ADDR' => '203.0.113.10'])->assertOk();
        postInbound('autorizacao', inboundBody('evt_ip6'), ['x-provedor-token' => INBOUND_TOKEN], ['REMOTE_ADDR' => '2001:db8:abcd:12::5'])->assertOk();

        expect(RecordingAuthorizationHandler::$events)->toHaveCount(2);
    });

    it('limite por minuto, por fonte e IP: além dele, 429 e o handler não roda', function (): void {
        authorizationSource(['rate_limit_per_minute' => 2]);
        $headers = ['x-provedor-token' => INBOUND_TOKEN];

        postInbound('autorizacao', inboundBody('evt_l1'), $headers)->assertOk();
        postInbound('autorizacao', inboundBody('evt_l2'), $headers)->assertOk();
        postInbound('autorizacao', inboundBody('evt_l3'), $headers)->assertStatus(429);
        // Outro IP tem o balde dele.
        postInbound('autorizacao', inboundBody('evt_l4'), $headers, ['REMOTE_ADDR' => '198.51.100.20'])->assertOk();

        expect(array_map(fn (InboundEvent $event): string => $event->id, RecordingAuthorizationHandler::$events))->toBe(['evt_l1', 'evt_l2', 'evt_l4']);
    });

    it('rotação de segredo: o atual e o anterior passam; outro, não', function (): void {
        // Token em cabeçalho, dois vigentes.
        postInbound('autorizacao', inboundBody('evt_rot1'), ['x-provedor-token' => INBOUND_TOKEN])->assertOk();
        postInbound('autorizacao', inboundBody('evt_rot2'), ['x-provedor-token' => INBOUND_TOKEN_PREVIOUS])->assertOk();
        postInbound('autorizacao', inboundBody('evt_rot3'), ['x-provedor-token' => 'tok_teste_revogado_000'])->assertStatus(401);

        // HMAC com carimbo, dois vigentes: assinado só com o anterior, com os
        // dois, com um desconhecido e com carimbo velho.
        authorizationSource([
            'verifier' => ['driver' => 'hmac', 'header' => 'Assinatura', 'format' => 'keyed', 'timestamp_key' => 't', 'signature_key' => 'v1', 'secrets' => [INBOUND_HMAC_SECRET, INBOUND_HMAC_SECRET_PREVIOUS]],
        ]);
        $agora = Carbon::now()->getTimestamp();
        $corpo = static fn (string $id): string => inboundBody($id);

        postInbound('autorizacao', $corpo('evt_h1'), ['Assinatura' => keyedSignature($corpo('evt_h1'), $agora, INBOUND_HMAC_SECRET_PREVIOUS)])->assertOk();
        postInbound('autorizacao', $corpo('evt_h2'), ['Assinatura' => keyedSignature($corpo('evt_h2'), $agora, INBOUND_HMAC_SECRET, INBOUND_HMAC_SECRET_PREVIOUS)])->assertOk();
        postInbound('autorizacao', $corpo('evt_h3'), ['Assinatura' => keyedSignature($corpo('evt_h3'), $agora, 'hmac_desconhecido_0000000000000000')])->assertStatus(401);
        postInbound('autorizacao', $corpo('evt_h4'), ['Assinatura' => keyedSignature($corpo('evt_h4'), $agora - 3600, INBOUND_HMAC_SECRET)])->assertStatus(401);

        expect(array_map(fn (InboundEvent $event): string => $event->id, RecordingAuthorizationHandler::$events))->toBe(['evt_rot1', 'evt_rot2', 'evt_h1', 'evt_h2']);
    });
});

describe('configuração inválida: 503 com o motivo no log, e o handler não roda', function (): void {
    it('na fonte de autorização', function (array $overrides, string $trecho): void {
        authorizationSource($overrides);
        $logs = captureLogs();

        $response = postInbound('autorizacao', inboundBody('evt_conf'), ['x-provedor-token' => INBOUND_TOKEN]);

        expect($response->getStatusCode())->toBe(503)
            ->and($response->getContent())->toBe('')
            ->and(RecordingAuthorizationHandler::$events)->toBe([])
            ->and(InboundWebhookEvent::query()->count())->toBe(0)
            ->and(implode("\n", (array) $logs))->toContain('webhooks.inbound.misconfigured')->toContain($trecho);
    })->with([
        'modo desconhecido' => [['mode' => 'sincrono'], 'modo desconhecido'],
        'modo que não é texto' => [['mode' => ['authorization']], 'modo desconhecido'],
        'handler da fila (sem o contrato de autorização)' => [['handler' => RecordingInboundHandler::class], 'InboundAuthorizationHandler'],
        'handler que não implementa nada' => [['handler' => stdClass::class], 'InboundAuthorizationHandler'],
        'opção de fila: tries' => [['tries' => 3], 'tire tries'],
        'opção de fila: store_payload' => [['store_payload' => true], 'tire store_payload'],
        'opções de fila: backoff, queue, connection' => [['backoff' => [1, 2], 'queue' => 'autorizacoes', 'connection' => 'redis'], 'tire backoff, queue, connection'],
    ]);

    it('store_payload desligado e opções nulas não são erro', function (): void {
        authorizationSource(['store_payload' => false, 'tries' => null, 'backoff' => null, 'queue' => null, 'connection' => null, 'budget_ms' => null]);

        postInbound('autorizacao', inboundBody('evt_nulos'), ['x-provedor-token' => INBOUND_TOKEN])->assertOk();
    });

    it('na fonte comum: handler de autorização ou orçamento de tempo', function (array $overrides, string $trecho): void {
        inboundSources(['token' => $overrides]);
        $logs = captureLogs();

        postInbound('token', inboundBody('evt_conf_comum'), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(503);

        expect(RecordingAuthorizationHandler::$events)->toBe([])
            ->and(implode("\n", (array) $logs))->toContain('webhooks.inbound.misconfigured')->toContain($trecho);
        Queue::assertNothingPushed();
    })->with([
        'handler de autorização sem o modo' => [['handler' => RecordingAuthorizationHandler::class], 'InboundWebhookHandler'],
        'budget_ms no modo queue' => [['budget_ms' => 500], 'budget_ms'],
    ]);
});

describe('exceção do handler', function (): void {
    it('vira 500 sem corpo — nem a mensagem nem a classe saem, nem com APP_DEBUG — e o registro fica failed com a classe', function (): void {
        config(['app.debug' => true]);
        $logs = captureLogs();
        RecordingAuthorizationHandler::$respond = function (InboundEvent $event): never {
            throw new DomainException('MARCADOR-DA-EXCECAO com dado do evento: '.json_encode($event->payload));
        };

        $response = postInbound('autorizacao', inboundBody('evt_excecao'), ['x-provedor-token' => INBOUND_TOKEN]);

        $record = InboundWebhookEvent::query()->sole();
        $tudo = implode("\n", (array) $logs);

        expect($response->getStatusCode())->toBe(500)
            ->and($response->getContent())->toBe('')
            ->and($response->headers->getCookies())->toBe([])
            ->and($record->status)->toBe(InboundStatus::Failed)
            ->and($record->attempts)->toBe(1)
            ->and($record->last_error)->toBe(DomainException::class)
            ->and($tudo)->toContain('webhooks.inbound.authorization_failed')->toContain('DomainException')
            ->and($tudo)->not->toContain('MARCADOR-DA-EXCECAO')
            ->and($tudo)->not->toContain('MARCADOR-DO-CORPO-7f3a');

        // O provedor tenta de novo e, desta vez, o handler responde.
        RecordingAuthorizationHandler::$respond = null;

        postInbound('autorizacao', inboundBody('evt_excecao'), ['x-provedor-token' => INBOUND_TOKEN])->assertOk();

        $record->refresh();

        expect($record->status)->toBe(InboundStatus::Processed)
            ->and($record->attempts)->toBe(2)
            ->and($record->last_error)->toBeNull();
    });

    it('resposta que o kit não consegue enviar também vira 500 sem corpo', function (string $caso, string $exception): void {
        RecordingAuthorizationHandler::$respond = fn (): AuthorizationResponse => match ($caso) {
            'redirecionamento' => new AuthorizationResponse(302, ['para' => 'outro-lugar']),
            'informativo' => new AuthorizationResponse(101, []),
            'acima' => new AuthorizationResponse(600, []),
            'infinito' => new AuthorizationResponse(200, ['valor' => INF]),
            default => new AuthorizationResponse(200, ['texto' => "fora do UTF-8: \xB1\x31"]),
        };

        $response = postInbound('autorizacao', inboundBody('evt_invalida'), ['x-provedor-token' => INBOUND_TOKEN]);

        expect($response->getStatusCode())->toBe(500)
            ->and($response->getContent())->toBe('')
            ->and(InboundWebhookEvent::query()->sole()->last_error)->toBe($exception);
    })->with([
        'código 3xx (redirecionamento)' => ['redirecionamento', InvalidArgumentException::class],
        'código 1xx' => ['informativo', InvalidArgumentException::class],
        'código acima de 599' => ['acima', InvalidArgumentException::class],
        'número que não vira JSON' => ['infinito', InvalidArgumentException::class],
        'texto fora do UTF-8' => ['utf8', InvalidArgumentException::class],
    ]);
});

describe('orçamento de tempo', function (): void {
    it('o tempo do handler vai para o log; acima do orçamento da fonte, aviso — sem interromper', function (): void {
        authorizationSource(['budget_ms' => 5]);
        RecordingAuthorizationHandler::$sleepMs = 30;
        $entries = captureLogEntries();

        postInbound('autorizacao', inboundBody('evt_lento'), ['x-provedor-token' => INBOUND_TOKEN])
            ->assertOk()
            ->assertExactJson(['status' => 'APPROVED', 'id' => 'evt_lento']);

        $authorized = logEntriesNamed($entries, 'webhooks.inbound.authorized');
        $over = logEntriesNamed($entries, 'webhooks.inbound.authorization_over_budget');
        $record = InboundWebhookEvent::query()->sole();

        expect($authorized)->toHaveCount(1)
            ->and($authorized[0]['level'])->toBe('info')
            ->and($authorized[0]['context']['duration_ms'])->toBeGreaterThanOrEqual(30)
            ->and($authorized[0]['context']['budget_ms'])->toBe(5)
            ->and($authorized[0]['context']['status'])->toBe(200)
            ->and($authorized[0]['context']['event'])->toBe($record->uuid)
            ->and($authorized[0]['context']['source'])->toBe('autorizacao')
            ->and($over)->toHaveCount(1)
            ->and($over[0]['level'])->toBe('warning')
            ->and($over[0]['context']['duration_ms'])->toBeGreaterThanOrEqual(30)
            ->and($over[0]['context']['budget_ms'])->toBe(5);
    });

    it('dentro do orçamento (o do pacote, sem o da fonte): só o tempo, sem aviso', function (): void {
        config(['webhooks.inbound.authorization_budget_ms' => 60000]);
        $entries = captureLogEntries();

        postInbound('autorizacao', inboundBody('evt_rapido'), ['x-provedor-token' => INBOUND_TOKEN])->assertOk();

        $authorized = logEntriesNamed($entries, 'webhooks.inbound.authorized');

        expect($authorized)->toHaveCount(1)
            ->and($authorized[0]['context']['budget_ms'])->toBe(60000)
            ->and($authorized[0]['context']['duration_ms'])->toBeInt()->toBeLessThan(60000)
            ->and(logEntriesNamed($entries, 'webhooks.inbound.authorization_over_budget'))->toBe([]);
    });

    it('a exceção lenta também leva o tempo e o aviso', function (): void {
        authorizationSource(['budget_ms' => 5]);
        RecordingAuthorizationHandler::$sleepMs = 20;
        RecordingAuthorizationHandler::$respond = function (): never {
            throw new RuntimeException('lenta e com erro');
        };
        $entries = captureLogEntries();

        postInbound('autorizacao', inboundBody('evt_lenta_erro'), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(500);

        $failed = logEntriesNamed($entries, 'webhooks.inbound.authorization_failed');

        expect($failed)->toHaveCount(1)
            ->and($failed[0]['context']['duration_ms'])->toBeGreaterThanOrEqual(20)
            ->and($failed[0]['context']['exception'])->toBe(RuntimeException::class)
            ->and(logEntriesNamed($entries, 'webhooks.inbound.authorization_over_budget'))->toHaveCount(1);
    });

    it('o padrão do pacote é 2000 ms, e o aplicativo sem a chave cai nele', function (): void {
        $source = InboundSource::fromConfig('orcamento', [
            'mode' => 'authorization',
            'verifier' => ['driver' => 'header_token', 'header' => 'x', 'secrets' => 's'],
            'handler' => RecordingAuthorizationHandler::class,
        ]);

        expect(config('webhooks.inbound.authorization_budget_ms'))->toBe(2000)
            ->and($source->authorizationBudgetMs())->toBe(2000);

        config(['webhooks.inbound' => ['enabled' => true]]);

        expect($source->authorizationBudgetMs())->toBe(2000);
    });
});

it('a fonte comum continua igual, ao lado da de autorização: 202, fila e deduplicação', function (): void {
    postInbound('token', inboundBody('evt_comum'), ['x-provedor-token' => INBOUND_TOKEN])
        ->assertStatus(202)
        ->assertExactJson(['status' => 'accepted']);
    postInbound('token', inboundBody('evt_comum'), ['x-provedor-token' => INBOUND_TOKEN])
        ->assertOk()
        ->assertExactJson(['status' => 'duplicate']);
    postInbound('autorizacao', inboundBody('evt_comum'), ['x-provedor-token' => INBOUND_TOKEN])
        ->assertOk()
        ->assertExactJson(['status' => 'APPROVED', 'id' => 'evt_comum']);

    expect(InboundWebhooks::source('token')?->mode)->toBe(InboundMode::Queue)
        ->and(InboundWebhooks::source('autorizacao')?->mode)->toBe(InboundMode::Authorization)
        ->and(InboundWebhookEvent::query()->orderBy('source')->pluck('status', 'source')->map(fn (InboundStatus $status): string => $status->value)->all())
        ->toBe(['autorizacao' => 'processed', 'token' => 'received'])
        ->and(RecordingAuthorizationHandler::$events)->toHaveCount(1);

    Queue::assertPushed(ProcessInboundWebhook::class, 1);
});

describe('registro pelo código', function (): void {
    it('com mode: Authorization e o orçamento da fonte', function (): void {
        InboundWebhooks::register(new InboundSource(
            name: 'autoriza-codigo',
            verifier: new SharedSecretHeader('x-chave', [INBOUND_TOKEN]),
            handler: new class implements InboundAuthorizationHandler
            {
                public function authorize(InboundEvent $event): AuthorizationResponse
                {
                    return new AuthorizationResponse(201, ['aceito' => $event->id]);
                }
            },
            parser: new JsonEventParser(idPath: 'id', typePath: 'event'),
            mode: InboundMode::Authorization,
            budgetMs: 1500,
        ));

        postInbound('autoriza-codigo', inboundBody('evt_codigo'), ['x-chave' => INBOUND_TOKEN])
            ->assertStatus(201)
            ->assertExactJson(['aceito' => 'evt_codigo']);
        postInbound('autoriza-codigo', inboundBody('evt_codigo'), ['x-chave' => 'outra'])->assertStatus(401);

        expect(InboundWebhooks::source('autoriza-codigo')?->authorizationBudgetMs())->toBe(1500);
        Queue::assertNothingPushed();
    });

    it('o contrato do modo e as opções são conferidos na construção', function (Closure $build, string $trecho): void {
        expect($build)->toThrow(InvalidSourceConfiguration::class, $trecho);
    })->with([
        'handler da fila (classe) no modo authorization' => [fn () => new InboundSource(name: 'x', verifier: new SharedSecretHeader('x', ['s']), handler: RecordingInboundHandler::class, mode: InboundMode::Authorization), 'InboundAuthorizationHandler'],
        'handler da fila (objeto) no modo authorization' => [fn () => new InboundSource(name: 'x', verifier: new SharedSecretHeader('x', ['s']), handler: new RecordingInboundHandler, mode: InboundMode::Authorization), 'InboundAuthorizationHandler'],
        'handler de autorização (objeto) no modo queue' => [fn () => new InboundSource(name: 'x', verifier: new SharedSecretHeader('x', ['s']), handler: new RecordingAuthorizationHandler), 'InboundWebhookHandler'],
        'tentativas no modo authorization' => [fn () => new InboundSource(name: 'x', verifier: new SharedSecretHeader('x', ['s']), handler: RecordingAuthorizationHandler::class, tries: 2, mode: InboundMode::Authorization), 'tire tries'],
        'orçamento no modo queue' => [fn () => new InboundSource(name: 'x', verifier: new SharedSecretHeader('x', ['s']), handler: RecordingInboundHandler::class, budgetMs: 100), 'budget_ms'],
    ]);
});

it('a fila nunca chama o handler de autorização (fonte que mudou de modo com evento na fila)', function (): void {
    postInbound('token', inboundBody('evt_trocou'), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(202);

    /** @var ProcessInboundWebhook $job */
    $job = Queue::pushed(ProcessInboundWebhook::class)->sole();

    // A fonte passou para o modo authorization com o job ainda na fila.
    inboundSources(['token' => ['mode' => 'authorization', 'handler' => RecordingAuthorizationHandler::class]]);

    expect(fn () => app()->call([$job, 'handle']))->toThrow(InvalidSourceConfiguration::class, 'não roda na fila')
        ->and(RecordingAuthorizationHandler::$events)->toBe([])
        ->and(RecordingInboundHandler::$events)->toBe([])
        ->and(InboundWebhookEvent::query()->sole()->last_error)->toBe(InvalidSourceConfiguration::class)
        // Os dois lados: a fonte de autorização não dá handler de fila, e a
        // comum não dá handler de autorização.
        ->and(fn () => InboundWebhooks::source('token')?->handler())->toThrow(InvalidSourceConfiguration::class, 'não roda na fila')
        ->and(fn () => InboundWebhooks::source('corpo')?->authorizationHandler())->toThrow(InvalidSourceConfiguration::class, 'não há handler de autorização');
});

it('o corpo e o token nunca vão para o log nem para a trilha — no aceito, no recusado e na exceção', function (): void {
    $logs = captureLogs();
    $marcador = 'MARCADOR-SIGILOSO-AUTORIZACAO-8d4a';

    postInbound('autorizacao', inboundBody('evt_s1', $marcador), ['x-provedor-token' => INBOUND_TOKEN])->assertOk();
    postInbound('autorizacao', inboundBody('evt_s2', $marcador), ['x-provedor-token' => 'tok_errado_'.$marcador])->assertStatus(401);

    RecordingAuthorizationHandler::$respond = function (InboundEvent $event): never {
        throw new RuntimeException('com o corpo: '.json_encode($event->payload));
    };
    postInbound('autorizacao', inboundBody('evt_s3', $marcador), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(500);

    $tudo = implode("\n", (array) $logs);
    $trilha = RequestLog::query()->get()->map(fn (RequestLog $log): string => (string) json_encode($log->getAttributes()))->implode("\n");

    expect($tudo)->toContain('webhooks.inbound.authorized')
        ->and($tudo)->not->toContain($marcador)
        ->and($tudo)->not->toContain(INBOUND_TOKEN)
        ->and(RequestLog::query()->count())->toBeGreaterThanOrEqual(3)
        ->and($trilha)->not->toContain($marcador)
        ->and($trilha)->not->toContain(INBOUND_TOKEN)
        ->and($trilha)->toContain('_corpo_assinado');
});
