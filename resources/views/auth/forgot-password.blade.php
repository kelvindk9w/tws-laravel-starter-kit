@extends('layouts.auth')

@section('title', __('auth.ui.forgot_title'))

@section('content')
    <h1>{{ __('auth.ui.forgot_title') }}</h1>
    <p>{{ __('auth.ui.forgot_subtitle') }}</p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <label for="email">{{ __('auth.ui.email') }}</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">

        <button type="submit">{{ __('auth.ui.forgot_submit') }}</button>
    </form>

    <p class="links">
        <a href="{{ route('login') }}">{{ __('auth.ui.login_link') }}</a>
    </p>
@endsection
