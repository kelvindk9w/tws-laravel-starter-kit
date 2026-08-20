@extends('layouts.auth')

@section('title', __('auth.ui.login_title'))

@section('content')
    <h1>{{ __('auth.ui.login_title') }}</h1>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <label for="email">{{ __('auth.ui.email') }}</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">

        <label for="password">{{ __('auth.ui.password') }}</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">

        <label>
            <input type="checkbox" name="remember" value="1"> {{ __('auth.ui.remember_me') }}
        </label>

        <button type="submit">{{ __('auth.ui.login_submit') }}</button>
    </form>

    <p class="links">
        <a href="{{ route('password.request') }}">{{ __('auth.ui.forgot_password') }}</a>
        ·
        <a href="{{ route('register') }}">{{ __('auth.ui.register_link') }}</a>
    </p>
@endsection
