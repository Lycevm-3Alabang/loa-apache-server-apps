{{-- Platform administration zone (admin-dashboard-home.md §4) — admin-gated --}}
@php
    $stats = $adminStats ?? [];
    $attention = $adminAttention ?? [];
    $activity = $adminActivity ?? [];
    $zoneFailed = $adminZoneFailed ?? true;
@endphp

<style>
    .admin-zone { margin-top: 2.5rem; padding-top: 2rem; border-top: 1.5px solid var(--border); }
    .admin-zone-title { margin: 0 0 1.25rem; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--text-muted); }

    .stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.75rem; margin-bottom: 1.75rem; }
    .stat-card {
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
        padding: 1rem 1.1rem;
        border: 1.5px solid var(--border);
        border-radius: var(--radius-xl);
        background: var(--surface-secondary);
        text-decoration: none;
        color: var(--text);
        transition: border-color 140ms ease, box-shadow 140ms ease;
    }
    .stat-card:hover { border-color: var(--brand-500); box-shadow: var(--shadow-sm); text-decoration: none; color: var(--text); }
    .stat-card .stat-value { font-size: 1.5rem; font-weight: 700; line-height: 1.2; font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif; }
    .stat-card .stat-label { font-size: 0.8125rem; font-weight: 600; color: var(--text-secondary); }
    .stat-card .stat-sub { font-size: 0.75rem; color: var(--text-muted); }

    .attention-section { margin-bottom: 1.75rem; }
    .attention-list { display: flex; flex-direction: column; gap: 0.5rem; }
    .attention-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.75rem 1rem;
        border: 1.5px solid var(--border);
        border-radius: var(--radius-xl);
        background: var(--surface-secondary);
    }
    .attention-item.aggregate { border-style: dashed; color: var(--text-muted); font-size: 0.8125rem; }
    .attention-item .att-copy { font-size: 0.875rem; font-weight: 500; }
    .attention-item .att-action {
        flex-shrink: 0;
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--brand-600);
        text-decoration: none;
        white-space: nowrap;
    }
    .attention-item .att-action:hover { text-decoration: underline; }

    .activity-section { margin-bottom: 1.75rem; }
    .activity-table { width: 100%; border-collapse: collapse; font-size: 0.8125rem; }
    .activity-table th {
        padding: 0.5rem 0.75rem;
        text-align: left;
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--text-muted);
        letter-spacing: 0.04em;
        text-transform: uppercase;
        border-bottom: 1px solid var(--border);
    }
    .activity-table td { padding: 0.5rem 0.75rem; border-bottom: 1px solid var(--border); vertical-align: middle; }
    .activity-table tr:last-child td { border-bottom: none; }
    .activity-table .action-badge {
        display: inline-block;
        padding: 0.15rem 0.45rem;
        border-radius: 999px;
        font-size: 0.6875rem;
        font-weight: 600;
        background: var(--surface-secondary);
        color: var(--text-secondary);
    }
    .activity-table a { font-weight: 500; }
    .activity-empty { padding: 1rem; text-align: center; color: var(--text-muted); font-size: 0.8125rem; }
    .activity-footer { padding: 0.625rem 0.75rem; border-top: 1px solid var(--border); text-align: right; }

    .quick-row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
    .quick-row a {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.5rem 0.875rem;
        border: 1.5px solid var(--border);
        border-radius: var(--radius-xl);
        background: var(--surface-secondary);
        color: var(--text-secondary);
        font-size: 0.8125rem;
        font-weight: 600;
        text-decoration: none;
        transition: border-color 140ms, background 140ms, color 140ms;
    }
    .quick-row a:hover { border-color: var(--brand-500); background: var(--surface); color: var(--text); text-decoration: none; }

    .zone-degrade { padding: 1rem; color: var(--text-muted); font-size: 0.875rem; }

    @media (max-width: 720px) {
        .stat-grid { grid-template-columns: repeat(2, 1fr); }
        .attention-item { flex-direction: column; align-items: flex-start; gap: 0.375rem; }
    }
</style>

<section class="admin-zone" aria-label="Platform administration">
    <h2 class="admin-zone-title">Platform administration</h2>

    @if ($zoneFailed)
        <p class="zone-degrade">Admin metrics temporarily unavailable.</p>
    @else
        {{-- §4.1 Platform pulse — stat strip --}}
        <div class="stat-grid">
            <a href="{{ route('admin.users') }}" class="stat-card">
                <span class="stat-value">{{ $stats['users_total'] }}</span>
                <span class="stat-label">Users</span>
                @if ($stats['users_pending'] > 0 || $stats['users_disabled'] > 0)
                    <span class="stat-sub">{{ $stats['users_pending'] }} pending &middot; {{ $stats['users_disabled'] }} disabled</span>
                @endif
            </a>
            <a href="{{ route('admin.tenants') }}" class="stat-card">
                <span class="stat-value">{{ $stats['tenants_active'] }}</span>
                <span class="stat-label">Tenants</span>
                @if ($stats['tenants_inactive'] > 0)
                    <span class="stat-sub">{{ $stats['tenants_inactive'] }} inactive</span>
                @endif
            </a>
            <div class="stat-card">
                <span class="stat-value">{{ $stats['active_sessions'] }}</span>
                <span class="stat-label">Active sessions</span>
                <span class="stat-sub">users with valid tokens</span>
            </div>
            <a href="{{ route('admin.tenants') }}" class="stat-card">
                <span class="stat-value">{{ $stats['memberships'] }}</span>
                <span class="stat-label">Memberships</span>
            </a>
        </div>

        {{-- §4.2 Needs attention --}}
        @if (!empty($attention))
            <div class="attention-section">
                <div class="attention-list">
                    @foreach ($attention as $item)
                        @if ($item['aggregate'] ?? false)
                            <div class="attention-item aggregate">
                                <span class="att-copy">{{ $item['copy'] }}</span>
                            </div>
                        @else
                            <div class="attention-item">
                                <span class="att-copy">{{ $item['copy'] }}</span>
                                @if ($item['url'])
                                    <a href="{{ $item['url'] }}" class="att-action">View &rarr;</a>
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        {{-- §4.3 Recent platform activity --}}
        <div class="activity-section">
            <table class="activity-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Actor</th>
                        <th>Action</th>
                        <th>Subject</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($activity as $row)
                        <tr>
                            <td>{{ $row['created_at']->diffForHumans() }}</td>
                            <td>{{ $row['actor_email'] }}</td>
                            <td><span class="action-badge">{{ str_replace('.', ' ', $row['action']) }}</span></td>
                            <td>
                                @if ($row['entity_type'] && $row['entity_id'])
                                    <a href="{{ $row['url'] }}">{{ $row['entity_type'] }}</a>
                                @else
                                    &mdash;
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="activity-empty">No recent activity.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            @if (!empty($activity))
                <div class="activity-footer">
                    <a href="{{ route('admin.audit-logs') }}">View all &rarr;</a>
                </div>
            @endif
        </div>

        {{-- §4.4 Quick actions --}}
        <div class="quick-row">
            <a href="{{ route('admin.users.create') }}">New user</a>
            <a href="{{ route('admin.tenants.create') }}">New tenant</a>
            <a href="{{ route('admin.groups') }}">Platform groups</a>
            <a href="{{ route('admin.users.import') }}">Import users</a>
            <a href="{{ route('admin.audit-logs') }}">Audit log</a>
        </div>
    @endif
</section>
