@extends('layouts.auth')

@section('title', 'Account | Lyceum of Alabang')
@section('eyebrow', 'LOA Platform')
@section('heading', 'Your account')
@section('intro', 'Your profile details on the LOA identity platform.')

@section('content')
    <style>
        .account-list { display: grid; gap: 0.75rem; margin: 0; padding: 0; list-style: none; }
        .account-list li { display: flex; flex-direction: column; gap: 0.2rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border); }
        .account-list dt, .account-label { color: var(--text-muted); font-size: 0.8125rem; }
        .account-value { font-weight: 600; overflow-wrap: anywhere; }
    </style>

    <dl class="account-list">
        <li>
            <dt>Name</dt>
            <dd class="account-value">{{ $portalUser->name }}</dd>
        </li>
        <li>
            <dt>Email</dt>
            <dd class="account-value">{{ $portalUser->email }}</dd>
        </li>
        <li>
            <dt>Status</dt>
            <dd class="account-value">{{ ucfirst($portalUser->status) }}</dd>
        </li>
    </dl>

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
        </div>

        <div class="field">
            <label for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" placeholder="Repeat your new password">
        </div>

        <button class="button" type="submit">Update password</button>
    </form>

    <p class="back-link"><a href="{{ route('portal.launcher') }}">Back to your applications</a></p>
@endsection
