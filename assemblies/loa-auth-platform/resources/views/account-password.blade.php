@extends('layouts.auth')

@section('title', 'Change password | Lyceum of Alabang')
@section('eyebrow', 'LOA Platform')
@section('heading', 'Change password')
@section('intro', 'Set a new password for your LOA Platform account.')

@section('content')
    <form class="auth-form" method="post" action="{{ route('portal.account.password') }}">
        @csrf
        <div class="field">
            <label for="current_password">Current password</label>
            <input id="current_password" name="current_password" type="password" required autocomplete="current-password" placeholder="Enter your current password">
        </div>

        <div class="field">
            <label for="password">New password</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" placeholder="Create a new password">
            <span class="password-hint">At least 8 characters with uppercase, lowercase, and a number.</span>
            <span class="password-hint">Changing your password signs you out of all LOA applications.</span>
        </div>

        <div class="field">
            <label for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" placeholder="Repeat your new password">
        </div>

        <button class="button" type="submit">Update password</button>
    </form>

    <p class="back-link"><a href="{{ route('portal.account') }}">Back to your account</a></p>
@endsection
