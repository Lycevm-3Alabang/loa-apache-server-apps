@extends('layouts.auth')

@section('title', 'Sign in | LOA Platform')
@section('eyebrow', 'Welcome back')
@section('heading', 'Sign in to LOA')
@section('intro', 'Use your LOA account to continue to the platform you need.')

@section('content')
    <form class="auth-form" method="post" action="{{ url('/login') }}">
        @csrf
        <input type="hidden" name="redirect" value="{{ old('redirect', $redirect) }}">

        <div class="field">
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="you@loa.edu.ph">
        </div>

        <div class="field">
            <div class="form-row">
                <label for="password">Password</label>
                <a class="form-link" href="{{ route('password.forgot') }}">Forgot password?</a>
            </div>
            <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Enter your password">
        </div>

        <button class="button" type="submit">
            <span>Continue securely</span>
            <span class="button-arrow" aria-hidden="true">&rarr;</span>
        </button>
    </form>
@endsection
