<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Core\Showcase\Models\FormSubmission;
use App\Core\Showcase\Support\FormSubmissionGuard;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Formulário demo em versão Livewire (AJAX) — exemplo funcional do padrão
 * "Livewire" na seção "Padrões de formulário" do showcase /ui.
 *
 * Campos: apelido, assunto e mensagem (+ honeypot invisível "website").
 * A submissão grava DE VERDADE em form_submissions (origem livewire) e
 * aparece no super admin — inclusive as tentativas de ataque bloqueadas
 * pela camada do formulário (vitrine de segurança — FormSubmissionGuard:
 * payload inerte, sucesso falso, badge vermelho no topo da listagem).
 * Este componente é autodefendido (config security.validation
 * .delegated_components): o middleware global delega a detecção para cá.
 *
 * Diferença do padrão clássico: wire:submit valida server-side sem reload
 * e o estado é preservado automaticamente (não existe old() no Livewire).
 */
final class ContactForm extends Component
{
    public string $nickname = '';

    public string $subject = 'suggestion';

    public string $message = '';

    /** Honeypot anti-spam (mesmo campo invisível do form clássico). */
    public string $website = '';

    public bool $sent = false;

    public function send(FormSubmissionGuard $guard): void
    {
        /** @var array{nickname: string, subject: string, message: string, website?: ?string} $validated */
        $validated = $this->validate([
            'nickname' => ['required', 'string', 'max:120'],
            'subject' => ['required', Rule::in(['suggestion', 'complaint', 'other'])],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            'website' => ['nullable', 'string', 'max:255'],
        ], [], [
            'nickname' => __('showcase.form_patterns.demo_nickname'),
            'subject' => __('showcase.form_patterns.demo_subject'),
            'message' => __('showcase.form_patterns.demo_message'),
        ]);

        // Sucesso falso também para tentativas bloqueadas (honeypot/ataque) —
        // não damos sinal de que a tentativa foi detectada.
        $guard->submit(
            origin: FormSubmission::ORIGIN_LIVEWIRE,
            nickname: $validated['nickname'],
            subject: $validated['subject'],
            message: $validated['message'],
            honeypot: $validated['website'] ?? null,
        );

        $this->reset('nickname', 'subject', 'message', 'website');
        $this->sent = true;
    }

    public function render(): View
    {
        return view('livewire.contact-form');
    }
}
