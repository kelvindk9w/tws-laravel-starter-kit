@extends('layouts.auth')

@section('title', __('auth.ui.login_title'))

@section('content')
    <h1 class="mb-6 font-display text-xl font-semibold tracking-[-0.01em]">{{ __('auth.ui.login_title') }}</h1>

    @php($demo = config('ui.demo_login'))

    @if ($demo['enabled'])
        <x-alert type="info" class="mb-4">
            {{ __('auth.ui.demo_notice') }}<br>
            <strong>{{ __('auth.ui.demo_credentials') }}:</strong>
            {{ $demo['email'] }} / {{ $demo['password'] }}
        </x-alert>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <x-input :label="__('auth.ui.email')" name="email" type="email"
                 :value="old('email', $demo['enabled'] ? $demo['email'] : '')"
                 :error="field_error('email')"
                 required autofocus autocomplete="username" />

        <x-input :label="__('auth.ui.password')" name="password" type="password"
                 :value="$demo['enabled'] ? $demo['password'] : ''"
                 :error="field_error('password')"
                 required autocomplete="current-password" />

        <x-checkbox :label="__('auth.ui.remember_me')" name="remember" />

        <x-button type="submit" class="w-full">{{ __('auth.ui.login_submit') }}</x-button>
    </form>

    <p class="mt-4 text-center text-sm text-gray-500 dark:text-gray-400">
        <a href="{{ route('password.request') }}" class="text-(--brand) hover:underline">{{ __('auth.ui.forgot_password') }}</a>
        ·
        <a href="{{ route('register') }}" class="text-(--brand) hover:underline">{{ __('auth.ui.register_link') }}</a>
    </p>
@endsection
