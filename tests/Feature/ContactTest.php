<?php

declare(strict_types=1);

use App\Core\Contact\Mail\ContactMessageMail;
use App\Core\Localization\Middleware\SetLocale;
use App\Core\Support\Platform;
use Illuminate\Support\Facades\Mail;

// Formulário de contato da landing (POST /contato): validação server-side,
// honeypot anti-spam, rate limit e envio enfileirado (Mailpit em dev).

/**
 * @return array<string, string>
 */
function validContactPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Maria Silva',
        'email' => 'maria@example.com',
        'subject' => 'suggestion',
        'message' => 'Adorei o kit, seria ótimo ter um componente de tabela.',
    ], $overrides);
}

it('envia a mensagem enfileirada para o e-mail de contato configurado', function () {
    config()->set('platform.contact_email', 'contato@example.com');
    app()->forgetInstance(Platform::class);

    Mail::fake();

    $this->post(route('contact.store'), validContactPayload())
        ->assertRedirect()
        ->assertSessionHas('contact_status', __('contact.sent'));

    Mail::assertQueued(ContactMessageMail::class, function (ContactMessageMail $mail): bool {
        return $mail->hasTo('contato@example.com')
            && $mail->hasReplyTo('maria@example.com')
            && $mail->senderName === 'Maria Silva'
            && $mail->subjectKey === 'suggestion';
    });
});

it('honeypot preenchido (bot) finge sucesso e NÃO envia e-mail', function () {
    Mail::fake();

    $this->post(route('contact.store'), validContactPayload(['website' => 'https://spam.example']))
        ->assertRedirect()
        ->assertSessionHas('contact_status', __('contact.sent'));

    Mail::assertNothingQueued();
});

it('valida os campos obrigatórios e o assunto permitido', function () {
    Mail::fake();

    $this->post(route('contact.store'), [])
        ->assertSessionHasErrors(['name', 'email', 'subject', 'message']);

    $this->post(route('contact.store'), validContactPayload([
        'email' => 'nao-e-email',
        'subject' => 'hack',
        'message' => 'curta',
    ]))->assertSessionHasErrors(['email', 'subject', 'message']);

    Mail::assertNothingQueued();
});

it('tem rate limit de rota sensível (5/min por IP)', function () {
    Mail::fake();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('contact.store'), validContactPayload())->assertRedirect();
    }

    $this->post(route('contact.store'), validContactPayload())
        ->assertTooManyRequests();
});

it('respeita o locale do visitante (cookie) na mensagem de retorno', function () {
    Mail::fake();

    $this->withCookie(SetLocale::COOKIE, 'en')
        ->post(route('contact.store'), validContactPayload())
        ->assertSessionHas('contact_status', __('contact.sent', locale: 'en'));
});
