<?php

declare(strict_types=1);

use App\Core\Contact\Mail\ContactMessageMail;
use App\Core\Support\Platform;
use Illuminate\Support\Facades\Mail;

// Formulário de contato da landing: validação server-side, honeypot,
// rate limit (throttle:sensitive) e e-mail enfileirado (Mailpit em dev).

function validContact(array $overrides = []): array
{
    return array_merge([
        'name' => 'Maria Silva',
        'email' => 'maria@example.com',
        'subject' => 'suggestion',
        'message' => 'Adorei o kit, queria sugerir um componente de tabela.',
    ], $overrides);
}

it('envia a mensagem por fila para o e-mail de contato configurado', function () {
    Mail::fake();
    config()->set('platform.contact_email', 'contato@example.com');
    // Platform é singleton resolvido no boot (Filament provider) — re-resolve.
    app()->forgetInstance(Platform::class);

    $this->post(route('contact.store'), validContact())
        ->assertRedirect()
        ->assertSessionHas('contact_status', __('contact.sent'));

    // replyTo é montado no envelope() (aplicado só no envio real) — com
    // Mail::fake, asserção via propriedades do mailable.
    Mail::assertQueued(
        ContactMessageMail::class,
        fn (ContactMessageMail $mail): bool => $mail->hasTo('contato@example.com')
            && $mail->senderEmail === 'maria@example.com'
            && $mail->subjectKey === 'suggestion',
    );
});

it('honeypot preenchido = bot: sucesso falso e NENHUM e-mail', function () {
    Mail::fake();

    $this->post(route('contact.store'), validContact(['website' => 'https://spam.example']))
        ->assertRedirect()
        ->assertSessionHas('contact_status', __('contact.sent'));

    Mail::assertNothingQueued();
});

it('valida os campos obrigatórios e o assunto na whitelist', function () {
    Mail::fake();

    $this->post(route('contact.store'), [])
        ->assertSessionHasErrors(['name', 'email', 'subject', 'message']);

    $this->post(route('contact.store'), validContact(['subject' => 'hack', 'message' => 'curta']))
        ->assertSessionHasErrors(['subject', 'message']);

    Mail::assertNothingQueued();
});

it('respeita o rate limit de rotas sensíveis', function () {
    Mail::fake();

    $max = (int) config('security.rate_limit.sensitive', 5);

    for ($i = 0; $i < $max; $i++) {
        $this->post(route('contact.store'), validContact())->assertRedirect();
    }

    $this->post(route('contact.store'), validContact())->assertTooManyRequests();
});

it('sem e-mail de contato configurado, registra aviso e responde sucesso', function () {
    Mail::fake();
    config()->set('platform.contact_email', null);
    app()->forgetInstance(Platform::class);

    $this->post(route('contact.store'), validContact())
        ->assertRedirect()
        ->assertSessionHas('contact_status');

    Mail::assertNothingQueued();
});

it('feedback de sucesso aparece como toast do kit na landing', function () {
    $this->withSession(['contact_status' => __('contact.sent')])
        ->get('/')
        ->assertOk()
        ->assertSee('data-toast', false)
        ->assertSee(__('contact.sent'));
});
