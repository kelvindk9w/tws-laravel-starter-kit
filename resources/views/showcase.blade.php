@php
    // Snippets copiáveis exibidos ao lado de cada variante. Ficam em PHP (e
    // não inline nos atributos) porque o compilador Blade tentaria compilar
    // as tags <x-…> dentro de um atributo de componente.
    $snip = [
        'btn' => '<x-button>…</x-button>',
        'btn_secondary' => '<x-button variant="secondary">…</x-button>',
        'btn_outline' => '<x-button variant="outline">…</x-button>',
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
        'input_password' => '<x-input type="password" label="…" name="password" />',
        'textarea' => '<x-textarea label="…" name="message" />',
        'skeleton' => '<x-skeleton :lines="3" />',
        'skeleton_card' => '<x-skeleton><div class="skeleton h-24 rounded-xl"></div></x-skeleton>',
        'overlay' => '<x-loading-overlay id="app-loading" message="…" /> + abrir via [data-overlay-show]',
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
        'form_errors' => '<x-form-errors /> // segue config/ui.php → error_display',
        'form_errors_summary' => '<x-form-errors display="summary" />',
        'form_errors_toast' => '<x-form-errors display="toast" />',
        'form_errors_both' => '<x-form-errors display="both" /> + :error="field_error(\'email\')"',
        'field_error' => ':error="field_error(\'email\')" // respeita a estratégia configurada',
        'table' => '<x-table :headers="[…]"><x-table-row><x-table-cell label="Nome">…</x-table-cell></x-table-row></x-table>',
        'stat' => '<x-stat label="…" value="12" icon="key" href="…" />',
        'chart' => '<x-chart :labels="[…]" :values="[…]" label="…" />',
        'dropdown' => '<x-dropdown><x-slot:trigger>…</x-slot:trigger><x-dropdown-item href="…">…</x-dropdown-item></x-dropdown>',
        'dropdown_danger' => '<x-dropdown-item danger wire:click="…">…</x-dropdown-item>',
        'drawer' => '<x-button data-modal-open="menu">…</x-button> + <x-drawer id="menu" title="…" side="left">…</x-drawer>',
        'file_input' => '<x-file-input name="avatar" accept="image/*" />',
        'avatar' => '<x-avatar :user="$user" size="md" />',
        'avatar_initials' => '<x-avatar name="Ana Ribeiro" size="lg" />',
        'side_nav' => '<x-side-nav id="ui" :title="…" :groups="…" spy />',
        'locale_switcher' => '<x-locale-switcher />',
        'theme_toggle' => '<x-theme-toggle />',
        'classic_form' => '<form method="POST">…old(\'campo\')…</form> + redirect back()',
        'ajax_form' => '<form wire:submit="send">…wire:model…</form>',
    ];
@endphp

<x-layouts.landing :title="__('showcase.title').' — '.platform()->name">
    <div class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 lg:flex lg:gap-10 lg:py-12">
        {{-- Índice das seções: coluna com rolagem própria no desktop, barra
             compacta + gaveta no mobile (<x-side-nav>, o MESMO componente do
             menu "Minha conta" do painel). O scrollspy marca a seção atual e
             o rótulo da barra acompanha. --}}
        <x-side-nav
            id="ui"
            :title="__('ui.nav.sections')"
            :groups="\App\Livewire\Support\Navigation::showcase()"
            spy
        />

        <main class="min-w-0 flex-1">
            <header class="mb-12 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="font-display text-3xl font-bold tracking-[-0.02em] text-gray-900 dark:text-gray-100">{{ __('showcase.title') }}</h1>
                    <p class="mt-2 max-w-xl text-gray-600 dark:text-gray-400">{{ __('showcase.subtitle') }}</p>
                </div>

                {{-- Toggle de tema de 3 estados (sistema/claro/escuro): prova
                     os dois temas dos componentes e segue o SO por padrão --}}
                <x-theme-toggle />
            </header>

            {{-- Tema e tokens de design --}}
            <section id="theme" class="mb-16 scroll-mt-32 lg:scroll-mt-24">
                <h2 class="mb-6 font-display text-xl font-semibold tracking-[-0.01em] text-gray-900 dark:text-gray-100">{{ __('showcase.categories.theme') }}</h2>
                <p class="mb-6 max-w-2xl text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.theme_tokens.guide') }}</p>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <x-card :title="__('showcase.theme_tokens.brand')">
                        <div class="flex items-center gap-3">
                            <span class="h-10 w-10 rounded-lg bg-brand"></span>
                            <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs dark:bg-gray-800">PLATFORM_PRIMARY_COLOR={{ platform()->primaryColor ?? __('showcase.theme_tokens.brand_unset') }}</code>
                        </div>
                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.theme_tokens.brand_hint') }}</p>
                    </x-card>

                    <x-card :title="__('showcase.theme_tokens.fonts')">
                        <p class="font-display text-lg font-semibold">{{ __('showcase.theme_tokens.font_display_sample') }}</p>
                        <p class="mt-1 text-sm">{{ __('showcase.theme_tokens.font_body_sample') }}</p>
                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.theme_tokens.fonts_hint') }}</p>
                    </x-card>

                    <x-card :title="__('showcase.theme_tokens.radii')">
                        <div class="flex items-end gap-3">
                            <span class="h-10 w-10 rounded-lg border border-gray-300 dark:border-gray-700"></span>
                            <span class="h-10 w-10 rounded-xl border border-gray-300 dark:border-gray-700"></span>
                            <code class="text-xs text-gray-500">rounded-lg / rounded-xl</code>
                        </div>
                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.theme_tokens.radii_hint') }}</p>
                    </x-card>

                    <x-card :title="__('showcase.theme_tokens.motion')">
                        <code class="block text-xs text-gray-500">--ease-out: cubic-bezier(0.23, 1, 0.32, 1)</code>
                        <code class="mt-1 block text-xs text-gray-500">--ease-in-out: cubic-bezier(0.77, 0, 0.175, 1)</code>
                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.theme_tokens.motion_hint') }}</p>
                    </x-card>
                </div>

                <div class="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-800 dark:bg-gray-900/50">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ __('showcase.theme_tokens.modes') }}</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.theme_tokens.modes_hint') }}</p>
                </div>
            </section>

            {{-- Botões --}}
            <section id="buttons" class="mb-16 scroll-mt-32 lg:scroll-mt-24">
                <h2 class="mb-2 font-display text-xl font-semibold tracking-[-0.01em] text-gray-900 dark:text-gray-100">{{ __('showcase.categories.buttons') }}</h2>
                <p class="mb-6 max-w-2xl text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.buttons.guide') }}</p>

                <p class="mb-3 text-sm text-gray-500">{{ __('showcase.buttons.variants') }}</p>
                <div class="mb-8 grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-5">
                    <div class="flex flex-col items-center gap-2"><x-button>{{ __('showcase.buttons.primary') }}</x-button><x-snippet :code="$snip['btn']" /></div>
                    <div class="flex flex-col items-center gap-2"><x-button variant="secondary">{{ __('showcase.buttons.secondary') }}</x-button><x-snippet :code="$snip['btn_secondary']" /></div>
                    <div class="flex flex-col items-center gap-2"><x-button variant="outline">{{ __('showcase.buttons.outline') }}</x-button><x-snippet :code="$snip['btn_outline']" /></div>
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
                    <div class="flex flex-col items-center gap-2"><x-button :disabled="true"><x-spinner size="sm" class="text-brand-foreground" /> {{ __('showcase.buttons.loading') }}</x-button><x-snippet :code="$snip['btn_loading']" /></div>
                    <div class="flex flex-col items-center gap-2"><x-button href="#buttons" variant="secondary">{{ __('showcase.buttons.as_link') }}</x-button><x-snippet :code="$snip['btn_link']" /></div>
                </div>
            </section>

            {{-- Alertas --}}
            <section id="alerts" class="mb-16 scroll-mt-32 lg:scroll-mt-24">
                <h2 class="mb-2 font-display text-xl font-semibold tracking-[-0.01em] text-gray-900 dark:text-gray-100">{{ __('showcase.categories.alerts') }}</h2>
                <p class="mb-6 max-w-2xl text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.alerts.guide') }}</p>
                <div class="space-y-4">
                    <div class="space-y-2"><x-alert type="success" :title="__('showcase.alerts.success_title')">{{ __('showcase.alerts.success') }}</x-alert><div><x-snippet :code="$snip['alert_success']" /></div></div>
                    <div class="space-y-2"><x-alert type="warning" :title="__('showcase.alerts.warning_title')">{{ __('showcase.alerts.warning') }}</x-alert><div><x-snippet :code="$snip['alert_warning']" /></div></div>
                    <div class="space-y-2"><x-alert type="error" :title="__('showcase.alerts.error_title')">{{ __('showcase.alerts.error') }}</x-alert><div><x-snippet :code="$snip['alert_error']" /></div></div>
                    <div class="space-y-2"><x-alert type="info" :title="__('showcase.alerts.info_title')">{{ __('showcase.alerts.info') }}</x-alert><div><x-snippet :code="$snip['alert_info']" /></div></div>
                </div>
            </section>

            {{-- Badges --}}
            <section id="badges" class="mb-16 scroll-mt-32 lg:scroll-mt-24">
                <h2 class="mb-2 font-display text-xl font-semibold tracking-[-0.01em] text-gray-900 dark:text-gray-100">{{ __('showcase.categories.badges') }}</h2>
                <p class="mb-6 max-w-2xl text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.badges.guide') }}</p>
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
            <section id="forms" class="mb-16 scroll-mt-32 lg:scroll-mt-24">
                <h2 class="mb-2 font-display text-xl font-semibold tracking-[-0.01em] text-gray-900 dark:text-gray-100">{{ __('showcase.categories.forms') }}</h2>
                <p class="mb-2 max-w-2xl text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.forms.guide') }}</p>
                <p class="mb-6 text-sm text-gray-500">{{ __('showcase.forms.usage') }}</p>
                <div class="grid max-w-2xl grid-cols-1 gap-6 sm:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <x-input :label="__('showcase.forms.text_label')" name="demo_name" :placeholder="__('showcase.forms.text_placeholder')" :hint="__('showcase.forms.text_hint')" />
                        <div><x-snippet :code="$snip['input']" /></div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <x-input :label="__('showcase.forms.password_label')" name="demo_password" type="password" :hint="__('showcase.forms.password_hint')" />
                        <div><x-snippet :code="$snip['input_password']" /></div>
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
                        <x-textarea :label="__('showcase.forms.message_label')" name="demo_message" :placeholder="__('showcase.forms.message_placeholder')" />
                        <div><x-snippet :code="$snip['textarea']" /></div>
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
            <section id="cards" class="mb-16 scroll-mt-32 lg:scroll-mt-24">
                <h2 class="mb-2 font-display text-xl font-semibold tracking-[-0.01em] text-gray-900 dark:text-gray-100">{{ __('showcase.categories.cards') }}</h2>
                <p class="mb-6 max-w-2xl text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.cards.guide') }}</p>
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
            <section id="modal" class="mb-16 scroll-mt-32 lg:scroll-mt-24">
                <h2 class="mb-2 font-display text-xl font-semibold tracking-[-0.01em] text-gray-900 dark:text-gray-100">{{ __('showcase.categories.modal') }}</h2>
                <p class="mb-6 max-w-2xl text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.modal.guide') }}</p>
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
            <section id="toast" class="mb-16 scroll-mt-32 lg:scroll-mt-24">
                <h2 class="mb-2 font-display text-xl font-semibold tracking-[-0.01em] text-gray-900 dark:text-gray-100">{{ __('showcase.categories.toast') }}</h2>
                <p class="mb-6 max-w-2xl text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.toast.guide') }}</p>
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
            <section id="empty_state" class="mb-16 scroll-mt-32 lg:scroll-mt-24">
                <h2 class="mb-2 font-display text-xl font-semibold tracking-[-0.01em] text-gray-900 dark:text-gray-100">{{ __('showcase.categories.empty_state') }}</h2>
                <p class="mb-6 max-w-2xl text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.empty_state.guide') }}</p>
                <x-empty-state :title="__('showcase.empty_state.title')" :description="__('showcase.empty_state.description')" icon="building-office">
                    <x-button size="sm">{{ __('showcase.empty_state.action') }}</x-button>
                </x-empty-state>
                <div class="mt-2"><x-snippet :code="$snip['empty_state']" /></div>
            </section>

            {{-- Carregamento: spinner, skeleton e overlay (hierarquia de espera) --}}
            <section id="loading" class="mb-16 scroll-mt-32 lg:scroll-mt-24">
                <h2 class="mb-2 font-display text-xl font-semibold tracking-[-0.01em] text-gray-900 dark:text-gray-100">{{ __('showcase.categories.loading') }}</h2>
                <p class="mb-6 max-w-2xl text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.loading.guide') }}</p>

                <p class="mb-3 text-sm text-gray-500">{{ __('showcase.loading.sizes') }}</p>
                <div class="mb-8 flex flex-wrap items-end gap-6">
                    <div class="flex flex-col items-center gap-2"><x-spinner size="sm" /><x-snippet :code="$snip['spinner_sm']" /></div>
                    <div class="flex flex-col items-center gap-2"><x-spinner /><x-snippet :code="$snip['spinner']" /></div>
                    <div class="flex flex-col items-center gap-2"><x-spinner size="lg" /><x-snippet :code="$snip['spinner_lg']" /></div>
                </div>
                <p class="mb-3 text-sm text-gray-500">{{ __('showcase.loading.in_button') }}</p>
                <div class="mb-10 flex flex-wrap items-center gap-3">
                    <x-button :disabled="true"><x-spinner size="sm" class="text-brand-foreground" /> {{ __('showcase.loading.saving') }}</x-button>
                </div>

                <h3 class="mb-2 font-semibold text-gray-900 dark:text-gray-100">{{ __('showcase.loading.skeleton_heading') }}</h3>
                <p class="mb-4 max-w-2xl text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.loading.skeleton_hint') }}</p>
                <div class="mb-10 grid max-w-2xl grid-cols-1 gap-6 sm:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <x-skeleton :lines="3" />
                        <div><x-snippet :code="$snip['skeleton']" /></div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <x-skeleton>
                            <div class="flex items-center gap-3">
                                <div class="skeleton h-10 w-10 rounded-full"></div>
                                <div class="flex-1 space-y-2">
                                    <div class="skeleton h-3 w-1/2 rounded"></div>
                                    <div class="skeleton h-3 w-full rounded"></div>
                                </div>
                            </div>
                        </x-skeleton>
                        <div><x-snippet :code="$snip['skeleton_card']" /></div>
                    </div>
                </div>

                <h3 class="mb-2 font-semibold text-gray-900 dark:text-gray-100">{{ __('showcase.loading.overlay_heading') }}</h3>
                <x-alert type="warning" class="mb-4 max-w-2xl">{{ __('showcase.loading.overlay_restriction') }}</x-alert>
                <div class="flex flex-wrap items-center gap-3">
                    <x-button variant="outline" data-overlay-show="showcase-overlay" data-overlay-timeout="1500">{{ __('showcase.loading.overlay_demo') }}</x-button>
                    <x-snippet :code="$snip['overlay']" />
                </div>
                <x-loading-overlay id="showcase-overlay" :message="__('showcase.loading.saving')" />
            </section>

            {{-- Dados: tabela, métrica e gráfico (o painel do usuário consome os 3) --}}
            <section id="data_display" class="mb-16 scroll-mt-32 lg:scroll-mt-24">
                <h2 class="mb-2 font-display text-xl font-semibold tracking-[-0.01em] text-gray-900 dark:text-gray-100">{{ __('showcase.categories.data_display') }}</h2>
                <p class="mb-6 max-w-2xl text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.data_display.guide') }}</p>

                <h3 class="mb-2 font-semibold text-gray-900 dark:text-gray-100">{{ __('showcase.data_display.table_heading') }}</h3>
                <p class="mb-4 max-w-2xl text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.data_display.table_hint') }}</p>
                <x-table :headers="[__('showcase.data_display.col_name'), __('showcase.data_display.col_status'), __('showcase.data_display.col_actions')]">
                    @foreach ([['Checkout', 'green'], ['Webhooks', 'green'], ['Legado', 'gray']] as [$rowName, $rowColor])
                        <x-table-row>
                            <x-table-cell :label="__('showcase.data_display.col_name')">
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $rowName }}</span>
                            </x-table-cell>
                            <x-table-cell :label="__('showcase.data_display.col_status')">
                                <x-badge :color="$rowColor">{{ $rowColor === 'green' ? __('showcase.data_display.status_active') : __('showcase.data_display.status_off') }}</x-badge>
                            </x-table-cell>
                            <x-table-cell :label="__('showcase.data_display.col_actions')" align="end">
                                <span class="flex items-center justify-end gap-2">
                                    <x-button variant="secondary" size="sm">{{ __('showcase.data_display.row_action') }}</x-button>
                                    <x-dropdown>
                                        <x-slot:trigger>
                                            <button type="button" aria-label="{{ __('panel.common.more_actions') }}" class="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-border text-gray-500 hover:bg-surface-sunken sm:h-8 sm:w-8 dark:text-gray-400">
                                                <x-ui-icon name="ellipsis-horizontal" class="h-4 w-4" />
                                            </button>
                                        </x-slot:trigger>
                                        <x-dropdown-item danger>
                                            <x-ui-icon name="trash" class="h-4 w-4" />
                                            {{ __('panel.common.delete') }}
                                        </x-dropdown-item>
                                    </x-dropdown>
                                </span>
                            </x-table-cell>
                        </x-table-row>
                    @endforeach
                </x-table>
                <div class="mt-2"><x-snippet :code="$snip['table']" /></div>

                <h3 class="mb-2 mt-10 font-semibold text-gray-900 dark:text-gray-100">{{ __('showcase.data_display.stat_heading') }}</h3>
                <p class="mb-4 max-w-2xl text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.data_display.stat_hint') }}</p>
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-stat :label="__('showcase.data_display.stat_keys')" value="12" icon="key" />
                    <x-stat :label="__('showcase.data_display.stat_projects')" value="3" icon="folder" />
                    <x-stat :label="__('showcase.data_display.stat_requests')" value="1.284" icon="signal" :hint="__('showcase.data_display.stat_hint_days')" />
                </div>
                <div class="mt-2"><x-snippet :code="$snip['stat']" /></div>

                <h3 class="mb-2 mt-10 font-semibold text-gray-900 dark:text-gray-100">{{ __('showcase.data_display.chart_heading') }}</h3>
                <p class="mb-4 max-w-2xl text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.data_display.chart_hint') }}</p>
                <div class="grid gap-6 lg:grid-cols-2">
                    <x-card :title="__('showcase.data_display.chart_with_data')">
                        <x-chart
                            :labels="['01/09', '02/09', '03/09', '04/09', '05/09', '06/09', '07/09']"
                            :values="[8, 14, 9, 22, 17, 26, 31]"
                            :label="__('showcase.data_display.chart_series')"
                        />
                    </x-card>
                    <x-card :title="__('showcase.data_display.chart_empty')">
                        <x-chart :labels="[]" :values="[]" :label="__('showcase.data_display.chart_series')" :empty-description="__('showcase.data_display.chart_empty_hint')" />
                    </x-card>
                </div>
                <div class="mt-2"><x-snippet :code="$snip['chart']" /></div>
            </section>

            {{-- Navegação: dropdown, drawer, arquivo, idioma e tema --}}
            <section id="navigation" class="mb-16 scroll-mt-32 lg:scroll-mt-24">
                <h2 class="mb-2 font-display text-xl font-semibold tracking-[-0.01em] text-gray-900 dark:text-gray-100">{{ __('showcase.categories.navigation') }}</h2>
                <p class="mb-6 max-w-2xl text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.navigation.guide') }}</p>

                <div class="grid gap-8 sm:grid-cols-2">
                    <div>
                        <h3 class="mb-2 font-semibold text-gray-900 dark:text-gray-100">{{ __('showcase.navigation.dropdown_heading') }}</h3>
                        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.navigation.dropdown_hint') }}</p>
                        <x-dropdown align="left">
                            <x-slot:trigger>
                                <x-button variant="secondary" size="sm">
                                    {{ __('showcase.navigation.dropdown_trigger') }}
                                    <x-ui-icon name="chevron-down" class="h-3.5 w-3.5" />
                                </x-button>
                            </x-slot:trigger>
                            <x-dropdown-item active>
                                <x-ui-icon name="check-circle" class="h-4 w-4" />
                                {{ __('showcase.navigation.dropdown_item_active') }}
                            </x-dropdown-item>
                            <x-dropdown-item>
                                <x-ui-icon name="pencil-square" class="h-4 w-4" />
                                {{ __('showcase.navigation.dropdown_item') }}
                            </x-dropdown-item>
                            <x-dropdown-item danger>
                                <x-ui-icon name="trash" class="h-4 w-4" />
                                {{ __('showcase.navigation.dropdown_item_danger') }}
                            </x-dropdown-item>
                        </x-dropdown>
                        <div class="mt-3 flex flex-col gap-2">
                            <x-snippet :code="$snip['dropdown']" />
                            <x-snippet :code="$snip['dropdown_danger']" />
                        </div>
                    </div>

                    <div>
                        <h3 class="mb-2 font-semibold text-gray-900 dark:text-gray-100">{{ __('showcase.navigation.drawer_heading') }}</h3>
                        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.navigation.drawer_hint') }}</p>
                        <x-button variant="secondary" size="sm" data-modal-open="showcase-drawer">
                            <x-ui-icon name="bars-3" class="h-4 w-4" />
                            {{ __('showcase.navigation.drawer_demo') }}
                        </x-button>
                        <x-drawer id="showcase-drawer" :title="__('showcase.navigation.drawer_title')">
                            <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('showcase.navigation.drawer_body') }}</p>
                        </x-drawer>
                        <div class="mt-3"><x-snippet :code="$snip['drawer']" /></div>
                    </div>

                    <div>
                        <h3 class="mb-2 font-semibold text-gray-900 dark:text-gray-100">{{ __('showcase.navigation.file_heading') }}</h3>
                        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.navigation.file_hint') }}</p>
                        <x-file-input name="showcase_file" accept="image/*" />
                        <div class="mt-3"><x-snippet :code="$snip['file_input']" /></div>
                    </div>

                    <div>
                        <h3 class="mb-2 font-semibold text-gray-900 dark:text-gray-100">{{ __('showcase.navigation.avatar_heading') }}</h3>
                        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.navigation.avatar_hint') }}</p>
                        <div class="flex flex-wrap items-end gap-4">
                            <x-avatar name="Ana Ribeiro" size="sm" />
                            <x-avatar name="Ana Ribeiro" size="md" />
                            <x-avatar name="Ana Ribeiro" size="lg" />
                            <x-avatar :src="asset('img/landing/dashboard.png')" name="Ana Ribeiro" size="lg" />
                        </div>
                        <div class="mt-3 flex flex-col gap-2">
                            <x-snippet :code="$snip['avatar']" />
                            <x-snippet :code="$snip['avatar_initials']" />
                        </div>
                    </div>

                    <div>
                        <h3 class="mb-2 font-semibold text-gray-900 dark:text-gray-100">{{ __('showcase.navigation.side_nav_heading') }}</h3>
                        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.navigation.side_nav_hint') }}</p>
                        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.navigation.side_nav_live') }}</p>
                        <div class="mt-3"><x-snippet :code="$snip['side_nav']" /></div>
                    </div>

                    <div>
                        <h3 class="mb-2 font-semibold text-gray-900 dark:text-gray-100">{{ __('showcase.navigation.locale_heading') }}</h3>
                        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.navigation.locale_hint') }}</p>
                        <div class="flex flex-wrap items-center gap-3">
                            <x-locale-switcher />
                            <x-theme-toggle />
                        </div>
                        <div class="mt-3 flex flex-col gap-2">
                            <x-snippet :code="$snip['locale_switcher']" />
                            <x-snippet :code="$snip['theme_toggle']" />
                        </div>
                    </div>
                </div>
            </section>

            {{-- Padrões de formulário: Blade clássico × Livewire + estratégias de erro --}}
            <section id="form_patterns" class="scroll-mt-32 lg:scroll-mt-24">
                <h2 class="mb-2 font-display text-xl font-semibold tracking-[-0.01em] text-gray-900 dark:text-gray-100">{{ __('showcase.categories.form_patterns') }}</h2>
                <p class="mb-8 max-w-2xl text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.form_patterns.guide') }}</p>

                {{-- Estratégias de exibição de erros (config/ui.php → error_display) --}}
                <h3 class="mb-2 font-semibold text-gray-900 dark:text-gray-100">{{ __('showcase.form_patterns.strategies_heading') }}</h3>
                <p class="mb-6 max-w-2xl text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.form_patterns.strategies_guide') }}</p>

                <div class="mb-12 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div class="flex flex-col gap-2">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('showcase.form_patterns.strategy_inline') }}</p>
                        <x-input :label="__('showcase.forms.with_error_label')" name="demo_inline_email" type="email" value="nao-e-um-email" :error="__('showcase.forms.with_error_message')" />
                        <p class="text-sm text-gray-500">{{ __('showcase.form_patterns.strategy_inline_hint') }}</p>
                        <div><x-snippet :code="$snip['field_error']" /></div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('showcase.form_patterns.strategy_summary') }}</p>
                        <x-form-errors display="summary" :messages="['demo_summary_email' => __('showcase.forms.with_error_message'), 'demo_summary_message' => __('showcase.form_patterns.demo_error_message')]" />
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-input :label="__('showcase.forms.with_error_label')" name="demo_summary_email" type="email" value="nao-e-um-email" />
                            <x-input :label="__('showcase.forms.message_label')" name="demo_summary_message" value="curta" />
                        </div>
                        <p class="text-sm text-gray-500">{{ __('showcase.form_patterns.strategy_summary_hint') }}</p>
                        <div><x-snippet :code="$snip['form_errors_summary']" /></div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('showcase.form_patterns.strategy_toast') }}</p>
                        <x-form-errors display="toast" :fixed="false" data-toast-sticky :messages="[__('showcase.forms.with_error_message'), __('showcase.form_patterns.demo_error_message')]" />
                        <p class="text-sm text-gray-500">{{ __('showcase.form_patterns.strategy_toast_hint') }}</p>
                        <div><x-snippet :code="$snip['form_errors_toast']" /></div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('showcase.form_patterns.strategy_both') }}</p>
                        <x-form-errors display="both" :messages="['demo_both_email' => __('showcase.forms.with_error_message')]" />
                        <x-input :label="__('showcase.forms.with_error_label')" name="demo_both_email" type="email" value="nao-e-um-email" :error="__('showcase.forms.with_error_message')" />
                        <p class="text-sm text-gray-500">{{ __('showcase.form_patterns.strategy_both_hint') }}</p>
                        <div><x-snippet :code="$snip['form_errors_both']" /></div>
                    </div>
                </div>

                {{-- Exemplo funcional 1: Blade clássico (POST + redirect + old()) --}}
                <x-card :title="__('showcase.form_patterns.classic_heading')" class="mb-8 max-w-2xl">
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.form_patterns.classic_guide') }}</p>

                    @php($demoDisplay = form_error_display(old('classic_display')))

                    {{-- novalidate: a demo existe para provar a validação
                         SERVER-SIDE (sem ela, o browser barra o submit). --}}
                    <form method="POST" action="{{ route('ui.form-demo') }}" class="space-y-4" id="demo-classic" novalidate>
                        @csrf

                        <x-form-errors :display="$demoDisplay" />

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-input :label="__('showcase.form_patterns.demo_nickname')" name="classic_nickname" :value="old('classic_nickname')" :placeholder="__('showcase.form_patterns.demo_nickname_placeholder')" :error="field_error('classic_nickname', $demoDisplay)" required maxlength="120" />
                            <x-select :label="__('showcase.form_patterns.demo_subject')" name="classic_subject" :error="field_error('classic_subject', $demoDisplay)" required>
                                @foreach (['suggestion', 'complaint', 'other'] as $subjectKey)
                                    <option value="{{ $subjectKey }}" @selected(old('classic_subject', 'suggestion') === $subjectKey)>{{ __("contact.subjects.{$subjectKey}") }}</option>
                                @endforeach
                            </x-select>
                        </div>

                        <x-textarea :label="__('showcase.form_patterns.demo_message')" name="classic_message" :placeholder="__('showcase.form_patterns.demo_message_placeholder')" :error="field_error('classic_message', $demoDisplay)" required minlength="10" maxlength="2000">{{ old('classic_message') }}</x-textarea>

                        {{-- Honeypot anti-spam: invisível para humanos; bots o
                             preenchem → bloqueio registrado (vitrine /admin). --}}
                        <div class="hidden" aria-hidden="true">
                            <label for="classic_website">{{ __('showcase.form_patterns.demo_honeypot_label') }}</label>
                            <input id="classic_website" type="text" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        <x-select :label="__('showcase.form_patterns.display_field')" name="classic_display" :hint="__('showcase.form_patterns.display_field_hint')">
                            @foreach (['inline', 'summary', 'toast', 'both'] as $strategyOption)
                                <option value="{{ $strategyOption }}" @selected(old('classic_display', 'inline') === $strategyOption)>{{ $strategyOption }}</option>
                            @endforeach
                        </x-select>

                        <x-button type="submit">{{ __('showcase.form_patterns.demo_submit') }}</x-button>
                    </form>

                    <x-slot:footer>
                        <x-snippet :code="$snip['classic_form']" />
                    </x-slot:footer>
                </x-card>

                {{-- Exemplo funcional 2: Livewire (wire:submit, AJAX) — o mesmo
                     envio do contato da landing, sem reload. --}}
                <x-card :title="__('showcase.form_patterns.ajax_heading')" class="max-w-2xl">
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">{{ __('showcase.form_patterns.ajax_guide') }}</p>

                    <div id="demo-ajax">
                        <livewire:contact-form />
                    </div>

                    <x-slot:footer>
                        <x-snippet :code="$snip['ajax_form']" />
                    </x-slot:footer>
                </x-card>
            </section>
        </main>
    </div>
</x-layouts.landing>
