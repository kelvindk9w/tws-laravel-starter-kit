{{-- LANDING "CÉU" (v3) — rota /v3, direção em avaliação ao lado de / e /v2.

     Por que um documento PRÓPRIO e não <x-layouts.site>: o esqueleto do kit
     injeta exatamente dois bundles (app.css + app.js), e a v3 precisa dos
     dela por cima. Dar um slot de <head> ao layout de TODO o produto para
     acomodar uma direção em teste seria mexer no esqueleto de landing,
     showcase, auth e painel por causa de uma página. Aqui o toque no kit é
     de duas props (site-header variant="floating", site-footer
     variant="plain") — e mais nada.

     O <head> abaixo é o MESMO do kit, item a item: charset, viewport, CSRF,
     override de marca do .env e o script anti-flash de tema. Cabeçalho,
     rodapé e toasts são os componentes do produto. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme-default="{{ auth()->user()?->theme ?? 'system' }}" data-v3-webgl="{{ config('landing_v3.webgl_enabled') ? 'on' : 'off' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('landing_v3.meta.title') }} · {{ platform()->name }}</title>
    <meta name="description" content="{{ __('landing_v3.meta.description') }}">

    @if (platform()->primaryColor !== null)
        <style>:root { --brand: {{ platform()->primaryColor }}; }</style>
    @endif

    @include('partials.theme-script')

    {{-- A capa do leque é o LCP da página: pré-carregada nas duas versões de
         tema? Não — só a clara, que é o padrão do sistema na maioria das
         máquinas; a escura entra pelo <picture> do herói sem bloquear. --}}
    <link rel="preload" as="image" href="{{ asset('img/landing-v3/dashboard-light-1440.webp') }}" imagesrcset="{{ asset('img/landing-v3/dashboard-light-720.webp') }} 720w, {{ asset('img/landing-v3/dashboard-light-1440.webp') }} 1440w" imagesizes="(max-width: 640px) 80vw, 30rem" fetchpriority="high">

    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/css/landing-v3.css', 'resources/js/landing-v3.js'])
</head>
<body @auth data-authenticated @endauth class="v3 flex min-h-screen flex-col bg-surface antialiased">
    {{-- Pular para o conteúdo: a primeira parada do teclado numa página cujo
         herói é grande e cheio de objetos decorativos. --}}
    <a href="#conteudo" class="sr-only rounded-full bg-brand px-4 py-2 text-brand-foreground focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50">{{ __('landing_v3.a11y.skip') }}</a>

    <x-site-header variant="floating" />

    <main id="conteudo">
        @include('landing-v3.hero')
        @include('landing-v3.components')
        @include('landing-v3.how')
        @include('landing-v3.security')
        @include('landing-v3.sky-footer')
    </main>

    <x-flash-toast />
</body>
</html>
