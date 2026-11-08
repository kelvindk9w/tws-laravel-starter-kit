<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Tests\Fixtures;

use RuntimeException;
use Twstec\Kit\Webhooks\Inbound\Contracts\InboundWebhookHandler;
use Twstec\Kit\Webhooks\Inbound\InboundEvent;

/**
 * Handler de teste: guarda cada evento recebido e falha as primeiras
 * `$failures` vezes (para provar nova tentativa e falha de vez).
 */
final class RecordingInboundHandler implements InboundWebhookHandler
{
    /**
     * @var list<InboundEvent>
     */
    public static array $events = [];

    public static int $failures = 0;

    public static function reset(): void
    {
        self::$events = [];
        self::$failures = 0;
    }

    public function handle(InboundEvent $event): void
    {
        if (self::$failures > 0) {
            self::$failures--;

            throw new RuntimeException('falha simulada do handler com dado do evento: '.json_encode($event->payload));
        }

        self::$events[] = $event;
    }
}
