@extends('layouts.auth')

@section('title', 'Your apps | Lyceum of Alabang')
@section('eyebrow', 'LOA Platform')
@section('heading', 'Your applications')
@section('intro', 'Everything you have access to across the LOA digital campus.')

@section('content')
    @if ($tenants->isEmpty() && !$isAdmin)
        <p class="card-intro">You don't have access to any applications yet. Contact your administrator.</p>
    @else
        <style>
            .app-grid { display: grid; gap: 0.75rem; }
            .app-tile { display: flex; flex-direction: column; align-items: flex-start; gap: 0.25rem; width: 100%; padding: 1rem 1.1rem; border: 1.5px solid var(--border); border-radius: var(--radius-xl); background: var(--surface-secondary); color: var(--text); text-decoration: none; cursor: pointer; font-family: inherit; text-align: left; transition: border-color 160ms ease, box-shadow 160ms ease; }
            .app-tile:hover { border-color: var(--brand-500); box-shadow: 0 0 0 4px rgba(252, 202, 19, 0.15); }
            .app-name { font-size: 0.9375rem; font-weight: 600; }
            .app-url { color: var(--text-muted); font-size: 0.8125rem; overflow-wrap: anywhere; }
        </style>

        <div class="app-grid">
            @foreach ($tenants as $tenant)
                <form method="post" action="{{ route('portal.go', $tenant->id) }}">
                    @csrf
                    <button type="submit" class="app-tile">
                        <span class="app-name">{{ $tenant->name }}</span>
                        <span class="app-url">{{ parse_url($tenant->effectiveAppUrl() ?? '', PHP_URL_HOST) }}</span>
                    </button>
                </form>
            @endforeach

            @if ($isAdmin)
                <a class="app-tile" href="{{ route('admin.users') }}">
                    <span class="app-name">Auth Admin Console</span>
                    <span class="app-url">User &amp; tenant management</span>
                </a>
            @endif

            <a class="app-tile" href="{{ route('portal.account') }}">
                <span class="app-name">Account</span>
                <span class="app-url">Profile &amp; password</span>
            </a>
        </div>
    @endif
@endsection
