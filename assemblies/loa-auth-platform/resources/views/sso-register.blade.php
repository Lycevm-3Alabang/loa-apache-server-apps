@extends('layouts.auth')

@section('title', 'Sign up | Lyceum of Alabang')
@section('eyebrow', 'Get started')
@section('heading', 'Create your LOA account')
@section('intro', 'Registration is restricted to LOA email addresses.')

@section('content')
    <form class="auth-form" method="post" action="{{ url('/sso/register') }}">
        @csrf

        <div class="field">
            <label for="name">Full name</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name" placeholder="Juan Dela Cruz">
        </div>

        <div class="field">
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" placeholder="you@lyceumalabang.edu.ph">
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" placeholder="Min. 8 characters">
            <span class="password-hint">At least 8 characters with one uppercase, one lowercase, and one number.</span>
        </div>

        <div class="field">
            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" placeholder="Re-enter your password">
        </div>

        <button class="button" type="submit">Create account</button>
    </form>

    <p class="back-link">Already have an account? <a href="{{ route('sso.login') }}">Sign in</a></p>
@endsection
