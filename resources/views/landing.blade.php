<x-layouts.landing :title="platform()->name" :force-dark="true">
    {{-- Hero --}}
    <section class="mx-auto max-w-6xl px-4 pb-20 pt-16 text-center sm:pt-24">
        <h1 class="mx-auto max-w-3xl font-display text-4xl font-bold tracking-[-0.03em] text-balance sm:text-5xl">
            {{ __('landing.hero.title') }}
        </h1>
        <p class="mx-auto mt-6 max-w-2xl text-lg text-gray-400">
            {{ __('landing.hero.subtitle') }}
        </p>
        <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
            <x-button :href="route('ui.showcase')" size="lg">{{ __('landing.hero.cta_components') }}</x-button>
            <x-button :href="route('login')" variant="secondary" size="lg">{{ __('landing.hero.cta_demo') }}</x-button>
            <x-button :href="route('register')" variant="ghost" size="lg">{{ __('landing.hero.cta_register') }}</x-button>
        </div>

        {{-- Screenshot real do painel em browser frame CSS --}}
        <div class="mx-auto mt-16 max-w-4xl overflow-hidden rounded-xl border border-gray-800 bg-gray-900 text-left shadow-2xl shadow-black/40" data-reveal>
            <div class="flex items-center gap-2 border-b border-gray-800 px-4 py-3">
                <span class="h-3 w-3 rounded-full bg-red-500/80"></span>
                <span class="h-3 w-3 rounded-full bg-yellow-500/80"></span>
                <span class="h-3 w-3 rounded-full bg-green-500/80"></span>
                <span class="ml-3 flex-1 rounded-md bg-gray-800 px-3 py-1 text-xs text-gray-500">{{ platform()->officialUrl }}/dashboard</span>
            </div>
            <img
                src="{{ asset('img/landing/dashboard.png') }}"
                alt="{{ __('landing.hero.screenshot_alt') }}"
                class="block w-full"
                width="1280"
                height="470"
                loading="lazy"
                decoding="async"
            >
        </div>
    </section>

    {{-- Barra de stack --}}
    <section id="stack" class="scroll-mt-16 border-y border-gray-800 bg-gray-900/50">
        <div class="mx-auto max-w-6xl px-4 py-12" data-reveal>
            <h2 class="text-center text-sm font-semibold uppercase tracking-widest text-gray-500">{{ __('landing.stack.heading') }}</h2>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-2">
                @foreach (__('landing.stack.items') as $tech)
                    <x-badge class="border border-gray-800 px-3 py-1 text-sm">{{ $tech }}</x-badge>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Horas economizadas --}}
    <section id="horas" class="mx-auto max-w-6xl scroll-mt-16 px-4 py-20">
        <div class="mx-auto max-w-2xl text-center" data-reveal>
            <h2 class="font-display text-3xl font-bold tracking-[-0.02em]">{{ __('landing.hours.heading') }}</h2>
            <p class="mt-3 text-balance text-gray-400">{{ __('landing.hours.subtitle') }}</p>
        </div>
        <div class="mx-auto mt-10 max-w-3xl overflow-hidden rounded-xl border border-gray-800" data-reveal>
            <ul class="divide-y divide-gray-800">
                @foreach (__('landing.hours.items') as $item)
                    <li class="flex items-center justify-between gap-4 bg-gray-900/40 px-5 py-3">
                        <span class="text-sm text-gray-300">{{ $item['task'] }}</span>
                        <x-badge color="blue" class="shrink-0">{{ $item['hours'] }}h</x-badge>
                    </li>
                @endforeach
            </ul>
            <div class="flex items-center justify-between gap-4 border-t border-gray-700 bg-gray-900 px-5 py-4">
                <span class="font-semibold">{{ __('landing.hours.total_label') }}</span>
                <x-badge color="green" class="px-3 py-1 text-sm">{{ __('landing.hours.total_value', ['hours' => array_sum(array_column(__('landing.hours.items'), 'hours'))]) }}</x-badge>
            </div>
        </div>
    </section>

    {{-- Grid de features --}}
    <section id="recursos" class="scroll-mt-16 border-y border-gray-800 bg-gray-900/50">
        <div class="mx-auto max-w-6xl px-4 py-20">
            <div class="mx-auto max-w-2xl text-center" data-reveal>
                <h2 class="font-display text-3xl font-bold tracking-[-0.02em]">{{ __('landing.features.heading') }}</h2>
                <p class="mt-3 text-balance text-gray-400">{{ __('landing.features.subtitle') }}</p>
            </div>
            <div class="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (__('landing.features.items') as $feature)
                    <div
                        class="rounded-xl border border-gray-800 bg-gray-950 p-6 transition-colors duration-200 ease-(--ease-out) hover:border-gray-700"
                        data-reveal
                        style="transition-delay: {{ $loop->index % 3 * 60 }}ms"
                    >
                        <div class="inline-flex rounded-lg bg-(--brand)/10 p-2.5 text-(--brand)">
                            <x-ui-icon :name="$feature['icon']" class="h-6 w-6" />
                        </div>
                        <h3 class="mt-4 font-display font-semibold tracking-[-0.01em]">{{ $feature['title'] }}</h3>
                        <p class="mt-2 text-sm text-gray-400">{{ $feature['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- CTA final com glow radial da marca --}}
    <section class="relative overflow-hidden">
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-0"
            style="background: radial-gradient(ellipse 55% 60% at 50% 45%, color-mix(in oklab, var(--brand) 16%, transparent), transparent)"
        ></div>
        <div class="relative mx-auto max-w-6xl px-4 py-24 text-center">
            <div data-reveal>
                <h2 class="font-display text-3xl font-bold tracking-[-0.02em]">{{ __('landing.cta.heading') }}</h2>
                <p class="mx-auto mt-3 max-w-xl text-gray-400">{{ __('landing.cta.subtitle') }}</p>
            </div>

            {{-- Comando de instalação copiável (o quickstart real do README) --}}
            <div class="mx-auto mt-8 max-w-2xl" data-reveal>
                <p class="text-sm text-gray-500">{{ __('landing.cta.install_label') }}</p>
                <div class="mt-3 flex items-center gap-3 rounded-xl border border-gray-800 bg-gray-950/80 py-3 pl-4 pr-2 text-left">
                    <x-ui-icon name="command-line" class="h-5 w-5 shrink-0 text-(--brand)" />
                    <code class="min-w-0 flex-1 overflow-x-auto whitespace-nowrap text-sm text-gray-300">{{ __('landing.cta.install_command') }}</code>
                    <button
                        type="button"
                        data-copy="{{ __('landing.cta.install_command') }}"
                        data-copied-text="{{ __('landing.cta.copied') }}"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-gray-700 px-3 py-1.5 text-sm text-gray-300 transition-[transform,background-color,border-color,color] duration-150 ease-(--ease-out) hover:border-gray-600 hover:bg-gray-800 active:scale-[0.97] motion-reduce:transition-none motion-reduce:active:scale-100"
                    >
                        <x-ui-icon name="clipboard-document" class="h-4 w-4" />
                        <span data-copy-label>{{ __('landing.cta.copy') }}</span>
                    </button>
                </div>
            </div>

            <div class="mt-8 flex flex-wrap items-center justify-center gap-3" data-reveal>
                <x-button :href="route('register')" size="lg">{{ __('landing.cta.register') }}</x-button>
                <x-button :href="route('login')" variant="secondary" size="lg">{{ __('landing.cta.demo') }}</x-button>
            </div>
        </div>
    </section>
</x-layouts.landing>
