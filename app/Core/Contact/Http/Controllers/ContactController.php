<?php

declare(strict_types=1);

namespace App\Core\Contact\Http\Controllers;

use App\Core\Contact\Http\Requests\ContactRequest;
use App\Core\Contact\Mail\ContactMessageMail;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Formulário de contato da landing (público, sem conta).
 *
 * Anti-spam: honeypot (bots preenchem o campo invisível "website" → sucesso
 * FALSO, sem envio — não dá sinal de que foi detectado) + throttle:sensitive
 * na rota + validação server-side (ContactRequest). O e-mail é enfileirado
 * para PLATFORM_CONTACT_EMAIL (em dev, visível no Mailpit).
 */
final class ContactController extends Controller
{
    public function store(ContactRequest $request): RedirectResponse
    {
        /** @var array{name: string, email: string, subject: string, message: string, website?: ?string} $data */
        $data = $request->validated();

        // Honeypot preenchido = bot: finge sucesso e não envia nada.
        if (! empty($data['website'])) {
            Log::info('contact.honeypot', ['ip' => $request->ip()]);

            return back()->with('contact_status', __('contact.sent'));
        }

        $recipient = platform()->contactEmail;

        if ($recipient !== null) {
            Mail::to($recipient)->queue(new ContactMessageMail(
                senderName: $data['name'],
                senderEmail: $data['email'],
                subjectKey: $data['subject'],
                messageText: $data['message'],
            ));
        } else {
            // Sem destinatário configurado, a mensagem se perderia — registrar.
            Log::warning('contact.no_recipient', ['subject' => $data['subject']]);
        }

        return back()->with('contact_status', __('contact.sent'));
    }
}
