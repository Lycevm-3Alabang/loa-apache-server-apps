@extends('layouts.auth')

@section('title', 'Activate your account | Lyceum of Alabang')
@section('eyebrow', 'Account activation')
@section('heading', 'Activate your account')
@section('intro', 'You were invited to the LOA digital campus. Set a password below to finish activating your account.')

@section('content')
    <form class="auth-form" method="post" action="{{ route('activate') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="field">
            <label for="email">Account email</label>
            <input id="email" name="email" type="email" value="{{ $email }}" readonly required autocomplete="email">
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required autofocus autocomplete="new-password" placeholder="Create a password">
            <span class="password-hint">At least 8 characters with uppercase, lowercase, and a number.</span>
        </div>

        <div class="field">
            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" placeholder="Repeat your new password">
        </div>

        <button class="button" type="submit">Activate account</button>
    </form>

    <p class="back-link">Already activated? <a href="{{ route('login') }}">Sign in</a></p>
@endsection
