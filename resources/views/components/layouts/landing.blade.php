@props([
    'title' => null,
])

{{-- Layout público (landing e showcase). Hoje é só um apelido do esqueleto
     único do site (<x-layouts.site>): cabeçalho, rodapé e <head> vivem em UM
     lugar, usado também pelas telas de auth e pelo painel. O nome sobrevive
     porque as views públicas já o referenciam — e porque "landing" continua
     dizendo, para quem lê a view, que aquela tela é pública. --}}
<x-layouts.site :title="$title">
    {{ $slot }}
</x-layouts.site>
