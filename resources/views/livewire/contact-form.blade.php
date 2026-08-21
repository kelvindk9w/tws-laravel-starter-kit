<div>
    @if ($sent)
        <x-alert type="success" :title="__('showcase.form_patterns.demo_sent_title')">{{ __('showcase.form_patterns.demo_sent') }}</x-alert>
        <x-button variant="outline" size="sm" class="mt-4" wire:click="$set('sent', false)">{{ __('showcase.form_patterns.demo_send_another') }}</x-button>
    @else
        {{-- novalidate: quem valida é o servidor (wire:submit); a validação
             nativa do browser barraria a demonstração. --}}
        <form wire:submit="send" class="space-y-4" novalidate>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input :label="__('showcase.form_patterns.demo_nickname')" name="nickname" wire:model="nickname" :placeholder="__('showcase.form_patterns.demo_nickname_placeholder')" :error="$errors->first('nickname')" required maxlength="120" />
                <x-select :label="__('showcase.form_patterns.demo_subject')" name="subject" wire:model="subject" :error="$errors->first('subject')" required>
                    @foreach (['suggestion', 'complaint', 'other'] as $subjectKey)
                        <option value="{{ $subjectKey }}">{{ __("contact.subjects.{$subjectKey}") }}</option>
                    @endforeach
                </x-select>
            </div>

            <x-textarea :label="__('showcase.form_patterns.demo_message')" name="message" wire:model="message" :placeholder="__('showcase.form_patterns.demo_message_placeholder')" :error="$errors->first('message')" required minlength="10" maxlength="2000" />

            {{-- Honeypot anti-spam (mesmo do form clássico): preenchido por
                 bots → bloqueio registrado (vitrine /admin), sucesso falso. --}}
            <div class="hidden" aria-hidden="true">
                <label for="website">{{ __('showcase.form_patterns.demo_honeypot_label') }}</label>
                <input id="website" type="text" name="website" wire:model="website" tabindex="-1" autocomplete="off">
            </div>

            <x-button type="submit" wire:loading.attr="disabled" wire:target="send">
                <x-spinner size="sm" class="text-brand-foreground" wire:loading wire:target="send" />
                {{ __('showcase.form_patterns.demo_submit_ajax') }}
            </x-button>
        </form>
    @endif
</div>
