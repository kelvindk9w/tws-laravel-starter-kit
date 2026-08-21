@props([
    'id',
    'title' => null,
])

{{--
    Modal sem dependência de JS externo. Abertura: qualquer elemento com
    data-modal-open="<id>". Fechamento: data-modal-close, backdrop ou Esc.

    <x-button data-modal-open="confirmar">Abrir</x-button>
    <x-modal id="confirmar" title="…">conteúdo</x-modal>
--}}
<div
    id="{{ $id }}"
    data-modal
    class="fixed inset-0 z-50 hidden items-center justify-center p-4"
    role="dialog"
    aria-modal="true"
>
    <div class="absolute inset-0 bg-gray-950/60 backdrop-blur-sm" data-modal-close></div>
    <div {{ $attributes->merge(['class' => 'relative w-full max-w-md rounded-xl border border-gray-200 bg-white p-6 shadow-xl dark:border-gray-800 dark:bg-gray-900']) }}>
        <div class="mb-4 flex items-start justify-between gap-4">
            @if ($title !== null)
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h2>
            @endif
            <button type="button" data-modal-close class="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200">
                <x-ui-icon name="x-mark" class="h-5 w-5" />
            </button>
        </div>
        <div class="text-sm text-gray-600 dark:text-gray-300">{{ $slot }}</div>
        @isset($footer)
            <div class="mt-6 flex justify-end gap-2">{{ $footer }}</div>
        @endisset
    </div>
</div>

@once
    <script>
        document.addEventListener('click', function (event) {
            const opener = event.target.closest('[data-modal-open]');
            if (opener) {
                const modal = document.getElementById(opener.dataset.modalOpen);
                if (modal) { modal.classList.remove('hidden'); modal.classList.add('flex'); }
                return;
            }
            if (event.target.closest('[data-modal-close]')) {
                const modal = event.target.closest('[data-modal]');
                if (modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('[data-modal]:not(.hidden)').forEach(function (modal) {
                    modal.classList.add('hidden'); modal.classList.remove('flex');
                });
            }
        });
    </script>
@endonce
