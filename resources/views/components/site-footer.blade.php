@props([
    'width' => 'max-w-6xl',
])

{{-- Rodapé do site — <x-site-footer />. O MESMO em landing, /ui, auth e
     painel: quem entrou na conta continua no site, e o suporte, o status da
     API e a razão social não podem sumir por causa disso.

     Contraste AA: era text-gray-400 (2,60:1 sobre branco) e dark:text-gray-500
     (4,16:1 sobre gray-950) — os dois reprovam. --color-text-muted é
     gray-500/gray-400 por tema: passa nos dois. --}}
<footer class="mt-auto border-t border-border">
    <div class="mx-auto flex {{ $width }} flex-wrap items-start justify-between gap-x-10 gap-y-6 px-4 py-10 text-sm text-text-muted">
        <div>
            <p class="font-display font-semibold tracking-tight text-gray-900 dark:text-gray-200">{{ platform()->name }}</p>
            <p class="mt-1 max-w-xs">{{ __('landing.footer.tagline') }}</p>
        </div>

        <nav aria-label="{{ __('landing.footer.links_heading') }}" class="flex flex-col gap-2">
            <p class="text-caption font-semibold uppercase tracking-widest text-text-muted">{{ __('landing.footer.links_heading') }}</p>
            <a href="{{ route('ui.showcase') }}" class="transition-colors duration-150 ease-(--ease-out) hover:text-gray-900 dark:hover:text-gray-200">{{ __('landing.footer.showcase') }}</a>
            <a href="{{ route('login') }}" class="transition-colors duration-150 ease-(--ease-out) hover:text-gray-900 dark:hover:text-gray-200">{{ __('landing.footer.demo') }}</a>
            <a href="{{ url('/#contato') }}" class="transition-colors duration-150 ease-(--ease-out) hover:text-gray-900 dark:hover:text-gray-200">{{ __('landing.footer.contact') }}</a>
            <a href="{{ url('/api/health') }}" class="transition-colors duration-150 ease-(--ease-out) hover:text-gray-900 dark:hover:text-gray-200">{{ __('landing.footer.api_status') }}</a>
        </nav>

        <div class="flex flex-col items-start gap-1 sm:items-end">
            <p>
                {{ __('landing.footer.rights', ['year' => date('Y'), 'company' => platform()->companyName]) }}
                @if (platform()->companyUrl)
                    · {{ __('landing.footer.developed_by') }}
                    <a href="{{ platform()->companyUrl }}" target="_blank" rel="noopener" class="hover:text-gray-900 dark:hover:text-gray-200">{{ platform()->companyName }}</a>
                @endif
            </p>
            <p>
                {{ __('ui.footer.operated_by', ['platform' => platform()->name]) }}
                @if (platform()->supportEmail)
                    · {{ __('ui.footer.support') }}:
                    <a href="mailto:{{ platform()->supportEmail }}" class="hover:text-gray-900 dark:hover:text-gray-200">{{ platform()->supportEmail }}</a>
                @endif
            </p>
        </div>
    </div>
</footer>
