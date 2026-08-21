@extends('layouts.auth')

@section('title', 'Sign in | Lyceum of Alabang')
@section('eyebrow', 'Welcome back')
@section('heading', 'Sign in to LOA Platform')
@section('intro', 'Access your LOA application with your credentials.')

@section('content')
    <form class="auth-form" method="post" action="{{ url('/sso/login') }}">
        @csrf
        <input type="hidden" name="redirect" value="{{ old('redirect', $redirect) }}">

        <div class="field">
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="you@lyceumalabang.edu.ph">
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Enter your password">
        </div>

        <button class="button" type="submit">Sign in</button>
    </form>

    <p class="back-link">Need help? Contact your administrator.</p>

    <script>
        console.log('[SSO Login] Redirect target:', @json($redirect));
        console.log('[SSO Login] Form action:', document.querySelector('form').action);

        document.querySelector('form').addEventListener('submit', function(e) {
            var email = document.getElementById('email').value;
            console.log('[SSO Login] Submitting form for:', email);
            console.log('[SSO Login] Redirect hidden field:', document.querySelector('input[name="redirect"]').value);
        });
    </script>
@endsection
