@extends('layouts.auth')

@section('title', 'Sign up | Lyceum of Alabang')
@section('eyebrow', 'Get started')
@section('heading', 'Create your account')
@section('intro', 'Join the LOA digital campus to access consultations, certificates, and more.')

@section('content')
    <form class="auth-form" method="post" action="{{ url('/register') }}">
        @csrf

        <div class="field">
            <label for="name">Full name</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name" placeholder="Juan Dela Cruz">
        </div>

        <div class="field">
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" placeholder="you@loa.edu.ph">
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

    <p class="back-link">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
@endsection
