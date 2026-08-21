<x-layouts.landing :title="platform()->name">
    {{-- Hero --}}
    <section class="mx-auto max-w-6xl px-4 pb-20 pt-16 text-center sm:pt-24">
        <h1 class="mx-auto max-w-3xl text-4xl font-bold tracking-tight sm:text-5xl">
            {{ __('landing.hero.title') }}
        </h1>
        <p class="mx-auto mt-6 max-w-2xl text-lg text-gray-400">
            {{ __('landing.hero.subtitle') }}
        </p>
        <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
            <x-button :href="route('ui.showcase')" size="lg">{{ __('landing.hero.cta_components') }}</x-button>
            <x-button :href="route('register')" variant="secondary" size="lg">{{ __('landing.hero.cta_register') }}</x-button>
        </div>

        {{-- Mockup do painel em browser frame (CSS puro, sem imagem externa) --}}
        <div class="mx-auto mt-16 max-w-3xl overflow-hidden rounded-xl border border-gray-800 bg-gray-900 text-left shadow-2xl">
            <div class="flex items-center gap-2 border-b border-gray-800 px-4 py-3">
                <span class="h-3 w-3 rounded-full bg-red-500/80"></span>
                <span class="h-3 w-3 rounded-full bg-yellow-500/80"></span>
                <span class="h-3 w-3 rounded-full bg-green-500/80"></span>
                <span class="ml-3 flex-1 rounded-md bg-gray-800 px-3 py-1 text-xs text-gray-500">{{ platform()->officialUrl }}/dashboard</span>
            </div>
            <div class="space-y-3 p-5">
                <div class="flex items-center gap-2">
                    <span class="inline-block h-6 w-6 rounded-md bg-(--brand)"></span>
                    <span class="text-sm font-semibold">{{ platform()->name }} · {{ __('landing.hero.mockup_title') }}</span>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    @foreach ([__('landing.hero.mockup_row_1'), __('landing.hero.mockup_row_2'), __('landing.hero.mockup_row_3')] as $metric)
                        <div class="rounded-lg border border-gray-800 bg-gray-950 p-4">
                            <p class="text-xs text-gray-500">{{ $metric }}</p>
                            <div class="mt-2 h-2 w-3/4 rounded bg-gray-800"></div>
                            <div class="mt-2 h-2 w-1/2 rounded bg-gray-800"></div>
                        </div>
                    @endforeach
                </div>
                <div class="rounded-lg border border-gray-800 bg-gray-950 p-4">
                    <div class="h-2 w-full rounded bg-gray-800"></div>
                    <div class="mt-2 h-2 w-5/6 rounded bg-gray-800"></div>
                    <div class="mt-2 h-2 w-2/3 rounded bg-gray-800"></div>
                </div>
            </div>
        </div>
    </section>

    {{-- Barra de stack --}}
    <section id="stack" class="border-y border-gray-800 bg-gray-900/50">
        <div class="mx-auto max-w-6xl px-4 py-10">
            <h2 class="text-center text-sm font-semibold uppercase tracking-widest text-gray-500">{{ __('landing.stack.heading') }}</h2>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-2">
                @foreach (__('landing.stack.items') as $tech)
                    <x-badge class="border border-gray-800 px-3 py-1 text-sm">{{ $tech }}</x-badge>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Horas economizadas --}}
    <section class="mx-auto max-w-6xl px-4 py-20">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-bold">{{ __('landing.hours.heading') }}</h2>
            <p class="mt-3 text-gray-400">{{ __('landing.hours.subtitle') }}</p>
        </div>
        <div class="mx-auto mt-10 max-w-3xl overflow-hidden rounded-xl border border-gray-800">
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
    <section id="recursos" class="border-t border-gray-800 bg-gray-900/50">
        <div class="mx-auto max-w-6xl px-4 py-20">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-3xl font-bold">{{ __('landing.features.heading') }}</h2>
                <p class="mt-3 text-gray-400">{{ __('landing.features.subtitle') }}</p>
            </div>
            <div class="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (__('landing.features.items') as $feature)
                    <div class="rounded-xl border border-gray-800 bg-gray-950 p-6">
                        <div class="inline-flex rounded-lg bg-(--brand)/10 p-2.5 text-(--brand)">
                            <x-ui-icon :name="$feature['icon']" class="h-6 w-6" />
                        </div>
                        <h3 class="mt-4 font-semibold">{{ $feature['title'] }}</h3>
                        <p class="mt-2 text-sm text-gray-400">{{ $feature['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- CTA final --}}
    <section class="mx-auto max-w-6xl px-4 py-20 text-center">
        <h2 class="text-3xl font-bold">{{ __('landing.cta.heading') }}</h2>
        <p class="mx-auto mt-3 max-w-xl text-gray-400">{{ __('landing.cta.subtitle') }}</p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <x-button :href="route('register')" size="lg">{{ __('landing.cta.register') }}</x-button>
            <x-button :href="route('login')" variant="ghost" size="lg">{{ __('landing.cta.login') }}</x-button>
        </div>
    </section>
</x-layouts.landing>
