<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Inbound\Http;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Webhooks\Inbound\InboundReceiver;

/**
 * `POST /<webhooks.inbound.path>/{source}` — a rota dos webhooks recebidos.
 * Sem sessão, sem CSRF, sem cookie (fora do grupo `web`), com limite por
 * fonte e IP (`throttle:webhooks-inbound`). O trabalho é do InboundReceiver.
 */
final class ReceiveInboundWebhook
{
    public const ROUTE = 'webhooks.inbound';

    public const LIMITER = 'webhooks-inbound';

    public function __invoke(Request $request, InboundReceiver $receiver, string $source): Response
    {
        return $receiver->receive($request, $source);
    }
}
