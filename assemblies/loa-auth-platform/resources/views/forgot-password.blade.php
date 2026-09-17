@extends('layouts.auth')

@section('title', 'Recover your account | Lyceum of Alabang')
@section('eyebrow', 'Account recovery')
@section('heading', 'Reset your password')
@section('intro', 'Enter your account email and we will send a secure reset link if the account exists.')

@section('content')
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;width:100%;">
        <div style="width:100%;max-width:360px;">
            <div style="margin-left:2rem;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--brand-600)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 9.9-2"/>
                </svg>
            </div>
            <form class="auth-form" method="post" action="{{ url('/forgot-password') }}">
                @csrf
                <input type="hidden" name="redirect" value="{{ old('redirect', $redirect) }}">

                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="you@lyceumalabang.edu.ph">
                    <span class="field-hint">The link will be valid for 60 minutes.</span>
                </div>

                <button class="button" type="submit">Send recovery link</button>
            </form>

            @if (!empty($redirect))
                <a class="back-link" href="{{ $redirect }}">Back to referrer</a>
            @else
                <a class="back-link" href="{{ route('login') }}">Back to sign in</a>
            @endif
        </div>
    </div>
@endsection
