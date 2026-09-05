{{-- Código de verificação (2FA por e-mail — ADR-006). Um único assunto:
     o código. Nada de botão, nada de link — quem abre este e-mail já está
     com o formulário aberto do outro lado. --}}
<x-email::layouts.kit
    :title="__('mail.verification_code.subject', ['platform' => platform()->name])"
    :preheader="__('mail.verification_code.preheader', ['minutes' => $expiresInMinutes])"
>
    <x-email::heading>{{ __('mail.verification_code.heading') }}</x-email::heading>

    <x-email::text>{{ __('mail.verification_code.intro') }}</x-email::text>

    <x-email::code :code="$code" />

    <x-email::text>{{ __('mail.verification_code.expires', ['minutes' => $expiresInMinutes]) }}</x-email::text>

    <x-email::notice>{{ __('mail.verification_code.ignore') }}</x-email::notice>
</x-email::layouts.kit>
