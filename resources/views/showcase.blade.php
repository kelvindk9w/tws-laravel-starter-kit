<x-layouts.landing :title="__('showcase.title').' — '.platform()->name">
    <div class="mx-auto max-w-6xl px-4 py-12 lg:flex lg:gap-10">
        {{-- Sidebar de categorias (âncoras) --}}
        <aside class="mb-10 shrink-0 lg:mb-0 lg:w-56">
            <nav class="flex flex-wrap gap-1 lg:sticky lg:top-20 lg:flex-col">
                @foreach (__('showcase.categories') as $anchor => $label)
                    <a href="#{{ $anchor }}" class="rounded-md px-3 py-1.5 text-sm text-gray-400 hover:bg-gray-800 hover:text-white">{{ $label }}</a>
                @endforeach
            </nav>
        </aside>

        <main class="min-w-0 flex-1">
            <header class="mb-12">
                <h1 class="text-3xl font-bold">{{ __('showcase.title') }}</h1>
                <p class="mt-2 text-gray-400">{{ __('showcase.subtitle') }}</p>
            </header>

            {{-- Botões --}}
            <section id="buttons" class="mb-16 scroll-mt-24">
                <h2 class="mb-6 text-xl font-semibold">{{ __('showcase.categories.buttons') }}</h2>

                <p class="mb-3 text-sm text-gray-500">{{ __('showcase.buttons.variants') }}</p>
                <div class="mb-8 flex flex-wrap items-center gap-3">
                    <div class="flex flex-col items-center gap-2"><x-button>{{ __('showcase.buttons.primary') }}</x-button><span class="text-xs text-gray-500">primary</span></div>
                    <div class="flex flex-col items-center gap-2"><x-button variant="secondary">{{ __('showcase.buttons.secondary') }}</x-button><span class="text-xs text-gray-500">secondary</span></div>
                    <div class="flex flex-col items-center gap-2"><x-button variant="ghost">{{ __('showcase.buttons.ghost') }}</x-button><span class="text-xs text-gray-500">ghost</span></div>
                    <div class="flex flex-col items-center gap-2"><x-button variant="danger">{{ __('showcase.buttons.danger') }}</x-button><span class="text-xs text-gray-500">danger</span></div>
                </div>

                <p class="mb-3 text-sm text-gray-500">{{ __('showcase.buttons.sizes') }}</p>
                <div class="mb-8 flex flex-wrap items-center gap-3">
                    <div class="flex flex-col items-center gap-2"><x-button size="sm">{{ __('showcase.buttons.small') }}</x-button><span class="text-xs text-gray-500">sm</span></div>
                    <div class="flex flex-col items-center gap-2"><x-button>{{ __('showcase.buttons.medium') }}</x-button><span class="text-xs text-gray-500">md</span></div>
                    <div class="flex flex-col items-center gap-2"><x-button size="lg">{{ __('showcase.buttons.large') }}</x-button><span class="text-xs text-gray-500">lg</span></div>
                </div>

                <p class="mb-3 text-sm text-gray-500">{{ __('showcase.buttons.states') }}</p>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex flex-col items-center gap-2"><x-button :disabled="true">{{ __('showcase.buttons.disabled') }}</x-button><span class="text-xs text-gray-500">disabled</span></div>
                    <div class="flex flex-col items-center gap-2"><x-button :disabled="true"><x-spinner size="sm" class="text-white" /> {{ __('showcase.buttons.loading') }}</x-button><span class="text-xs text-gray-500">loading</span></div>
                    <div class="flex flex-col items-center gap-2"><x-button href="#buttons" variant="secondary">{{ __('showcase.buttons.as_link') }}</x-button><span class="text-xs text-gray-500">href</span></div>
                </div>
            </section>

            {{-- Alertas --}}
            <section id="alerts" class="mb-16 scroll-mt-24">
                <h2 class="mb-6 text-xl font-semibold">{{ __('showcase.categories.alerts') }}</h2>
                <div class="space-y-4">
                    <x-alert type="success" :title="__('showcase.alerts.success_title')">{{ __('showcase.alerts.success') }}</x-alert>
                    <x-alert type="warning" :title="__('showcase.alerts.warning_title')">{{ __('showcase.alerts.warning') }}</x-alert>
                    <x-alert type="error" :title="__('showcase.alerts.error_title')">{{ __('showcase.alerts.error') }}</x-alert>
                    <x-alert type="info" :title="__('showcase.alerts.info_title')">{{ __('showcase.alerts.info') }}</x-alert>
                </div>
            </section>

            {{-- Badges --}}
            <section id="badges" class="mb-16 scroll-mt-24">
                <h2 class="mb-6 text-xl font-semibold">{{ __('showcase.categories.badges') }}</h2>
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex flex-col items-center gap-2"><x-badge color="green">{{ __('showcase.badges.active') }}</x-badge><span class="text-xs text-gray-500">green</span></div>
                    <div class="flex flex-col items-center gap-2"><x-badge color="yellow">{{ __('showcase.badges.pending') }}</x-badge><span class="text-xs text-gray-500">yellow</span></div>
                    <div class="flex flex-col items-center gap-2"><x-badge color="red">{{ __('showcase.badges.blocked') }}</x-badge><span class="text-xs text-gray-500">red</span></div>
                    <div class="flex flex-col items-center gap-2"><x-badge color="blue">{{ __('showcase.badges.beta') }}</x-badge><span class="text-xs text-gray-500">blue</span></div>
                    <div class="flex flex-col items-center gap-2"><x-badge color="brand">{{ __('showcase.badges.brand') }}</x-badge><span class="text-xs text-gray-500">brand</span></div>
                    <div class="flex flex-col items-center gap-2"><x-badge>{{ __('showcase.badges.neutral') }}</x-badge><span class="text-xs text-gray-500">gray</span></div>
                </div>
            </section>

            {{-- Formulários --}}
            <section id="forms" class="mb-16 scroll-mt-24">
                <h2 class="mb-6 text-xl font-semibold">{{ __('showcase.categories.forms') }}</h2>
                <p class="mb-6 text-sm text-gray-500">{{ __('showcase.forms.usage') }}</p>
                <div class="grid max-w-2xl grid-cols-1 gap-6 sm:grid-cols-2">
                    <x-input :label="__('showcase.forms.text_label')" name="demo_name" :placeholder="__('showcase.forms.text_placeholder')" :hint="__('showcase.forms.text_hint')" />
                    <x-input :label="__('showcase.forms.with_error_label')" name="demo_email" type="email" value="nao-e-um-email" :error="__('showcase.forms.with_error_message')" />
                    <x-input :label="__('showcase.forms.disabled_label')" name="demo_disabled" :disabled="true" value="readonly" />
                    <x-select :label="__('showcase.forms.select_label')" name="demo_plan">
                        <option>{{ __('showcase.forms.select_option_1') }}</option>
                        <option>{{ __('showcase.forms.select_option_2') }}</option>
                        <option>{{ __('showcase.forms.select_option_3') }}</option>
                    </x-select>
                    <div class="flex flex-col gap-3">
                        <x-checkbox :label="__('showcase.forms.checkbox')" name="demo_terms" />
                        <x-checkbox :label="__('showcase.forms.checkbox_checked')" name="demo_news" :checked="true" />
                    </div>
                    <div class="flex flex-col gap-3">
                        <x-toggle :label="__('showcase.forms.toggle')" name="demo_notifications" />
                        <x-toggle :label="__('showcase.forms.toggle_on')" name="demo_2fa" :checked="true" />
                    </div>
                </div>
            </section>

            {{-- Cards --}}
            <section id="cards" class="mb-16 scroll-mt-24">
                <h2 class="mb-6 text-xl font-semibold">{{ __('showcase.categories.cards') }}</h2>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <x-card :title="__('showcase.cards.simple_title')">{{ __('showcase.cards.simple_body') }}</x-card>
                    <x-card :title="__('showcase.cards.footer_title')">
                        {{ __('showcase.cards.footer_body') }}
                        <x-slot:footer>
                            <x-button size="sm">{{ __('showcase.cards.footer_action') }}</x-button>
                        </x-slot:footer>
                    </x-card>
                </div>
            </section>

            {{-- Modal --}}
            <section id="modal" class="mb-16 scroll-mt-24">
                <h2 class="mb-6 text-xl font-semibold">{{ __('showcase.categories.modal') }}</h2>
                <x-button data-modal-open="showcase-modal">{{ __('showcase.modal.open') }}</x-button>
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
                <h2 class="mb-6 text-xl font-semibold">{{ __('showcase.categories.toast') }}</h2>
                <x-button data-toast-demo variant="secondary">{{ __('showcase.toast.demo_button') }}</x-button>
                <p class="mt-4 text-sm text-gray-500">{{ __('showcase.toast.flash_note') }}</p>
                <div class="pointer-events-none fixed bottom-4 right-4 z-50">
                    <x-toast id="showcase-toast" type="success" class="hidden">{{ __('showcase.toast.demo_message') }}</x-toast>
                </div>
                <script>
                    document.querySelector('[data-toast-demo]').addEventListener('click', function () {
                        const toast = document.getElementById('showcase-toast');
                        toast.classList.remove('hidden');
                        setTimeout(function () { toast.classList.add('hidden'); }, 3000);
                    });
                </script>
            </section>

            {{-- Estado vazio --}}
            <section id="empty_state" class="mb-16 scroll-mt-24">
                <h2 class="mb-6 text-xl font-semibold">{{ __('showcase.categories.empty_state') }}</h2>
                <x-empty-state :title="__('showcase.empty_state.title')" :description="__('showcase.empty_state.description')" icon="building-office">
                    <x-button size="sm">{{ __('showcase.empty_state.action') }}</x-button>
                </x-empty-state>
            </section>

            {{-- Carregamento --}}
            <section id="loading" class="scroll-mt-24">
                <h2 class="mb-6 text-xl font-semibold">{{ __('showcase.categories.loading') }}</h2>
                <p class="mb-3 text-sm text-gray-500">{{ __('showcase.loading.sizes') }}</p>
                <div class="mb-8 flex items-end gap-6">
                    <div class="flex flex-col items-center gap-2"><x-spinner size="sm" /><span class="text-xs text-gray-500">sm</span></div>
                    <div class="flex flex-col items-center gap-2"><x-spinner /><span class="text-xs text-gray-500">md</span></div>
                    <div class="flex flex-col items-center gap-2"><x-spinner size="lg" /><span class="text-xs text-gray-500">lg</span></div>
                </div>
                <p class="mb-3 text-sm text-gray-500">{{ __('showcase.loading.in_button') }}</p>
                <x-button :disabled="true"><x-spinner size="sm" class="text-white" /> {{ __('showcase.loading.saving') }}</x-button>
            </section>
        </main>
    </div>
</x-layouts.landing>
