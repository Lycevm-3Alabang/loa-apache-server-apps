@extends('layouts.admin')

@section('title', 'Dashboard | LOA Platform')

@section('content')
    <style>
        .profile-strip { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem; padding: 0.875rem 1rem; border: 1px solid var(--border); border-radius: var(--radius-xl); background: var(--surface-secondary); }
        .profile-id { display: flex; flex-direction: column; gap: 0.1rem; min-width: 0; flex: 1; }
        .profile-name { font-size: 0.9375rem; font-weight: 600; overflow-wrap: anywhere; }
        .profile-email { color: var(--text-muted); font-size: 0.8125rem; overflow-wrap: anywhere; }
        .status-badge { flex-shrink: 0; padding: 0.2rem 0.6rem; border: 1px solid #b8e1d5; border-radius: 999px; background: #effaf6; color: var(--success); font-size: 0.6875rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; }
        .status-badge.status-disabled { border-color: #edc5c0; background: #fff5f3; color: var(--danger); }
        .status-badge.status-pending { border-color: #f3e2ae; background: #fffaeb; color: var(--brand-700); }
        .manage-link { flex-shrink: 0; font-size: 0.8125rem; }

        .app-grid { display: grid; gap: 0.75rem; grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr)); }
        .app-tile { display: flex; flex-direction: column; align-items: flex-start; gap: 0.25rem; width: 100%; padding: 1rem 1.1rem; border: 1.5px solid var(--border); border-radius: var(--radius-xl); background: var(--surface-secondary); color: var(--text); text-decoration: none; cursor: pointer; font-family: inherit; text-align: left; transition: border-color 160ms ease, box-shadow 160ms ease; }
        .app-tile:hover { border-color: var(--brand-500); box-shadow: 0 0 0 4px rgba(252, 202, 19, 0.15); text-decoration: none; color: var(--text); }
        .app-name { font-size: 0.9375rem; font-weight: 600; }
        .app-url { color: var(--text-muted); font-size: 0.8125rem; overflow-wrap: anywhere; }
    </style>

    <div class="page-header">
        <div>
            <h1>Dashboard</h1>
            <p>Your applications and account, all in one place.</p>
        </div>
    </div>

    <div class="profile-strip">
        <div class="profile-id">
            <span class="profile-name">{{ $portalUser->name }}</span>
            <span class="profile-email">{{ $portalUser->email }}</span>
        </div>
        <span class="status-badge status-{{ $portalUser->status }}">{{ ucfirst($portalUser->status) }}</span>
        <a class="manage-link" href="{{ route('portal.account') }}">Manage account</a>
    </div>

    @if ($tenants->isEmpty() && !$isAdmin)
        <div class="panel">
            <p style="margin:0;">You don't have access to any applications yet. Contact your administrator.</p>
        </div>
    @else
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
        </div>
    @endif
@endsection
