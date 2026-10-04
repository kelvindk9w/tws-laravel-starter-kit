<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Delivery;

/**
 * O BACKOFF das novas tentativas (`webhooks.delivery.backoff`, em segundos;
 * padrão 1 min, 5 min, 30 min, 2 h, 12 h). Valor que não é inteiro positivo é
 * ignorado; lista vazia = sem nova tentativa (só a primeira).
 */
final class RetrySchedule
{
    /**
     * @return list<int>
     */
    public static function delays(): array
    {
        return array_values(array_filter(
            array_map(static fn (mixed $delay): int => is_numeric($delay) ? (int) $delay : 0, (array) config('webhooks.delivery.backoff', [])),
            static fn (int $delay): bool => $delay > 0,
        ));
    }

    /**
     * Total de tentativas automáticas: a primeira e uma por espera.
     */
    public static function maxAttempts(): int
    {
        return 1 + count(self::delays());
    }

    /**
     * Espera antes da próxima tentativa, depois de `$attempts` tentativas
     * feitas; nulo = acabaram.
     */
    public static function delayAfter(int $attempts): ?int
    {
        return self::delays()[$attempts - 1] ?? null;
    }
}
