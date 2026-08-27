@extends('layouts.admin')

@section('title', 'Dashboard | LOA Platform')

@section('content')
    <style>
        /* Apps-first launcher (dashboard-account.md v1.2 D13/D15) */
        .launcher-grid { display: grid; gap: 0.9rem; grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr)); }

        .app-card {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            width: 100%;
            padding: 1rem 1.1rem;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-xl);
            background: var(--surface-secondary);
            color: var(--text);
            text-decoration: none;
            cursor: pointer;
            font-family: inherit;
            text-align: left;
            transition: border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
        }

        .app-card:hover {
            border-color: var(--brand-500);
            box-shadow: 0 0 0 4px rgba(252, 202, 19, 0.15), 0 6px 16px rgba(2, 6, 23, 0.08);
            color: var(--text);
            text-decoration: none;
            transform: translateY(-1px);
        }

        .app-card:focus-visible { outline: 3px solid var(--brand-500); outline-offset: 2px; }
        .app-card:active { transform: translateY(0); }

        .app-initial {
            display: grid;
            place-items: center;
            flex-shrink: 0;
            width: 2.6rem;
            height: 2.6rem;
            border-radius: var(--radius-xl);
            background: rgba(252, 202, 19, 0.18);
            color: var(--brand-700);
            font-size: 1.05rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .app-meta { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; flex: 1; }
        .app-name { font-size: 0.9375rem; font-weight: 600; overflow-wrap: anywhere; }
        .app-host { color: var(--text-muted); font-size: 0.8125rem; overflow-wrap: anywhere; }

        .launcher-open {
            flex-shrink: 0;
            color: var(--brand-700);
            font-size: 0.8125rem;
            font-weight: 700;
        }

        /* Single-membership emphasis (v1.2 §4.3) */
        .launcher-single .launcher-grid { grid-template-columns: 1fr; }
        .launcher-single .app-card { padding: 1.75rem; }
        .launcher-single .app-initial { width: 3.25rem; height: 3.25rem; font-size: 1.35rem; }
        .launcher-single .app-name { font-size: 1.125rem; }
        .launcher-single .app-host { font-size: 0.875rem; }

        /* Empty state (v1.2 §4.4) */
        .empty-state {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
            max-width: 34rem;
            padding: 2rem;
            border: 1.5px dashed var(--border-strong, var(--border));
            border-radius: var(--radius-xl);
            background: var(--surface-secondary);
        }

        .empty-state h2 { margin: 0; font-size: 1rem; font-weight: 700; }
        .empty-state p { margin: 0; color: var(--text-muted); font-size: 0.875rem; }
    </style>

    @php
        $firstName = explode(' ', trim($portalUser->name ?? ''))[0] ?? '';
    @endphp

    <div class="page-header">
        <div>
            <h1>{{ $firstName !== '' ? 'Welcome back, '.$firstName : 'Welcome back' }}</h1>
            <p>Choose an application to open.</p>
        </div>
    </div>

    @if ($tenants->isEmpty())
        {{-- Plain isEmpty gate (v1.2 D13): admins with zero memberships land
             here too — no console tile steers them away anymore. --}}
        <div class="empty-state">
            <h2>No applications yet</h2>
            <p>You don't have access to any applications yet. Once your administrator enrolls you, your apps will appear here.</p>
        </div>
    @elseif ($tenants->count() === 1)
        @php
            $tenant = $tenants->first();
            $host = parse_url($tenant->effectiveAppUrl() ?? '', PHP_URL_HOST);
        @endphp
        <div class="launcher-single">
            <div class="launcher-grid">
                <form method="post" action="{{ route('portal.go', $tenant->id) }}">
                    @csrf
                    <button type="submit" class="app-card" aria-label="Continue to {{ $tenant->name }}">
                        <span class="app-initial" aria-hidden="true">{{ mb_substr($tenant->name, 0, 1) }}</span>
                        <span class="app-meta">
                            <span class="app-name">{{ $tenant->name }}</span>
                            @if ($host)
                                <span class="app-host">{{ $host }}</span>
                            @endif
                        </span>
                        <span class="launcher-open">Continue &rarr;</span>
                    </button>
                </form>
            </div>
        </div>
    @else
        <div class="launcher-grid">
            @foreach ($tenants as $tenant)
                @php $host = parse_url($tenant->effectiveAppUrl() ?? '', PHP_URL_HOST); @endphp
                <form method="post" action="{{ route('portal.go', $tenant->id) }}">
                    @csrf
                    <button type="submit" class="app-card">
                        <span class="app-initial" aria-hidden="true">{{ mb_substr($tenant->name, 0, 1) }}</span>
                        <span class="app-meta">
                            <span class="app-name">{{ $tenant->name }}</span>
                            @if ($host)
                                <span class="app-host">{{ $host }}</span>
                            @endif
                        </span>
                    </button>
                </form>
            @endforeach
        </div>
    @endif

    @if ($isAdmin)
        @include('admin.partials.admin-zone', [
            'adminStats' => $adminStats ?? [],
            'adminAttention' => $adminAttention ?? [],
            'adminActivity' => $adminActivity ?? [],
            'adminZoneFailed' => $adminZoneFailed ?? true,
        ])
    @endif
@endsection
