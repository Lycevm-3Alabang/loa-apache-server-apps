@extends('layouts.auth')

@section('title', 'Choose a new password | Lyceum of Alabang')
@section('eyebrow', 'Secure password reset')
@section('heading', 'Choose a new password')
@section('intro', 'Create a strong password for your LOA account. Your existing sessions will be signed out after this change.')

@section('content')
    <form class="auth-form" method="post" action="{{ url('/reset-password') }}">
        @csrf
        <input type="hidden" name="token" value="{{ old('token', $token) }}">

        <div class="field">
            <label for="email">Account email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" readonly required autocomplete="email">
        </div>

        <div class="field">
            <label for="password">New password</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" placeholder="Create a new password">
            <span class="password-hint">At least 8 characters with uppercase, lowercase, and a number.</span>
        </div>

        <div class="field">
            <label for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" placeholder="Repeat your new password">
        </div>

        <button class="button" type="submit">Update password</button>
    </form>
@endsection
