{{-- Mensagem do formulário de contato da landing → caixa do time. É o único
     e-mail do kit que vai para DENTRO da empresa: o "remetente" é o visitante
     (replyTo), e o corpo preserva as quebras de linha do que ele escreveu. --}}
<x-email::layouts.kit
    :title="__('contact.mail.subject_line', ['platform' => platform()->name, 'subject' => $subjectLabel])"
    :preheader="__('mail.contact_message.preheader', ['name' => $senderName, 'subject' => $subjectLabel])"
>
    <x-email::heading>{{ __('mail.contact_message.heading') }}</x-email::heading>

    <x-email::text>{{ __('contact.mail.intro') }}</x-email::text>

    <x-email::panel>
        <x-email::field :label="__('contact.mail.from')" :value="$senderName.' <'.$senderEmail.'>'" />
        <x-email::field :label="__('contact.mail.subject_label')" :value="$subjectLabel" last />
    </x-email::panel>

    <x-email::rule :space="24" />

    <x-email::text muted>{{ __('mail.contact_message.message_label') }}</x-email::text>

    <x-email::panel preserve-lines>{{ $messageText }}</x-email::panel>

    <x-email::rule :space="24" />

    <x-email::text muted last>{{ __('mail.contact_message.reply_hint') }}</x-email::text>
</x-email::layouts.kit>
