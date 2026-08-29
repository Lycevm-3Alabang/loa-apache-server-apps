@extends('layouts.auth')

@section('title', 'Access Denied | LOA Platform')
@section('eyebrow', 'Access Denied')
@section('heading', 'You don''t have access')
@section('intro', 'You don''t have access to this application. Contact your administrator.')

@section('content')
    <div style="text-align:center;padding:1.5rem 0;">
        <div style="margin-bottom:1.5rem;">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--danger)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="15" y1="9" x2="9" y2="15"/>
                <line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
        </div>
        <p style="color:var(--text-secondary);font-size:0.9375rem;line-height:1.6;margin:0 0 0.5rem;">
            Application:<br>
            <strong style="color:var(--text);">{{ $tenantName }}</strong>
        </p>
        <a href="{{ route('home') }}" class="button" style="display:inline-flex;width:auto;text-decoration:none;">
            Back to Dashboard
        </a>
    </div>
@endsection
