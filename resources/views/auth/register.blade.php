@extends('layouts.auth')

@section('title', __('auth.ui.register_title'))

@section('content')
    <h1>{{ __('auth.ui.register_title') }}</h1>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <label for="name">{{ __('auth.ui.name') }}</label>
        <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">

        <label for="email">{{ __('auth.ui.email') }}</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">

        <label for="password">{{ __('auth.ui.password') }}</label>
        <input id="password" type="password" name="password" required autocomplete="new-password">

        <label for="password_confirmation">{{ __('auth.ui.password_confirmation') }}</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">

        <button type="submit">{{ __('auth.ui.register_submit') }}</button>
    </form>

    <p class="links">
        <a href="{{ route('login') }}">{{ __('auth.ui.login_link') }}</a>
    </p>
@endsection
