@extends('layouts.auth')

@section('title', __('auth.ui.transaction_password_title'))

@section('content')
    <h1>{{ __('auth.ui.transaction_password_title') }}</h1>
    <p>{{ __('auth.ui.transaction_password_subtitle') }}</p>

    <form method="POST" action="{{ route('transaction-password.update') }}">
        @csrf
        @method('PUT')

        @if (auth()->user()->hasTransactionPassword())
            <label for="current_transaction_password">{{ __('auth.ui.current_transaction_password') }}</label>
            <input id="current_transaction_password" type="password" name="current_transaction_password" required autocomplete="off">
        @endif

        <label for="transaction_password">{{ __('auth.ui.new_transaction_password') }}</label>
        <input id="transaction_password" type="password" name="transaction_password" required autocomplete="off">

        <label for="transaction_password_confirmation">{{ __('auth.ui.password_confirmation') }}</label>
        <input id="transaction_password_confirmation" type="password" name="transaction_password_confirmation" required autocomplete="off">

        <button type="submit">{{ __('auth.ui.save') }}</button>
    </form>
@endsection
