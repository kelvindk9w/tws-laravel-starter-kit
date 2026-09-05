<x-layouts.landing :title="platform()->name">
    {{-- Hero --}}
    <section class="mx-auto max-w-6xl px-4 pb-20 pt-16 text-center sm:pt-24">
        <h1 class="mx-auto max-w-3xl font-display text-4xl font-bold tracking-[-0.03em] text-balance sm:text-5xl">
            {{ __('landing.hero.title') }}
        </h1>
        <p class="mx-auto mt-6 max-w-2xl text-lg text-gray-600 dark:text-gray-400">
            {{ __('landing.hero.subtitle') }}
        </p>
        {{-- UM primário (a conversão), UM secundário (a prova), e o resto
             como link de texto. Quatro botões lado a lado não são quatro
             opções: são nenhuma — e antes o CTA de conversão era justamente
             o único SEM forma de botão. --}}
        <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
            <x-button :href="route('register')" size="lg">{{ __('landing.hero.cta_register') }}</x-button>
            <x-button :href="route('login')" variant="secondary" size="lg">{{ __('landing.hero.cta_demo') }}</x-button>
        </div>
        <div class="mt-5 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm">
            <a href="{{ route('ui.showcase') }}" class="text-gray-600 underline decoration-gray-300 underline-offset-4 transition-colors duration-150 ease-(--ease-out) hover:text-gray-900 dark:text-gray-400 dark:decoration-gray-600 dark:hover:text-white">{{ __('landing.hero.cta_components') }}</a>
            @if (config('ui.demo_login.enabled'))
                <a href="{{ url('/admin') }}" class="text-gray-600 underline decoration-gray-300 underline-offset-4 transition-colors duration-150 ease-(--ease-out) hover:text-gray-900 dark:text-gray-400 dark:decoration-gray-600 dark:hover:text-white">{{ __('landing.hero.cta_admin_demo') }}</a>
            @endif
        </div>

        {{-- Screenshot real do painel em browser frame CSS --}}
        <div class="mx-auto mt-16 max-w-4xl overflow-hidden rounded-xl border border-gray-800 bg-gray-900 text-left shadow-2xl shadow-black/40" data-reveal>
            <div class="flex items-center gap-2 border-b border-gray-800 px-4 py-3">
                <span class="h-3 w-3 rounded-full bg-red-500/80"></span>
                <span class="h-3 w-3 rounded-full bg-yellow-500/80"></span>
                <span class="h-3 w-3 rounded-full bg-green-500/80"></span>
                <span class="ml-3 flex-1 truncate rounded-md bg-gray-800 px-3 py-1 text-xs text-gray-400">{{ platform()->officialUrl }}/dashboard</span>
            </div>
            <img
                src="{{ asset('img/landing/dashboard.png') }}"
                alt="{{ __('landing.hero.screenshot_alt') }}"
                class="block w-full"
                width="1280"
                height="700"
                loading="lazy"
                decoding="async"
            >
        </div>
    </section>

    {{-- Barra de stack --}}
    <section id="stack" class="scroll-mt-16 border-y border-border bg-surface-sunken">
        <div class="mx-auto max-w-6xl px-4 py-12" data-reveal>
            <h2 class="text-center text-sm font-semibold uppercase tracking-widest text-text-muted">{{ __('landing.stack.heading') }}</h2>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-2">
                @foreach (__('landing.stack.items') as $tech)
                    <x-badge class="px-3 py-1 text-sm">{{ $tech }}</x-badge>
                @endforeach
            </div>
            <p class="mx-auto mt-6 max-w-2xl text-center text-sm text-text-muted">{{ __('landing.stack.dev_note') }}</p>
        </div>
    </section>

    {{-- Horas economizadas — o argumento comercial mais forte da página.
         Era uma lista de 10 linhas em text-sm com pílulas azuis de 12px: a
         informação de maior valor renderizada com a MENOR ênfase. Agora o
         total é um número display e a lista é o detalhamento dele.
         As pílulas viraram cinzas: azul, no theme.css deste kit, é cor de
         STATUS (badge/alert info) — usá-lo como decoração de quantidade era
         a única cor saturada da página inteira, gasta com enfeite. --}}
    @php
        $hoursItems = __('landing.hours.items');
        $hoursTotal = array_sum(array_column($hoursItems, 'hours'));
    @endphp
    <section id="horas" class="mx-auto max-w-6xl scroll-mt-16 px-4 py-20">
        <div class="mx-auto max-w-2xl text-center" data-reveal>
            <h2 class="font-display text-3xl font-bold tracking-[-0.02em]">{{ __('landing.hours.heading') }}</h2>
            <p class="mt-3 text-balance text-gray-600 dark:text-gray-400">{{ __('landing.hours.subtitle') }}</p>
        </div>

        <div class="mx-auto mt-12 flex max-w-3xl flex-col items-center text-center" data-reveal>
            <p class="font-display text-display tabular-nums">{{ $hoursTotal }}</p>
            <p class="mt-2 text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('landing.hours.total_unit') }}</p>
            <p class="mt-2 max-w-md text-sm text-text-muted">{{ __('landing.hours.total_caption') }}</p>
        </div>

        <div class="mx-auto mt-10 max-w-3xl overflow-hidden rounded-xl border border-border" data-reveal>
            <ul class="divide-y divide-border">
                @foreach ($hoursItems as $item)
                    <li class="flex items-center justify-between gap-4 bg-surface px-5 py-3">
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['task'] }}</span>
                        <x-badge class="shrink-0 tabular-nums">{{ $item['hours'] }}h</x-badge>
                    </li>
                @endforeach
            </ul>
            <div class="flex items-center justify-between gap-4 border-t border-border-strong bg-surface-sunken px-5 py-4">
                <span class="font-semibold">{{ __('landing.hours.total_label') }}</span>
                <span class="font-display text-lg font-semibold tabular-nums">{{ __('landing.hours.total_value', ['hours' => $hoursTotal]) }}</span>
            </div>
        </div>
    </section>

    {{-- Grid de features --}}
    <section id="recursos" class="scroll-mt-16 border-y border-border bg-surface-sunken">
        <div class="mx-auto max-w-6xl px-4 py-20">
            <div class="mx-auto max-w-2xl text-center" data-reveal>
                <h2 class="font-display text-3xl font-bold tracking-[-0.02em]">{{ __('landing.features.heading') }}</h2>
                <p class="mt-3 text-balance text-gray-600 dark:text-gray-400">{{ __('landing.features.subtitle') }}</p>
            </div>
            <div class="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (__('landing.features.items') as $feature)
                    <div
                        class="rounded-xl border border-border bg-surface p-6 transition-colors duration-200 ease-(--ease-out) hover:border-border-strong"
                        data-reveal
                        style="transition-delay: {{ $loop->index % 3 * 60 }}ms"
                    >
                        <div class="inline-flex rounded-lg bg-brand/10 p-2.5 text-brand">
                            <x-ui-icon :name="$feature['icon']" class="h-6 w-6" />
                        </div>
                        <h3 class="mt-4 font-display font-semibold tracking-[-0.01em]">{{ $feature['title'] }}</h3>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $feature['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Contato (formulário funcional: honeypot + rate limit + e-mail em fila) --}}
    <section id="contato" class="mx-auto max-w-6xl scroll-mt-16 px-4 py-20">
        <div class="mx-auto max-w-2xl text-center" data-reveal>
            <h2 class="font-display text-3xl font-bold tracking-[-0.02em]">{{ __('contact.heading') }}</h2>
            <p class="mt-3 text-balance text-gray-600 dark:text-gray-400">{{ __('contact.subtitle') }}</p>
        </div>

        <x-card class="mx-auto mt-10 max-w-2xl">
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
    </section>

    {{-- CTA final com glow radial da marca --}}
    <section class="relative overflow-hidden">
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-0"
            style="background: radial-gradient(ellipse 55% 60% at 50% 45%, color-mix(in oklab, var(--color-brand) 16%, transparent), transparent)"
        ></div>
        <div class="relative mx-auto max-w-6xl px-4 py-24 text-center">
            <div data-reveal>
                <h2 class="font-display text-3xl font-bold tracking-[-0.02em]">{{ __('landing.cta.heading') }}</h2>
                <p class="mx-auto mt-3 max-w-xl text-gray-600 dark:text-gray-400">{{ __('landing.cta.subtitle') }}</p>
            </div>

            <div class="mt-8 flex flex-wrap items-center justify-center gap-3" data-reveal>
                <x-button :href="route('register')" size="lg">{{ __('landing.cta.register') }}</x-button>
                <x-button :href="route('login')" variant="secondary" size="lg">{{ __('landing.cta.demo') }}</x-button>
            </div>

            {{-- Repositório público do kit (config via platform()/env): link
                 de texto, não um terceiro botão competindo com a conversão. --}}
            @if (platform()->repoUrl)
                <div class="mt-6" data-reveal>
                    <a href="{{ platform()->repoUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 text-sm text-gray-600 underline decoration-gray-300 underline-offset-4 transition-colors duration-150 ease-(--ease-out) hover:text-gray-900 dark:text-gray-400 dark:decoration-gray-600 dark:hover:text-white">
                        <x-ui-icon name="code-bracket" class="h-4 w-4" />
                        {{ __('landing.cta.repo') }}
                    </a>
                </div>
            @endif
        </div>
    </section>
</x-layouts.landing>
