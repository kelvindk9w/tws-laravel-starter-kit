{{-- CONTATO — o único formulário público do kit, e o motivo de ele estar aqui:
     ele é FUNCIONAL. Honeypot, validação server-side, rate limit de rota
     sensível, e-mail em fila e a submissão registrada no super admin. Vender
     "formulários prontos" e não ter um formulário na própria vitrine seria a
     pior demonstração possível.

     Veio da landing anterior quando ela saiu de cena, sem mudar uma regra: os
     mesmos componentes do kit (<x-input>, <x-select>, <x-textarea>), as mesmas
     strings de lang/*/contact.php e a mesma rota `contact.store`. O toast de
     sucesso é o <x-flash-toast> do produto, no fim do documento. --}}
<section id="contato" class="scroll-mt-24 bg-surface pb-24 sm:pb-32">
    <div class="mx-auto max-w-6xl px-4">
        <div class="sky-rise mx-auto max-w-2xl text-center">
            <h2 class="sky-title text-gray-900 dark:text-gray-50" data-sky-text>{{ __('contact.heading') }}</h2>
            <p class="sky-lede mx-auto mt-4" data-sky-text>{{ __('contact.subtitle') }}</p>
        </div>

        <x-card class="sky-rise mx-auto mt-10 max-w-2xl">
            <form method="POST" action="{{ route('contact.store') }}" class="space-y-4">
                @csrf

                {{-- Resumo conforme config/ui.php → error_display (inline =
                     padrão: o erro aparece junto ao campo, via field_error). --}}
                <x-form-errors />

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input :label="__('contact.form.name')" name="name" :value="old('name')" :placeholder="__('contact.form.name_placeholder')" :error="field_error('name')" required maxlength="120" />
                    <x-input :label="__('contact.form.email')" name="email" type="email" :value="old('email')" :placeholder="__('contact.form.email_placeholder')" :error="field_error('email')" required />
                </div>

                <x-select :label="__('contact.form.subject')" name="subject" :error="field_error('subject')" required>
                    @foreach (['suggestion', 'complaint', 'other'] as $subjectKey)
                        <option value="{{ $subjectKey }}" @selected(old('subject') === $subjectKey)>{{ __("contact.subjects.{$subjectKey}") }}</option>
                    @endforeach
                </x-select>

                <x-textarea :label="__('contact.form.message')" name="message" :placeholder="__('contact.form.message_placeholder')" :error="field_error('message')" required minlength="10" maxlength="5000">{{ old('message') }}</x-textarea>

                {{-- Honeypot anti-spam: invisível para humanos (CSS), fora do
                     tab order; bots que o preenchem recebem sucesso falso. --}}
                <div class="hidden" aria-hidden="true">
                    <label for="website">{{ __('contact.form.honeypot_label') }}</label>
                    <input id="website" type="text" name="website" tabindex="-1" autocomplete="off">
                </div>

                <x-button type="submit">{{ __('contact.form.submit') }}</x-button>
            </form>
        </x-card>
    </div>
</section>
