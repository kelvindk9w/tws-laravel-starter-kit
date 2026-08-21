@php
    // Snippets copiáveis exibidos ao lado de cada variante. Ficam em PHP (e
    // não inline nos atributos) porque o compilador Blade tentaria compilar
    // as tags <x-…> dentro de um atributo de componente.
    $snip = [
        'btn' => '<x-button>…</x-button>',
        'btn_secondary' => '<x-button variant="secondary">…</x-button>',
        'btn_ghost' => '<x-button variant="ghost">…</x-button>',
        'btn_danger' => '<x-button variant="danger">…</x-button>',
        'btn_sm' => '<x-button size="sm">…</x-button>',
        'btn_lg' => '<x-button size="lg">…</x-button>',
        'btn_disabled' => '<x-button :disabled="true">…</x-button>',
        'btn_loading' => '<x-button :disabled="true"><x-spinner size="sm" /> …</x-button>',
        'btn_link' => '<x-button href="…">…</x-button>',
        'alert_success' => '<x-alert type="success" title="…">…</x-alert>',
        'alert_warning' => '<x-alert type="warning" title="…">…</x-alert>',
        'alert_error' => '<x-alert type="error" title="…">…</x-alert>',
        'alert_info' => '<x-alert type="info" title="…">…</x-alert>',
        'badge_green' => '<x-badge color="green">…</x-badge>',
        'badge_yellow' => '<x-badge color="yellow">…</x-badge>',
        'badge_red' => '<x-badge color="red">…</x-badge>',
        'badge_blue' => '<x-badge color="blue">…</x-badge>',
        'badge_brand' => '<x-badge color="brand">…</x-badge>',
        'badge' => '<x-badge>…</x-badge>',
        'input' => '<x-input label="…" name="demo_name" hint="…" />',
        'input_error' => '<x-input label="…" name="demo_email" :error="…" />',
        'input_disabled' => '<x-input label="…" name="demo_disabled" :disabled="true" />',
        'select' => '<x-select label="…" name="demo_plan">…</x-select>',
        'checkbox' => '<x-checkbox label="…" name="demo_terms" />',
        'toggle' => '<x-toggle label="…" name="demo_2fa" />',
        'card' => '<x-card title="…">…</x-card>',
        'card_footer' => '<x-card title="…">… <x-slot:footer>…</x-slot:footer> </x-card>',
        'modal' => '<x-button data-modal-open="meu-modal">…</x-button> + <x-modal id="meu-modal" title="…">…</x-modal>',
        'toast' => '<x-button data-toast-show="meu-toast">…</x-button> + <x-toast id="meu-toast">…</x-toast>',
        'empty_state' => '<x-empty-state title="…" description="…" icon="building-office">…</x-empty-state>',
        'spinner_sm' => '<x-spinner size="sm" />',
        'spinner' => '<x-spinner />',
        'spinner_lg' => '<x-spinner size="lg" />',
    ];
@endphp

<x-layouts.landing :title="__('showcase.title').' — '.platform()->name">
    <div class="mx-auto max-w-6xl px-4 py-12 lg:flex lg:gap-10">
        {{-- Sidebar de categorias (âncoras + scrollspy) --}}
        <aside class="mb-10 shrink-0 lg:mb-0 lg:w-56">
            <nav data-scrollspy class="flex flex-wrap gap-1 lg:sticky lg:top-20 lg:flex-col">
                @foreach (__('showcase.categories') as $anchor => $label)
                    <a href="#{{ $anchor }}" class="rounded-md px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">{{ $label }}</a>
                @endforeach
            </nav>
        </aside>

        <main class="min-w-0 flex-1">
            <header class="mb-12 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="font-display text-3xl font-bold tracking-[-0.02em] text-gray-900 dark:text-gray-100">{{ __('showcase.title') }}</h1>
                    <p class="mt-2 max-w-xl text-gray-600 dark:text-gray-400">{{ __('showcase.subtitle') }}</p>
                </div>

                {{-- Toggle claro/escuro: prova os dois temas dos componentes --}}
                <button
                    type="button"
                    data-theme-toggle
                    title="{{ __('showcase.theme.toggle') }}"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-600 transition-[transform,background-color,border-color,color] duration-150 ease-(--ease-out) hover:bg-gray-100 active:scale-[0.97] motion-reduce:transition-none motion-reduce:active:scale-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    <x-ui-icon name="sun" class="hidden h-4 w-4 dark:inline" />
                    <x-ui-icon name="moon" class="h-4 w-4 dark:hidden" />
                    <span class="hidden dark:inline">{{ __('showcase.theme.light') }}</span>
                    <span class="dark:hidden">{{ __('showcase.theme.dark') }}</span>
                </button>
            </header>

            {{-- Botões --}}
            <section id="buttons" class="mb-16 scroll-mt-24">
                <h2 class="mb-6 font-display text-xl font-semibold tracking-[-0.01em] text-gray-900 dark:text-gray-100">{{ __('showcase.categories.buttons') }}</h2>

                <p class="mb-3 text-sm text-gray-500">{{ __('showcase.buttons.variants') }}</p>
                <div class="mb-8 grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-4">
                    <div class="flex flex-col items-center gap-2"><x-button>{{ __('showcase.buttons.primary') }}</x-button><x-snippet :code="$snip['btn']" /></div>
                    <div class="flex flex-col items-center gap-2"><x-button variant="secondary">{{ __('showcase.buttons.secondary') }}</x-button><x-snippet :code="$snip['btn_secondary']" /></div>
                    <div class="flex flex-col items-center gap-2"><x-button variant="ghost">{{ __('showcase.buttons.ghost') }}</x-button><x-snippet :code="$snip['btn_ghost']" /></div>
                    <div class="flex flex-col items-center gap-2"><x-button variant="danger">{{ __('showcase.buttons.danger') }}</x-button><x-snippet :code="$snip['btn_danger']" /></div>
                </div>

                <p class="mb-3 text-sm text-gray-500">{{ __('showcase.buttons.sizes') }}</p>
                <div class="mb-8 grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-3">
                    <div class="flex flex-col items-center gap-2"><x-button size="sm">{{ __('showcase.buttons.small') }}</x-button><x-snippet :code="$snip['btn_sm']" /></div>
                    <div class="flex flex-col items-center gap-2"><x-button>{{ __('showcase.buttons.medium') }}</x-button><x-snippet :code="$snip['btn']" /></div>
                    <div class="flex flex-col items-center gap-2"><x-button size="lg">{{ __('showcase.buttons.large') }}</x-button><x-snippet :code="$snip['btn_lg']" /></div>
                </div>

                <p class="mb-3 text-sm text-gray-500">{{ __('showcase.buttons.states') }}</p>
                <div class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-3">
                    <div class="flex flex-col items-center gap-2"><x-button :disabled="true">{{ __('showcase.buttons.disabled') }}</x-button><x-snippet :code="$snip['btn_disabled']" /></div>
                    <div class="flex flex-col items-center gap-2"><x-button :disabled="true"><x-spinner size="sm" class="text-white" /> {{ __('showcase.buttons.loading') }}</x-button><x-snippet :code="$snip['btn_loading']" /></div>
                    <div class="flex flex-col items-center gap-2"><x-button href="#buttons" variant="secondary">{{ __('showcase.buttons.as_link') }}</x-button><x-snippet :code="$snip['btn_link']" /></div>
                </div>
            </section>

            {{-- Alertas --}}
            <section id="alerts" class="mb-16 scroll-mt-24">
                <h2 class="mb-6 font-display text-xl font-semibold tracking-[-0.01em] text-gray-900 dark:text-gray-100">{{ __('showcase.categories.alerts') }}</h2>
                <div class="space-y-4">
                    <div class="space-y-2"><x-alert type="success" :title="__('showcase.alerts.success_title')">{{ __('showcase.alerts.success') }}</x-alert><div><x-snippet :code="$snip['alert_success']" /></div></div>
                    <div class="space-y-2"><x-alert type="warning" :title="__('showcase.alerts.warning_title')">{{ __('showcase.alerts.warning') }}</x-alert><div><x-snippet :code="$snip['alert_warning']" /></div></div>
                    <div class="space-y-2"><x-alert type="error" :title="__('showcase.alerts.error_title')">{{ __('showcase.alerts.error') }}</x-alert><div><x-snippet :code="$snip['alert_error']" /></div></div>
                    <div class="space-y-2"><x-alert type="info" :title="__('showcase.alerts.info_title')">{{ __('showcase.alerts.info') }}</x-alert><div><x-snippet :code="$snip['alert_info']" /></div></div>
                </div>
            </section>

            {{-- Badges --}}
            <section id="badges" class="mb-16 scroll-mt-24">
                <h2 class="mb-6 font-display text-xl font-semibold tracking-[-0.01em] text-gray-900 dark:text-gray-100">{{ __('showcase.categories.badges') }}</h2>
                <div class="grid grid-cols-2 gap-x-4 gap-y-6 lg:grid-cols-3">
                    <div class="flex flex-col items-center gap-2"><x-badge color="green">{{ __('showcase.badges.active') }}</x-badge><x-snippet :code="$snip['badge_green']" /></div>
                    <div class="flex flex-col items-center gap-2"><x-badge color="yellow">{{ __('showcase.badges.pending') }}</x-badge><x-snippet :code="$snip['badge_yellow']" /></div>
                    <div class="flex flex-col items-center gap-2"><x-badge color="red">{{ __('showcase.badges.blocked') }}</x-badge><x-snippet :code="$snip['badge_red']" /></div>
                    <div class="flex flex-col items-center gap-2"><x-badge color="blue">{{ __('showcase.badges.beta') }}</x-badge><x-snippet :code="$snip['badge_blue']" /></div>
                    <div class="flex flex-col items-center gap-2"><x-badge color="brand">{{ __('showcase.badges.brand') }}</x-badge><x-snippet :code="$snip['badge_brand']" /></div>
                    <div class="flex flex-col items-center gap-2"><x-badge>{{ __('showcase.badges.neutral') }}</x-badge><x-snippet :code="$snip['badge']" /></div>
                </div>
            </section>

            {{-- Formulários --}}
            <section id="forms" class="mb-16 scroll-mt-24">
                <h2 class="mb-6 font-display text-xl font-semibold tracking-[-0.01em] text-gray-900 dark:text-gray-100">{{ __('showcase.categories.forms') }}</h2>
                <p class="mb-6 text-sm text-gray-500">{{ __('showcase.forms.usage') }}</p>
                <div class="grid max-w-2xl grid-cols-1 gap-6 sm:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <x-input :label="__('showcase.forms.text_label')" name="demo_name" :placeholder="__('showcase.forms.text_placeholder')" :hint="__('showcase.forms.text_hint')" />
                        <div><x-snippet :code="$snip['input']" /></div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <x-input :label="__('showcase.forms.with_error_label')" name="demo_email" type="email" value="nao-e-um-email" :error="__('showcase.forms.with_error_message')" />
                        <div><x-snippet :code="$snip['input_error']" /></div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <x-input :label="__('showcase.forms.disabled_label')" name="demo_disabled" :disabled="true" value="readonly" />
                        <div><x-snippet :code="$snip['input_disabled']" /></div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <x-select :label="__('showcase.forms.select_label')" name="demo_plan">
                            <option>{{ __('showcase.forms.select_option_1') }}</option>
                            <option>{{ __('showcase.forms.select_option_2') }}</option>
                            <option>{{ __('showcase.forms.select_option_3') }}</option>
                        </x-select>
                        <div><x-snippet :code="$snip['select']" /></div>
                    </div>
                    <div class="flex flex-col gap-3">
                        <x-checkbox :label="__('showcase.forms.checkbox')" name="demo_terms" />
                        <x-checkbox :label="__('showcase.forms.checkbox_checked')" name="demo_news" :checked="true" />
                        <div><x-snippet :code="$snip['checkbox']" /></div>
                    </div>
                    <div class="flex flex-col gap-3">
                        <x-toggle :label="__('showcase.forms.toggle')" name="demo_notifications" />
                        <x-toggle :label="__('showcase.forms.toggle_on')" name="demo_2fa" :checked="true" />
                        <div><x-snippet :code="$snip['toggle']" /></div>
                    </div>
                </div>
            </section>

            {{-- Cards --}}
            <section id="cards" class="mb-16 scroll-mt-24">
                <h2 class="mb-6 font-display text-xl font-semibold tracking-[-0.01em] text-gray-900 dark:text-gray-100">{{ __('showcase.categories.cards') }}</h2>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <x-card :title="__('showcase.cards.simple_title')">{{ __('showcase.cards.simple_body') }}</x-card>
                        <div><x-snippet :code="$snip['card']" /></div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <x-card :title="__('showcase.cards.footer_title')">
                            {{ __('showcase.cards.footer_body') }}
                            <x-slot:footer>
                                <x-button size="sm">{{ __('showcase.cards.footer_action') }}</x-button>
                            </x-slot:footer>
                        </x-card>
                        <div><x-snippet :code="$snip['card_footer']" /></div>
                    </div>
                </div>
            </section>

            {{-- Modal --}}
            <section id="modal" class="mb-16 scroll-mt-24">
                <h2 class="mb-6 font-display text-xl font-semibold tracking-[-0.01em] text-gray-900 dark:text-gray-100">{{ __('showcase.categories.modal') }}</h2>
                <div class="flex flex-wrap items-center gap-3">
                    <x-button data-modal-open="showcase-modal">{{ __('showcase.modal.open') }}</x-button>
                    <x-snippet :code="$snip['modal']" />
                </div>
                <x-modal id="showcase-modal" :title="__('showcase.modal.title')">
                    <p>{{ __('showcase.modal.body') }}</p>
                    <x-slot:footer>
                        <x-button variant="ghost" data-modal-close>{{ __('showcase.modal.cancel') }}</x-button>
                        <x-button data-modal-close>{{ __('showcase.modal.confirm') }}</x-button>
                    </x-slot:footer>
                </x-modal>
            </section>

            {{-- Toast --}}
            <section id="toast" class="mb-16 scroll-mt-24">
                <h2 class="mb-6 font-display text-xl font-semibold tracking-[-0.01em] text-gray-900 dark:text-gray-100">{{ __('showcase.categories.toast') }}</h2>
                <div class="flex flex-wrap items-center gap-3">
                    <x-button data-toast-show="showcase-toast" variant="secondary">{{ __('showcase.toast.demo_button') }}</x-button>
                    <x-snippet :code="$snip['toast']" />
                </div>
                <p class="mt-4 text-sm text-gray-500">{{ __('showcase.toast.flash_note') }}</p>
                <div class="pointer-events-none fixed bottom-4 right-4 z-50 flex flex-col items-end gap-2">
                    <x-toast id="showcase-toast" type="success" class="hidden">{{ __('showcase.toast.demo_message') }}</x-toast>
                    <x-toast id="clipboard-toast" type="info" class="hidden">{{ __('showcase.clipboard_toast') }}</x-toast>
                </div>
            </section>

            {{-- Estado vazio --}}
            <section id="empty_state" class="mb-16 scroll-mt-24">
                <h2 class="mb-6 font-display text-xl font-semibold tracking-[-0.01em] text-gray-900 dark:text-gray-100">{{ __('showcase.categories.empty_state') }}</h2>
                <x-empty-state :title="__('showcase.empty_state.title')" :description="__('showcase.empty_state.description')" icon="building-office">
                    <x-button size="sm">{{ __('showcase.empty_state.action') }}</x-button>
                </x-empty-state>
                <div class="mt-2"><x-snippet :code="$snip['empty_state']" /></div>
            </section>

            {{-- Carregamento --}}
            <section id="loading" class="scroll-mt-24">
                <h2 class="mb-6 font-display text-xl font-semibold tracking-[-0.01em] text-gray-900 dark:text-gray-100">{{ __('showcase.categories.loading') }}</h2>
                <p class="mb-3 text-sm text-gray-500">{{ __('showcase.loading.sizes') }}</p>
                <div class="mb-8 flex flex-wrap items-end gap-6">
                    <div class="flex flex-col items-center gap-2"><x-spinner size="sm" /><x-snippet :code="$snip['spinner_sm']" /></div>
                    <div class="flex flex-col items-center gap-2"><x-spinner /><x-snippet :code="$snip['spinner']" /></div>
                    <div class="flex flex-col items-center gap-2"><x-spinner size="lg" /><x-snippet :code="$snip['spinner_lg']" /></div>
                </div>
                <p class="mb-3 text-sm text-gray-500">{{ __('showcase.loading.in_button') }}</p>
                <x-button :disabled="true"><x-spinner size="sm" class="text-white" /> {{ __('showcase.loading.saving') }}</x-button>
            </section>
        </main>
    </div>
</x-layouts.landing>
