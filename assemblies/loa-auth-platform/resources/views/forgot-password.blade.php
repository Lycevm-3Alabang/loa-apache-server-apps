@extends('layouts.auth')

@section('title', 'Recover your account | Lyceum of Alabang')
@section('eyebrow', 'Account recovery')
@section('heading', 'Reset your password')
@section('intro', 'Enter your account email and we will send a secure reset link if the account exists.')

@section('content')
    <form class="auth-form" method="post" action="{{ url('/forgot-password') }}">
        @csrf

        <div class="field">
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="you@lyceumalabang.edu.ph">
            <span class="field-hint">The link will be valid for 60 minutes.</span>
        </div>

        <button class="button" type="submit">Send recovery link</button>
    </form>

    <a class="back-link" href="{{ route('login') }}">Back to sign in</a>
@endsection
