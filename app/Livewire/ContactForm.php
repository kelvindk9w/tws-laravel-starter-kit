<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Core\Contact\Http\Requests\ContactRequest;
use App\Core\Contact\Mail\ContactMessageMail;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

/**
 * Formulário de contato em versão Livewire (AJAX) — exemplo funcional do
 * padrão "Livewire" na seção "Padrões de formulário" do showcase /ui.
 *
 * Reusa a MESMA regra do POST clássico da landing: validação do
 * ContactRequest, honeypot com sucesso falso e e-mail enfileirado para
 * PLATFORM_CONTACT_EMAIL. A diferença é o transporte: wire:submit valida
 * server-side sem reload e o estado é preservado automaticamente (não
 * existe old() no mundo Livewire).
 */
final class ContactForm extends Component
{
    public string $name = '';

    public string $email = '';

    public string $subject = 'suggestion';

    public string $message = '';

    /** Honeypot anti-spam (mesmo campo invisível do form clássico). */
    public string $website = '';

    public bool $sent = false;

    public function send(): void
    {
        $request = new ContactRequest;

        /** @var array{name: string, email: string, subject: string, message: string, website?: ?string} $validated */
        $validated = $this->validate($request->rules(), [], $request->attributes());

        // Honeypot preenchido = bot: finge sucesso e não envia nada.
        if (! empty($validated['website'])) {
            Log::info('contact.honeypot', ['ip' => request()->ip(), 'via' => 'livewire']);
            $this->reset('name', 'email', 'subject', 'message', 'website');
            $this->sent = true;

            return;
        }

        $recipient = platform()->contactEmail;

        if ($recipient !== null) {
            Mail::to($recipient)->queue(new ContactMessageMail(
                senderName: $validated['name'],
                senderEmail: $validated['email'],
                subjectKey: $validated['subject'],
                messageText: $validated['message'],
            ));
        } else {
            Log::warning('contact.no_recipient', ['subject' => $validated['subject']]);
        }

        $this->reset('name', 'email', 'subject', 'message', 'website');
        $this->sent = true;
    }

    public function render(): View
    {
        return view('livewire.contact-form');
    }
}
