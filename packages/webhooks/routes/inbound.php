<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Twstec\Kit\Webhooks\Inbound\Http\ReceiveInboundWebhook;
use Twstec\Kit\Webhooks\Inbound\InboundWebhooks;

// Webhooks RECEBIDOS (twstec/kit-webhooks): uma rota para todas as fontes,
// FORA do grupo `web` — sem sessão, sem cookie, sem CSRF —, com o limite por
// fonte e IP. A pilha global do kit vale (proxies confiáveis, limite de
// borda, filtro de ataques e trilha), e o caminho entra nos caminhos de
// corpo assinado: o corpo não é inspecionado nem gravado na trilha (ver
// WebhooksServiceProvider::registerInbound).
Route::post(InboundWebhooks::path().'/{source}', ReceiveInboundWebhook::class)
    ->where('source', '[a-z0-9][a-z0-9_-]{0,63}')
    ->middleware('throttle:'.ReceiveInboundWebhook::LIMITER)
    ->name(ReceiveInboundWebhook::ROUTE);
