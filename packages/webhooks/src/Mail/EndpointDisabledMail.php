<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Mail;

use Illuminate\Support\Facades\Route;
use Twstec\Kit\Foundation\Mail\KitMailable;

/**
 * AVISO: o endpoint foi desativado depois de N tentativas falhas seguidas.
 *
 * Para o dono e os administradores da conta. SEMPRE enfileirado e com o job
 * cifrado (KitMailable). Leva só o nome do endpoint, o HOST do destino
 * (nunca a URL inteira) e a quantidade de falhas — nunca o segredo nem o
 * conteúdo de um evento.
 *
 * O corpo é a view do pacote (`webhooks::mail.endpoint-disabled`), com os
 * componentes de e-mail do foundation; os textos são do pacote
 * (`webhooks.mail.endpoint_disabled.*`), nos três idiomas.
 */
final class EndpointDisabledMail extends KitMailable
{
    public function __construct(
        public readonly string $endpointName,
        public readonly string $endpointHost,
        public readonly int $failures,
        public readonly string $accountName,
    ) {}

    protected function subjectLine(): string
    {
        return __('webhooks.mail.endpoint_disabled.subject', ['platform' => platform()->name]);
    }

    protected function messageView(): string
    {
        return 'webhooks::mail.endpoint-disabled';
    }

    /**
     * @return array<string, mixed>
     */
    protected function messageData(): array
    {
        return [
            // A tela de webhooks do starter, quando existe (o pacote não tem telas).
            'panelUrl' => Route::has('panel.webhooks') ? route('panel.webhooks') : url('/'),
        ];
    }
}
