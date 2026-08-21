@extends('layouts.auth')

@section('title', __('auth.ui.reset_title'))

@section('content')
    <h1 class="mb-6 font-display text-xl font-semibold tracking-[-0.01em]">{{ __('auth.ui.reset_title') }}</h1>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <x-input :label="__('auth.ui.email')" name="email" type="email" :value="old('email', $email)" required autofocus autocomplete="username" />
        <x-input :label="__('auth.ui.new_password')" name="password" type="password" required autocomplete="new-password" />
        <x-input :label="__('auth.ui.password_confirmation')" name="password_confirmation" type="password" required autocomplete="new-password" />

        <x-button type="submit" class="w-full">{{ __('auth.ui.reset_submit') }}</x-button>
    </form>
@endsection
