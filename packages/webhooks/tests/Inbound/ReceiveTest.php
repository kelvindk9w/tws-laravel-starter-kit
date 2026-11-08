<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Twstec\Kit\Foundation\Logging\Models\RequestLog;
use Twstec\Kit\Webhooks\Enums\InboundStatus;
use Twstec\Kit\Webhooks\Inbound\ProcessInboundWebhook;
use Twstec\Kit\Webhooks\Models\InboundWebhookEvent;
use Twstec\Kit\Webhooks\Tests\Fixtures\RecordingInboundHandler;

// =============================================================================
// O RECEBIMENTO pela rota de verdade (pilha global do kit inteira): fonte
// desconhecida, sem sessão/CSRF, verificação, deduplicação (inclusive a
// corrida), allowlist de IP com e sem proxy confiável, limite de corpo,
// limite de requisições, fila fora do ar — e que o corpo e o token nunca
// aparecem em log nem na trilha.
// =============================================================================

beforeEach(function (): void {
    RecordingInboundHandler::reset();
    inboundSources();
    Queue::fake();
});

it('fonte desconhecida (ou nome fora do padrão) responde 404, sem corpo', function (): void {
    postInbound('inexistente', inboundBody(), ['x-provedor-token' => INBOUND_TOKEN])->assertNotFound();

    $response = postInbound('Token', inboundBody(), ['x-provedor-token' => INBOUND_TOKEN]);

    expect($response->getStatusCode())->toBe(404);
    Queue::assertNothingPushed();
    expect(InboundWebhookEvent::query()->count())->toBe(0);
});

it('a rota não tem sessão, cookie nem CSRF — só o limite por fonte', function (): void {
    $route = Route::getRoutes()->getByName('webhooks.inbound');

    expect($route)->not->toBeNull()
        ->and($route->methods())->toBe(['POST'])
        ->and($route->uri())->toBe('webhooks/inbound/{source}')
        ->and($route->gatherMiddleware())->toBe(['throttle:webhooks-inbound']);

    $response = postInbound('token', inboundBody(), ['x-provedor-token' => INBOUND_TOKEN]);

    $response->assertStatus(202);
    expect($response->headers->getCookies())->toBe([]);
});

it('evento válido: 202, registro de deduplicação SEM o corpo e o job na fila', function (): void {
    postInbound('token', inboundBody('evt_aceito'), ['x-provedor-token' => INBOUND_TOKEN])
        ->assertStatus(202)
        ->assertExactJson(['status' => 'accepted']);

    $record = InboundWebhookEvent::query()->sole();

    expect($record->source)->toBe('token')
        ->and($record->event_id)->toBe('evt_aceito')
        ->and($record->event_type)->toBe('PAYMENT_RECEIVED')
        // 10:15 em São Paulo (UTC-3) = 13:15 UTC.
        ->and($record->occurred_at?->utc()->format('Y-m-d H:i:s'))->toBe('2026-11-07 13:15:00')
        ->and($record->status)->toBe(InboundStatus::Received)
        ->and($record->payload)->toBeNull()
        ->and(DB::table('webhook_inbound_events')->value('payload'))->toBeNull()
        ->and($record->payload_hash)->toBe(hash('sha256', inboundBody('evt_aceito')));

    Queue::assertPushed(ProcessInboundWebhook::class, fn (ProcessInboundWebhook $job): bool => $job->receiptId === $record->id
        && $job->source === 'token'
        && data_get($job->payload, 'payment.id') === 'pay_123');
});

it('falha de verificação: 401 sem dizer o motivo, motivo só no log interno, nada gravado nem na fila', function (string $caso, array $headers, string $motivo): void {
    $logs = captureLogs();

    $response = postInbound('token', inboundBody(), $headers);

    expect($response->getStatusCode())->toBe(401)
        ->and($response->getContent())->toBe('')
        ->and(InboundWebhookEvent::query()->count())->toBe(0)
        ->and(implode("\n", (array) $logs))->toContain('webhooks.inbound.rejected')->toContain('"reason":"'.$motivo.'"');

    Queue::assertNothingPushed();
})->with([
    'token errado' => ['errado', ['x-provedor-token' => 'tok_errado'], 'invalid_signature'],
    'sem o cabeçalho' => ['ausente', [], 'missing_signature'],
]);

it('carimbo velho e futuro são recusados na rota (repetição de requisição capturada)', function (): void {
    $body = inboundBody('evt_ts');
    $agora = Carbon::now()->getTimestamp();

    postInbound('carimbo', $body, ['Assinatura' => keyedSignature($body, $agora - 3600, INBOUND_HMAC_SECRET)])->assertStatus(401);
    postInbound('carimbo', $body, ['Assinatura' => keyedSignature($body, $agora + 3600, INBOUND_HMAC_SECRET)])->assertStatus(401);
    postInbound('carimbo', $body, ['Assinatura' => keyedSignature($body, $agora - 30, INBOUND_HMAC_SECRET_PREVIOUS)])->assertStatus(202);

    expect(InboundWebhookEvent::query()->pluck('event_id')->all())->toBe(['evt_ts']);
});

it('repetição: 200 "duplicate", um registro só e um job só', function (): void {
    $headers = ['x-provedor-token' => INBOUND_TOKEN];

    postInbound('token', inboundBody('evt_rep'), $headers)->assertStatus(202);
    postInbound('token', inboundBody('evt_rep'), $headers)->assertOk()->assertExactJson(['status' => 'duplicate']);
    postInbound('token', inboundBody('evt_rep'), ['x-provedor-token' => INBOUND_TOKEN_PREVIOUS])->assertOk();

    expect(InboundWebhookEvent::query()->count())->toBe(1);
    Queue::assertPushed(ProcessInboundWebhook::class, 1);
});

it('o mesmo id em OUTRA fonte não é repetição', function (): void {
    inboundSources(['token2' => config('webhooks.inbound_sources.token')]);

    postInbound('token', inboundBody('evt_x'), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(202);
    postInbound('token2', inboundBody('evt_x'), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(202);

    expect(InboundWebhookEvent::query()->orderBy('source')->pluck('source')->all())->toBe(['token', 'token2']);
});

it('o mesmo id com corpo diferente continua repetição — e deixa aviso no log', function (): void {
    $logs = captureLogs();
    $headers = ['x-provedor-token' => INBOUND_TOKEN];

    postInbound('token', inboundBody('evt_mm', 'primeiro'), $headers)->assertStatus(202);
    postInbound('token', inboundBody('evt_mm', 'segundo'), $headers)->assertOk()->assertExactJson(['status' => 'duplicate']);

    expect(implode("\n", (array) $logs))->toContain('webhooks.inbound.duplicate_mismatch');
    Queue::assertPushed(ProcessInboundWebhook::class, 1);
});

it('CORRIDA: outra requisição grava o mesmo id entre a verificação e o INSERT — vira repetição, sem erro e sem segundo job', function (): void {
    // A outra requisição (mesmo id) ganha a corrida: o INSERT dela entra
    // logo ANTES do INSERT desta (gancho antes da execução da consulta). Um
    // "consulta e depois insere" passaria pela consulta, perderia no INSERT
    // e estouraria a unicidade; o INSERT com conflito ignorado só não grava.
    $competidor = false;

    DB::connection()->beforeExecuting(function (string $sql) use (&$competidor): void {
        if ($competidor || ! str_contains($sql, 'insert or ignore into "webhook_inbound_events"')) {
            return;
        }

        $competidor = true;

        DB::table('webhook_inbound_events')->insert([
            'uuid' => (string) Str::uuid7(),
            'source' => 'token',
            'event_id' => 'evt_corrida',
            'status' => InboundStatus::Received->value,
            'attempts' => 0,
            'payload_hash' => hash('sha256', inboundBody('evt_corrida')),
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    postInbound('token', inboundBody('evt_corrida'), ['x-provedor-token' => INBOUND_TOKEN])
        ->assertOk()
        ->assertExactJson(['status' => 'duplicate']);

    expect($competidor)->toBeTrue()
        ->and(InboundWebhookEvent::query()->where('event_id', 'evt_corrida')->count())->toBe(1);
    Queue::assertNothingPushed();
});

it('a unicidade (fonte, id) é do BANCO: um INSERT comum repetido é recusado', function (): void {
    $row = static fn (): array => [
        'uuid' => (string) Str::uuid7(),
        'source' => 'token',
        'event_id' => 'evt_unico',
        'status' => InboundStatus::Received->value,
        'attempts' => 0,
        'payload_hash' => str_repeat('a', 64),
        'received_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ];

    DB::table('webhook_inbound_events')->insert($row());

    expect(fn () => DB::table('webhook_inbound_events')->insert($row()))->toThrow(QueryException::class)
        // E o caminho do recebimento (INSERT com conflito ignorado) não lança.
        ->and(DB::table('webhook_inbound_events')->insertOrIgnore($row()))->toBe(0);
});

it('evento que FALHOU de vez volta para a fila no reenvio do provedor — uma vez', function (): void {
    $headers = ['x-provedor-token' => INBOUND_TOKEN];

    postInbound('token', inboundBody('evt_falhou'), $headers)->assertStatus(202);
    InboundWebhookEvent::query()->update(['status' => InboundStatus::Failed->value]);

    postInbound('token', inboundBody('evt_falhou'), $headers)->assertStatus(202)->assertExactJson(['status' => 'accepted']);
    postInbound('token', inboundBody('evt_falhou'), $headers)->assertOk()->assertExactJson(['status' => 'duplicate']);

    expect(InboundWebhookEvent::query()->sole()->status)->toBe(InboundStatus::Received);
    Queue::assertPushed(ProcessInboundWebhook::class, 2);
});

it('sem id no corpo nem no esquema, deduplica pelo hash do corpo', function (): void {
    inboundSources(['token' => ['event' => ['type' => 'event']]]);
    $body = (string) json_encode(['event' => 'SEM_ID', 'valor' => 10]);

    postInbound('token', $body, ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(202);
    postInbound('token', $body, ['x-provedor-token' => INBOUND_TOKEN])->assertOk();

    expect(InboundWebhookEvent::query()->sole()->event_id)->toBe('sha256:'.hash('sha256', $body));
});

it('Standard Webhooks: o webhook-id assinado é o id que deduplica', function (): void {
    $body = (string) json_encode(['type' => 'invoice.paid', 'data' => ['id' => 'inv_1']]);
    $headers = standardHeaders($body, 'msg_std_1', Carbon::now()->getTimestamp(), standardSecret('atual'));

    postInbound('padrao', $body, $headers)->assertStatus(202);
    postInbound('padrao', $body, $headers)->assertOk();

    $record = InboundWebhookEvent::query()->sole();

    expect($record->event_id)->toBe('msg_std_1')
        ->and($record->event_type)->toBe('invoice.paid')
        ->and($record->occurred_at?->getTimestamp())->toBe((int) $headers['webhook-timestamp']);
});

it('corpo verificado que não é JSON objeto: 400, nada gravado', function (string $body): void {
    $logs = captureLogs();

    postInbound('token', $body, ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(400);

    expect(InboundWebhookEvent::query()->count())->toBe(0)
        ->and(implode("\n", (array) $logs))->toContain('webhooks.inbound.malformed')->not->toContain($body);
})->with([
    'JSON quebrado' => ['{"id": "evt_1", MARCADOR'],
    'lista na raiz' => ['[1,2,3]'],
    'texto puro' => ['MARCADOR-texto'],
]);

describe('allowlist de IP da fonte', function (): void {
    it('IPv4 com CIDR: dentro passa, fora é 403 (com motivo só no log) — antes da verificação', function (): void {
        inboundSources(['token' => ['allowed_ips' => ['203.0.113.0/24', '198.51.100.7']]]);
        $logs = captureLogs();

        postInbound('token', inboundBody('evt_ip1'), ['x-provedor-token' => INBOUND_TOKEN], ['REMOTE_ADDR' => '203.0.113.10'])->assertStatus(202);
        postInbound('token', inboundBody('evt_ip2'), ['x-provedor-token' => INBOUND_TOKEN], ['REMOTE_ADDR' => '198.51.100.7'])->assertStatus(202);

        $fora = postInbound('token', inboundBody('evt_ip3'), ['x-provedor-token' => INBOUND_TOKEN], ['REMOTE_ADDR' => '203.0.114.10']);

        expect($fora->getStatusCode())->toBe(403)
            ->and($fora->getContent())->toBe('')
            ->and(implode("\n", (array) $logs))->toContain('ip_not_allowed')
            ->and(InboundWebhookEvent::query()->pluck('event_id')->sort()->values()->all())->toBe(['evt_ip1', 'evt_ip2']);
    });

    it('IPv6 com prefixo', function (): void {
        inboundSources(['token' => ['allowed_ips' => ['2001:db8:abcd::/48']]]);

        postInbound('token', inboundBody('evt_v6a'), ['x-provedor-token' => INBOUND_TOKEN], ['REMOTE_ADDR' => '2001:db8:abcd:12::5'])->assertStatus(202);
        postInbound('token', inboundBody('evt_v6b'), ['x-provedor-token' => INBOUND_TOKEN], ['REMOTE_ADDR' => '2001:db8:abce::5'])->assertStatus(403);
        postInbound('token', inboundBody('evt_v6c'), ['x-provedor-token' => INBOUND_TOKEN], ['REMOTE_ADDR' => '203.0.113.10'])->assertStatus(403);
    });

    it('X-Forwarded-For forjado não move a comparação; com o proxy declarado confiável, vale o IP real', function (): void {
        inboundSources(['token' => ['allowed_ips' => ['198.51.100.7']]]);
        $viaProxy = ['REMOTE_ADDR' => '10.0.0.2', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7'];

        postInbound('token', inboundBody('evt_px1'), ['x-provedor-token' => INBOUND_TOKEN], $viaProxy)->assertStatus(403);

        config(['security.proxies.trusted' => ['10.0.0.0/8']]);

        postInbound('token', inboundBody('evt_px2'), ['x-provedor-token' => INBOUND_TOKEN], $viaProxy)->assertStatus(202);
        postInbound('token', inboundBody('evt_px3'), ['x-provedor-token' => INBOUND_TOKEN], ['REMOTE_ADDR' => '10.0.0.2', 'HTTP_X_FORWARDED_FOR' => '203.0.113.99'])->assertStatus(403);
    });

    it('faixa inválida na allowlist é erro de configuração: 503 (falha fechada), nunca "libera tudo"', function (): void {
        inboundSources(['token' => ['allowed_ips' => ['999.1.1.1/8']]]);
        $logs = captureLogs();

        postInbound('token', inboundBody(), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(503);

        expect(implode("\n", (array) $logs))->toContain('webhooks.inbound.misconfigured');
    });
});

it('corpo acima do limite: 413 antes de verificar, nada gravado', function (): void {
    inboundSources(['token' => ['max_body_bytes' => 200]]);
    $grande = inboundBody('evt_grande', str_repeat('x', 300));

    postInbound('token', $grande, ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(413);
    postInbound('token', inboundBody('evt_pequeno', 'p'), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(202);

    expect(InboundWebhookEvent::query()->pluck('event_id')->all())->toBe(['evt_pequeno']);
});

it('limite de requisições por fonte e IP: além dele, 429', function (): void {
    inboundSources(['token' => ['rate_limit_per_minute' => 2]]);
    $headers = ['x-provedor-token' => INBOUND_TOKEN];

    postInbound('token', inboundBody('evt_r1'), $headers)->assertStatus(202);
    postInbound('token', inboundBody('evt_r2'), $headers)->assertStatus(202);
    postInbound('token', inboundBody('evt_r3'), $headers)->assertStatus(429);
    // Outro IP tem o balde dele.
    postInbound('token', inboundBody('evt_r4'), $headers, ['REMOTE_ADDR' => '198.51.100.20'])->assertStatus(202);
});

it('fila fora do ar: 503 e o registro é desfeito — o reenvio do provedor entra normalmente', function (): void {
    inboundSources(['token' => ['connection' => 'fila-que-nao-existe']]);
    $logs = captureLogs();

    // A fila de verdade (não a falsa) — a conexão inexistente lança no envio.
    Queue::swap(Queue::getFacadeRoot()->queue);

    postInbound('token', inboundBody('evt_fila'), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(503);

    expect(InboundWebhookEvent::query()->count())->toBe(0)
        ->and(implode("\n", (array) $logs))->toContain('webhooks.inbound.queue_unavailable');

    inboundSources();
    Queue::fake();

    postInbound('token', inboundBody('evt_fila'), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(202);
    Queue::assertPushed(ProcessInboundWebhook::class, 1);
});

it('handler que não implementa o contrato: 503 com o motivo no log', function (): void {
    inboundSources(['token' => ['handler' => stdClass::class]]);
    $logs = captureLogs();

    postInbound('token', inboundBody(), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(503);

    expect(implode("\n", (array) $logs))->toContain('webhooks.inbound.misconfigured')->toContain('handler');
});

describe('o corpo e os segredos nunca vão para log nem trilha', function (): void {
    it('nem no aceito, nem no recusado, nem no repetido', function (): void {
        $logs = captureLogs();
        $marcador = 'MARCADOR-SIGILOSO-91c2';

        postInbound('token', inboundBody('evt_l1', $marcador), ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(202);
        postInbound('token', inboundBody('evt_l1', $marcador), ['x-provedor-token' => INBOUND_TOKEN])->assertOk();
        postInbound('token', inboundBody('evt_l2', $marcador), ['x-provedor-token' => 'tok_errado_'.$marcador])->assertStatus(401);

        $body = hash_hmac('sha256', inboundBody('evt_l3', $marcador), INBOUND_HMAC_SECRET);
        postInbound('corpo', inboundBody('evt_l3', $marcador), ['X-Hub-Signature-256' => 'sha256='.$body])->assertStatus(202);

        $tudo = implode("\n", (array) $logs);
        $trilha = RequestLog::query()->get()->map(fn (RequestLog $log): string => (string) json_encode($log->getAttributes()))->implode("\n");

        expect($tudo)->not->toBe('')
            ->and($tudo)->not->toContain($marcador)
            ->and($tudo)->not->toContain(INBOUND_TOKEN)
            ->and($tudo)->not->toContain($body)
            ->and(RequestLog::query()->count())->toBeGreaterThanOrEqual(4)
            ->and($trilha)->not->toContain($marcador)
            ->and($trilha)->not->toContain(INBOUND_TOKEN)
            ->and($trilha)->toContain('_corpo_assinado');
    });

    it('o caminho entra nos caminhos de corpo assinado: texto que parece ataque não é recusado pelo filtro', function (): void {
        expect(config('security.validation.signed_body_paths'))->toContain('webhooks/inbound/*');

        $body = inboundBody('evt_atk', "<script>alert(1)</script> ' OR 1=1 -- ../../etc/passwd");

        postInbound('token', $body, ['x-provedor-token' => INBOUND_TOKEN])->assertStatus(202);
    });
});
