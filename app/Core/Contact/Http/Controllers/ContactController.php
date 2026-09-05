<?php

declare(strict_types=1);

namespace App\Core\Contact\Http\Controllers;

use App\Core\Contact\Http\Requests\ContactRequest;
use App\Core\Contact\Mail\ContactMessageMail;
use App\Core\Showcase\Models\FormSubmission;
use App\Core\Showcase\Support\FormSubmissionGuard;
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
 *
 * Persistência (bug de QA #5): TODA mensagem também vira uma linha em
 * form_submissions com origem `contact`, pelo MESMO FormSubmissionGuard dos
 * forms demo do /ui — inclusive as bloqueadas (honeypot/ataque), que ficam
 * registradas para auditoria no /admin e NÃO geram e-mail. Sem isso o dono
 * só teria a caixa de entrada como trilha.
 */
final class ContactController extends Controller
{
    public function __construct(private readonly FormSubmissionGuard $guard) {}

    public function store(ContactRequest $request): RedirectResponse
    {
        /** @var array{name: string, email: string, subject: string, message: string, website?: ?string} $data */
        $data = $request->validated();

        // Mesma camada de formulário dos demos do /ui: detecta ataque/honeypot
        // e persiste a submissão (inerte) antes de qualquer envio.
        $submission = $this->guard->submit(
            origin: FormSubmission::ORIGIN_CONTACT,
            nickname: $data['name'],
            subject: $data['subject'],
            message: $data['message'],
            honeypot: $data['website'] ?? null,
            senderEmail: $data['email'],
        );

        // Bloqueada (bot ou ataque): sucesso FALSO, nada é enviado — a
        // tentativa já ficou registrada para auditoria.
        if ($submission->isBlocked()) {
            Log::info('contact.blocked', ['ip' => $request->ip(), 'attack_type' => $submission->attack_type]);

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
