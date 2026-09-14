@extends('layouts.auth')

@section('title', 'Activate Certificate Account | LOA Platform')
@section('eyebrow', 'Certificate Activation')
@section('heading', 'Activate your account')
@section('intro', 'A certificate has been issued in your name. Create your account to access it.')

@section('content')
    @if ($errors->any())
        <div class="alert alert-error" role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div style="background: var(--surface-secondary); border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 1.25rem; margin-bottom: 1.5rem;">
        <div style="display: grid; gap: 0.5rem; font-size: 0.875rem;">
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Certificate</span>
                <span style="font-weight: 600; font-family: monospace;">{{ $certificateNumber }}</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Name</span>
                <span style="font-weight: 500;">{{ $recipientName }}</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Email</span>
                <span style="font-weight: 500;">{{ $email }}</span>
            </div>
        </div>
    </div>

    <form class="auth-form" method="post" action="{{ route('set-password.cert.process') }}">
        @csrf
        <input type="hidden" name="cert" value="{{ $certificateNumber }}">
        <input type="hidden" name="email" value="{{ $email }}">

        <button class="button" type="submit">Create account &amp; set password</button>
    </form>

    <p class="back-link">Already have an account? <a href="{{ route('sso.login') }}">Sign in</a></p>
@endsection
