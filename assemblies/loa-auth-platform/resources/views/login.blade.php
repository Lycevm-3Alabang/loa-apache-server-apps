@extends('layouts.auth')

@section('title', 'Sign in | Lyceum of Alabang')
@section('eyebrow', 'Welcome back')
@section('heading', 'Sign in to your account')
@section('intro', 'Access the LOA digital campus with your credentials.')

@section('content')
    <form class="auth-form" method="post" action="{{ url('/login') }}">
        @csrf
        <input type="hidden" name="redirect" value="{{ old('redirect', $redirect) }}">

        <div class="field">
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="you@lyceumalabang.edu.ph">
        </div>

        <div class="field">
            <div class="form-row">
                <label for="password">Password</label>
                <a class="form-link" href="{{ route('password.forgot') }}">Forgot password?</a>
            </div>
            <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Enter your password">
        </div>

        <button class="button" type="submit">Sign in</button>
    </form>

    <p class="back-link">Need help? Contact your administrator.</p>
@endsection
