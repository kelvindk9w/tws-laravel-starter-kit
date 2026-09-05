@php
    use App\Livewire\Support\Navigation;
@endphp

{{-- Painel do usuário. Mesmo cabeçalho, mesmo rodapé e mesma marca do site
     (decisão do dono: quem entra na conta NÃO sai do site) — o que muda é
     que aparece um menu lateral "Minha conta" à esquerda do conteúdo, no
     mesmo estilo do índice do /ui.

     A antiga barra horizontal do painel não escalava: cada tela nova
     empurrava a próxima para fora, e no mobile o menu virava um bloco de
     29% da altura da tela. Na coluna, "adicionar uma tela" é acrescentar uma
     linha ao mapa (App\Livewire\Support\Navigation). --}}
<x-layouts.site :title="($title ?? __('panel.nav.dashboard')).' — '.platform()->name" background="bg-surface-sunken" width="max-w-7xl">
    <div class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 lg:flex lg:gap-10">
        {{-- mobile="none": no painel o índice do mobile mora na gaveta do
             cabeçalho, junto dos links do site — uma gaveta só. --}}
        <x-side-nav
            id="account"
            :title="__('ui.nav.account')"
            :groups="Navigation::account()"
            mobile="none"
        />

        <main class="min-w-0 flex-1">
            {{ $slot }}
        </main>
    </div>
</x-layouts.site>
