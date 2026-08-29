@extends('layouts.auth')

@section('title', 'Redirecting | LOA Platform')
@section('eyebrow', 'Leaving LOA Platform')
@section('heading', 'Ready to redirect')
@section('intro', 'Click the button below to continue to the application.')

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
        <a class="button" href="{{ $full_url }}" style="display:inline-flex;width:auto;text-decoration:none;">
            Continue to application
        </a>
        @if($is_admin)
            <p style="margin-top:1rem;">
                <a href="{{ config('app.url') }}" style="font-size:0.8125rem;color:var(--text-muted);text-decoration:underline;">
                    Back to Admin Console
                </a>
            </p>
        @endif
    </div>

    @if(!$is_admin)
    <script>
        // Non-admins auto-redirect — no manual click needed.
        setTimeout(function () {
            window.location.href = @json($full_url);
        }, 300);
    </script>
    @endif

    <style>
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
    </style>
@endsection
