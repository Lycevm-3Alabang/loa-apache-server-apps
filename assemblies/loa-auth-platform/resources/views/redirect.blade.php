@extends('layouts.auth')

@section('title', 'Redirecting | LOA Platform')
@section('eyebrow', 'Leaving LOA Platform')
@section('heading', 'Redirecting...')
@section('intro', 'You are being redirected to the application.')

@section('content')
    <div style="text-align:center;padding:1.5rem 0;">
        <div style="margin-bottom:1.5rem;">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--brand-600)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="animation:pulse 1.5s ease-in-out infinite;">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                <polyline points="15 3 21 3 21 9"/>
                <line x1="10" y1="14" x2="21" y2="3"/>
            </svg>
        </div>
        <p style="color:var(--text-secondary);font-size:0.9375rem;line-height:1.6;margin:0 0 0.5rem;">
            Redirecting to<br>
            <strong style="color:var(--text);word-break:break-all;">{{ $url }}</strong>
        </p>
        <p style="color:var(--text-muted);font-size:0.8125rem;margin:0 0 1.5rem;">
            If you are not redirected automatically,
        </p>
        <a class="button" href="{{ $full_url }}" style="display:inline-flex;width:auto;text-decoration:none;">
            Click here to continue
        </a>
    </div>

    <script>
        console.log('[SSO Redirect] Target URL:', @json($full_url));
        console.log('[SSO Redirect] URL only:', @json($url));

        setTimeout(function() {
            console.log('[SSO Redirect] Navigating to:', @json($full_url));
            window.location.href = {!! json_encode($full_url) !!};
        }, 2000);
    </script>

    <style>
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
    </style>
@endsection
