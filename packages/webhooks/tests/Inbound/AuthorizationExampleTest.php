<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

// =============================================================================
// O EXEMPLO de handler de autorização (examples/authorization-handler.php),
// pela rota de verdade: decide pelo limite configurado, recusa valor
// inválido e — o ponto do exemplo — devolve a MESMA resposta para a mesma
// chamada, pela decisão gravada no banco do aplicativo, mesmo sob corrida e
// mesmo com a regra mudada no meio.
// =============================================================================

beforeEach(function (): void {
    Queue::fake();

    // A tabela do aplicativo, como o docblock do exemplo mostra.
    Schema::create('authorization_decisions', function (Blueprint $table): void {
        $table->id();
        $table->string('source', 64);
        $table->string('call_id', 255);
        $table->boolean('approved');
        $table->string('reason', 50)->nullable();
        $table->timestampTz('created_at');
        $table->unique(['source', 'call_id']);
    });

    config(['services.example.authorization_max_amount' => 1000]);

    inboundSources(['exemplo' => [
        'mode' => 'authorization',
        'verifier' => ['driver' => 'header_token', 'header' => 'x-provedor-token', 'secrets' => [INBOUND_TOKEN]],
        'handler' => ExampleAuthorizationHandler::class,
        'event' => ['id' => 'id', 'type' => 'event'],
    ]]);
});

function exampleCall(string $id, mixed $amount): string
{
    return inboundBody($id, 'MARCADOR-EXEMPLO', ['operation' => ['amount' => $amount]]);
}

it('aprova dentro do limite; recusa acima dele e valor que não é inteiro positivo', function (mixed $amount, array $expected): void {
    postInbound('exemplo', exampleCall('chamada_'.md5((string) json_encode($amount)), $amount), ['x-provedor-token' => INBOUND_TOKEN])
        ->assertOk()
        ->assertExactJson($expected);

    Queue::assertNothingPushed();
})->with([
    'dentro do limite' => [800, ['status' => 'APPROVED']],
    'no limite' => [1000, ['status' => 'APPROVED']],
    'acima do limite' => [1001, ['status' => 'REFUSED', 'reason' => 'over_limit']],
    'zero' => [0, ['status' => 'REFUSED', 'reason' => 'invalid_amount']],
    'negativo' => [-5, ['status' => 'REFUSED', 'reason' => 'invalid_amount']],
    'texto' => ['800', ['status' => 'REFUSED', 'reason' => 'invalid_amount']],
    'decimal' => [8.5, ['status' => 'REFUSED', 'reason' => 'invalid_amount']],
    'ausente' => [null, ['status' => 'REFUSED', 'reason' => 'invalid_amount']],
]);

it('a mesma chamada recebe a mesma resposta, mesmo com a regra mudada no meio', function (): void {
    $headers = ['x-provedor-token' => INBOUND_TOKEN];

    postInbound('exemplo', exampleCall('chamada_a', 800), $headers)->assertOk()->assertExactJson(['status' => 'APPROVED']);

    config(['services.example.authorization_max_amount' => 100]);

    // A repetição devolve a decisão gravada; uma chamada nova segue a regra nova.
    postInbound('exemplo', exampleCall('chamada_a', 800), $headers)->assertOk()->assertExactJson(['status' => 'APPROVED']);
    postInbound('exemplo', exampleCall('chamada_b', 800), $headers)->assertOk()->assertExactJson(['status' => 'REFUSED', 'reason' => 'over_limit']);

    expect(DB::table('authorization_decisions')->count())->toBe(2);
});

it('CORRIDA: outra chamada igual grava a decisão primeiro — esta responde a decisão gravada, não a sua', function (): void {
    $competidor = false;

    DB::connection()->beforeExecuting(function (string $sql) use (&$competidor): void {
        if ($competidor || ! str_contains($sql, 'insert or ignore into "authorization_decisions"')) {
            return;
        }

        $competidor = true;

        DB::table('authorization_decisions')->insert([
            'source' => 'exemplo',
            'call_id' => 'chamada_corrida',
            'approved' => false,
            'reason' => 'over_limit',
            'created_at' => now(),
        ]);
    });

    postInbound('exemplo', exampleCall('chamada_corrida', 800), ['x-provedor-token' => INBOUND_TOKEN])
        ->assertOk()
        ->assertExactJson(['status' => 'REFUSED', 'reason' => 'over_limit']);

    expect($competidor)->toBeTrue()
        ->and(DB::table('authorization_decisions')->count())->toBe(1);
});

it('sem o limite configurado: 500 sem corpo (falha fechada), nada decidido', function (): void {
    config(['services.example.authorization_max_amount' => null]);

    $response = postInbound('exemplo', exampleCall('chamada_sem_limite', 800), ['x-provedor-token' => INBOUND_TOKEN]);

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getContent())->toBe('')
        ->and(DB::table('authorization_decisions')->count())->toBe(0);
});
