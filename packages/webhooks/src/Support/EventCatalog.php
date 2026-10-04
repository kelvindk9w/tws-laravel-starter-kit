<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Support;

use InvalidArgumentException;

/**
 * O CATÁLOGO de eventos que o aplicativo emite (`webhooks.events`) mais o
 * evento de teste do pacote (`webhook.ping`, só pelo botão "enviar teste").
 *
 * Nome de evento: minúsculas, dígitos, `_` e `.`, de 3 a 100 caracteres, com
 * pelo menos um ponto (`order.created`). Evento fora do catálogo é recusado
 * no disparo — erro de programação, nunca envio silencioso.
 */
final class EventCatalog
{
    public const PING = 'webhook.ping';

    public const NAME_PATTERN = '/^[a-z0-9_]+(?:\.[a-z0-9_]+)+$/';

    /**
     * Os eventos que um endpoint pode assinar, na ordem da configuração.
     *
     * @return list<string>
     */
    public static function events(): array
    {
        $events = [];

        foreach ((array) config('webhooks.events', []) as $event) {
            if (is_string($event) && self::validName($event) && $event !== self::PING && ! in_array($event, $events, true)) {
                $events[] = $event;
            }
        }

        return $events;
    }

    public static function validName(string $event): bool
    {
        return strlen($event) >= 3 && strlen($event) <= 100 && preg_match(self::NAME_PATTERN, $event) === 1;
    }

    /**
     * @throws InvalidArgumentException evento fora do catálogo
     */
    public static function assertDispatchable(string $event): void
    {
        if (! in_array($event, self::events(), true)) {
            throw new InvalidArgumentException("Evento de webhook fora do catálogo (webhooks.events): [{$event}].");
        }
    }

    /**
     * Rótulo da tela: a tradução `webhooks.events.<nome>` quando existe; senão,
     * o próprio nome.
     */
    public static function label(string $event): string
    {
        if ($event === '*') {
            return __('webhooks.events_all');
        }

        $key = 'webhooks.events.'.$event;

        return app('translator')->has($key) ? (string) __($key) : $event;
    }
}
