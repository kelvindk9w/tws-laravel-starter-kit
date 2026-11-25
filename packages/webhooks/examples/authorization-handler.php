<?php

declare(strict_types=1);

use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\DB;
use Twstec\Kit\Webhooks\Inbound\AuthorizationResponse;
use Twstec\Kit\Webhooks\Inbound\Contracts\InboundAuthorizationHandler;
use Twstec\Kit\Webhooks\Inbound\InboundEvent;

/**
 * Exemplo de handler de AUTORIZAÇÃO, para uma fonte com
 * `'mode' => 'authorization'`: o provedor pergunta, na hora, se pode seguir
 * com uma operação (`{"id": "...", "operation": {"amount": 1500}}`, valor em
 * unidades menores) e espera a resposta desta requisição. O formato da
 * resposta é o do provedor de exemplo; o kit só a entrega.
 *
 * - O kit NÃO deduplica nem guarda a resposta. Aqui, a decisão vai para o
 *   banco do aplicativo com INSERT de conflito ignorado sobre a unicidade
 *   (fonte, id da chamada), e a resposta sai da decisão GRAVADA: a mesma
 *   chamada repetida — inclusive duas ao mesmo tempo — recebe a mesma
 *   resposta, mesmo que a regra tenha mudado no meio.
 * - O payload é texto de terceiros: valor que não é inteiro positivo é
 *   recusado.
 * - Sem o limite configurado, a resolução do handler falha e o kit responde
 *   500 (falha fechada).
 *
 * A tabela é do aplicativo (migration dele):
 *
 *     Schema::create('authorization_decisions', function (Blueprint $table) {
 *         $table->id();
 *         $table->string('source', 64);
 *         $table->string('call_id', 255);
 *         $table->boolean('approved');
 *         $table->string('reason', 50)->nullable();
 *         $table->timestampTz('created_at');
 *         $table->unique(['source', 'call_id']);
 *     });
 */
final class ExampleAuthorizationHandler implements InboundAuthorizationHandler
{
    public function __construct(
        #[Config('services.example.authorization_max_amount')] private readonly int $maxAmount,
    ) {}

    public function authorize(InboundEvent $event): AuthorizationResponse
    {
        $amount = $event->get('operation.amount');

        [$approved, $reason] = match (true) {
            ! is_int($amount) || $amount <= 0 => [false, 'invalid_amount'],
            $amount > $this->maxAmount => [false, 'over_limit'],
            default => [true, null],
        };

        // Quem gravou primeiro decide; a repetição só lê.
        DB::table('authorization_decisions')->insertOrIgnore([
            'source' => $event->source,
            'call_id' => $event->id,
            'approved' => $approved,
            'reason' => $reason,
            'created_at' => now(),
        ]);

        $decision = DB::table('authorization_decisions')
            ->where('source', $event->source)
            ->where('call_id', $event->id)
            ->first(['approved', 'reason']);

        if ($decision !== null && (bool) $decision->approved) {
            return new AuthorizationResponse(200, ['status' => 'APPROVED']);
        }

        return new AuthorizationResponse(200, ['status' => 'REFUSED', 'reason' => $decision->reason ?? 'unknown']);
    }
}
