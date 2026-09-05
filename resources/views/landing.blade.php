{{-- LANDING OFICIAL "CÉU" — rota /. (A direção nasceu em /v3, foi escolhida
     pelo dono e virou a home; /v3 continua existindo como redirect 301 e /v2
     segue ao lado como conceito alternativo.)

     Por que um documento PRÓPRIO e não <x-layouts.site>: o esqueleto do kit
     injeta exatamente dois bundles (app.css + app.js), e esta página precisa
     dos dela por cima. Dar um slot de <head> ao layout de TODO o produto por
     causa de uma página mexeria no esqueleto de showcase, auth e painel. Aqui
     o toque no kit é de duas props (site-header variant="floating",
     site-footer variant="plain") — e mais nada.

     O <head> abaixo é o MESMO do kit, item a item: charset, viewport, CSRF,
     override de marca do .env e o script anti-flash de tema. Cabeçalho,
     rodapé e toasts são os componentes do produto. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme-default="{{ auth()->user()?->theme ?? 'system' }}" data-sky-webgl="{{ config('landing.webgl_enabled') ? 'on' : 'off' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('landing.meta.title') }} · {{ platform()->name }}</title>
    <meta name="description" content="{{ __('landing.meta.description') }}">

    @if (platform()->primaryColor !== null)
        <style>:root { --brand: {{ platform()->primaryColor }}; }</style>
    @endif

    @include('partials.theme-script')

    {{-- A capa do leque é o LCP da página: pré-carregada nas duas versões de
         tema? Não — só a clara, que é o padrão do sistema na maioria das
         máquinas; a escura entra pelo <picture> do herói sem bloquear. --}}
    <link rel="preload" as="image" href="{{ asset('img/landing/dashboard-light-1440.webp') }}" imagesrcset="{{ asset('img/landing/dashboard-light-720.webp') }} 720w, {{ asset('img/landing/dashboard-light-1440.webp') }} 1440w" imagesizes="(max-width: 640px) 80vw, 30rem" fetchpriority="high">

    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/css/landing.css', 'resources/js/landing.js'])
</head>
<body @auth data-authenticated @endauth class="sky flex min-h-screen flex-col bg-surface antialiased">
    {{-- Pular para o conteúdo: a primeira parada do teclado numa página cujo
         herói é grande e cheio de objetos decorativos. --}}
    <a href="#conteudo" class="sr-only rounded-full bg-brand px-4 py-2 text-brand-foreground focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50">{{ __('landing.a11y.skip') }}</a>

    <x-site-header variant="floating" />

    <main id="conteudo">
        @include('landing.hero')
        @include('landing.components')
        @include('landing.how')
        @include('landing.security')
        @include('landing.contact')
        @include('landing.sky-footer')
    </main>

    <x-flash-toast />
</body>
</html>
