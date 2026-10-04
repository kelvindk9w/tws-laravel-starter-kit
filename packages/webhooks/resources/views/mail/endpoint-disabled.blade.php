{{-- Aviso de endpoint de webhook desativado por falhas seguidas.
     Só o nome, o HOST do destino e a quantidade de falhas — nunca o segredo,
     a URL inteira ou o conteúdo de um evento. --}}
<x-email::layouts.kit
    :title="__('webhooks.mail.endpoint_disabled.subject', ['platform' => platform()->name])"
    :preheader="__('webhooks.mail.endpoint_disabled.preheader', ['failures' => $failures])"
>
    <x-email::heading>{{ __('webhooks.mail.endpoint_disabled.heading') }}</x-email::heading>

    <x-email::text>{{ __('webhooks.mail.endpoint_disabled.intro', ['failures' => $failures]) }}</x-email::text>

    <x-email::panel>
        <x-email::field :label="__('webhooks.mail.endpoint_disabled.account_label')" :value="$accountName" />
        <x-email::field :label="__('webhooks.mail.endpoint_disabled.name_label')" :value="$endpointName" />
        <x-email::field :label="__('webhooks.mail.endpoint_disabled.host_label')" :value="$endpointHost" mono last />
    </x-email::panel>

    <x-email::rule :space="24" />

    <x-email::text>{{ __('webhooks.mail.endpoint_disabled.action') }}</x-email::text>

    <x-email::button :url="$panelUrl">{{ __('webhooks.mail.endpoint_disabled.cta') }}</x-email::button>
</x-email::layouts.kit>
