<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Tests\Fixtures;

use Closure;
use Twstec\Kit\Webhooks\Inbound\AuthorizationResponse;
use Twstec\Kit\Webhooks\Inbound\Contracts\InboundAuthorizationHandler;
use Twstec\Kit\Webhooks\Inbound\InboundEvent;

/**
 * Handler de autorização de teste: guarda cada evento recebido e responde o
 * que o teste mandar (`$respond`), demorando `$sleepMs` antes. Sem
 * `$respond`, aprova com o id do evento no corpo.
 */
final class RecordingAuthorizationHandler implements InboundAuthorizationHandler
{
    /**
     * @var list<InboundEvent>
     */
    public static array $events = [];

    /**
     * @var (Closure(InboundEvent): AuthorizationResponse)|null
     */
    public static ?Closure $respond = null;

    public static int $sleepMs = 0;

    public static function reset(): void
    {
        self::$events = [];
        self::$respond = null;
        self::$sleepMs = 0;
    }

    public function authorize(InboundEvent $event): AuthorizationResponse
    {
        self::$events[] = $event;

        if (self::$sleepMs > 0) {
            usleep(self::$sleepMs * 1000);
        }

        return self::$respond === null
            ? new AuthorizationResponse(200, ['status' => 'APPROVED', 'id' => $event->id])
            : (self::$respond)($event);
    }
}
