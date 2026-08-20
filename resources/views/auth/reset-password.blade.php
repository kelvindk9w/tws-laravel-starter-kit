@extends('layouts.auth')

@section('title', __('auth.ui.reset_title'))

@section('content')
    <h1>{{ __('auth.ui.reset_title') }}</h1>

    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <label for="email">{{ __('auth.ui.email') }}</label>
        <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autofocus autocomplete="username">

        <label for="password">{{ __('auth.ui.new_password') }}</label>
        <input id="password" type="password" name="password" required autocomplete="new-password">

        <label for="password_confirmation">{{ __('auth.ui.password_confirmation') }}</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">

        <button type="submit">{{ __('auth.ui.reset_submit') }}</button>
    </form>
@endsection
