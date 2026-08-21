<div>
    @if ($sent)
        <x-alert type="success" :title="__('contact.form.sent_title')">{{ __('contact.sent') }}</x-alert>
        <x-button variant="outline" size="sm" class="mt-4" wire:click="$set('sent', false)">{{ __('contact.form.send_another') }}</x-button>
    @else
        {{-- novalidate: quem valida é o servidor (wire:submit); a validação
             nativa do browser barraria a demonstração. --}}
        <form wire:submit="send" class="space-y-4" novalidate>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input :label="__('contact.form.name')" name="name" wire:model="name" :placeholder="__('contact.form.name_placeholder')" :error="$errors->first('name')" required maxlength="120" />
                <x-input :label="__('contact.form.email')" name="email" type="email" wire:model="email" :placeholder="__('contact.form.email_placeholder')" :error="$errors->first('email')" required />
            </div>

            <x-select :label="__('contact.form.subject')" name="subject" wire:model="subject" :error="$errors->first('subject')" required>
                @foreach (['suggestion', 'complaint', 'other'] as $subjectKey)
                    <option value="{{ $subjectKey }}">{{ __("contact.subjects.{$subjectKey}") }}</option>
                @endforeach
            </x-select>

            <x-textarea :label="__('contact.form.message')" name="message" wire:model="message" :placeholder="__('contact.form.message_placeholder')" :error="$errors->first('message')" required minlength="10" maxlength="5000" />

            {{-- Honeypot anti-spam (mesmo do form clássico da landing). --}}
            <div class="hidden" aria-hidden="true">
                <label for="website">{{ __('contact.form.honeypot_label') }}</label>
                <input id="website" type="text" name="website" wire:model="website" tabindex="-1" autocomplete="off">
            </div>

            <x-button type="submit" wire:loading.attr="disabled" wire:target="send">
                <x-spinner size="sm" class="text-white" wire:loading wire:target="send" />
                {{ __('contact.form.submit') }}
            </x-button>
        </form>
    @endif
</div>
