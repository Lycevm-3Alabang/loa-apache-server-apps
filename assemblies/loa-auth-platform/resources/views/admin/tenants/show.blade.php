@extends('layouts.admin')

@section('title', $tenant->name . ' | LOA Admin')
@section('content')
    @include('admin.partials.breadcrumbs', ['items' => [
        ['label' => 'Tenants', 'url' => route('admin.tenants')],
        ['label' => $tenant->name],
    ]])
    <div class="page-header">
        <div>
            <h1>{{ $tenant->name }}</h1>
            <p>Tenant detail and membership management.</p>
        </div>
    </div>

    {{-- Tenant info --}}
    <div class="detail-card">
        <h2>Tenant details</h2>
        <div class="detail-grid">
            <div class="detail-field">
                <label>Slug</label>
                <span>{{ $tenant->slug }}</span>
            </div>
            <div class="detail-field">
                <label>Status</label>
                <span><span class="badge badge-{{ $tenant->status === 'active' ? 'active' : 'disabled' }}">{{ $tenant->status }}</span></span>
            </div>
            <div class="detail-field">
                <label>App URL</label>
                <span>{{ $tenant->app_url ?? '—' }}</span>
            </div>
            <div class="detail-field">
                <label>Dev App URL</label>
                <span>{{ $tenant->dev_app_url ?? '—' }}</span>
            </div>
            <div class="detail-field">
                <label>Redirect Origins</label>
                <span>{{ $tenant->redirect_origins ? implode(', ', $tenant->redirect_origins) : '—' }}</span>
            </div>
            <div class="detail-field">
                <label>Dev Redirect Origins</label>
                <span>{{ $tenant->dev_redirect_origins ? implode(', ', $tenant->dev_redirect_origins) : '—' }}</span>
            </div>
            <div class="detail-field">
                <label>Members</label>
                <span>{{ $tenant->users_count }}</span>
            </div>
            <div class="detail-field">
                <label>Created</label>
                <span>{{ $tenant->created_at?->format('M j, Y g:i A') ?? '—' }}</span>
            </div>
        </div>

        <div class="quick-actions" aria-label="Tenant actions">
            <a class="action-tile" href="{{ route('admin.tenants.edit', $tenant) }}">
                <span class="tile-icon tile-slate" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
                </span>
                <span class="tile-body">
                    <strong>Edit tenant</strong>
                    <small>Name, URLs, and redirect origins</small>
                </span>
                <span class="tile-chevron" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </span>
            </a>
            <a class="action-tile" href="{{ route('admin.tenants.groups', $tenant) }}">
                <span class="tile-icon tile-info" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </span>
                <span class="tile-body">
                    <strong>Manage groups</strong>
                    <small>Groups, permissions, and members</small>
                </span>
                <span class="tile-chevron" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </span>
            </a>
            <a class="action-tile" href="{{ route('admin.tenants.endpoints.manage', $tenant) }}">
                <span class="tile-icon tile-violet" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                </span>
                <span class="tile-body">
                    <strong>Manage endpoints</strong>
                    <small>Endpoint catalog and access levels</small>
                </span>
                <span class="tile-chevron" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </span>
            </a>
            <a class="action-tile" href="{{ route('admin.tenants.access-config.import', $tenant) }}">
                <span class="tile-icon tile-brand" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                </span>
                <span class="tile-body">
                    <strong>Import/Export Config</strong>
                    <small>Transfer groups, grants, overrides</small>
                </span>
                <span class="tile-chevron" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </span>
            </a>
        </div>

        <div style="margin-top:1.25rem;">
            <form method="post" action="{{ route('admin.tenants.status', $tenant) }}" style="display:flex;gap:0.5rem;align-items:center;">
                @csrf
                @if ($tenant->status === 'active')
                    <input type="hidden" name="status" value="suspended">
                    <button class="button button-soft-danger" type="submit" onclick="return confirm('Suspend this tenant?');">Suspend tenant</button>
                @else
                    <input type="hidden" name="status" value="active">
                    <button class="button button-soft-success" type="submit">Activate tenant</button>
                @endif
            </form>
        </div>
    </div>

    {{-- Members --}}
    <div class="detail-card">
        <div class="section-header">
            <h2>Members ({{ $tenant->users_count }})</h2>
        </div>

        {{-- Add member --}}
        @if ($nonMembers->isNotEmpty())
            <form method="post" action="{{ route('admin.tenants.members', $tenant) }}" class="inline-form" style="margin-bottom:1.25rem;">
                @csrf
                <input type="hidden" name="action" value="add">
                <div class="field">
                    <select name="user_id" required style="height:2.5rem;padding:0.5rem 0.75rem;border:1.5px solid var(--border);border-radius:var(--radius-xl);background:var(--surface-secondary);font-family:inherit;font-size:0.875rem;">
                        <option value="">Select a user…</option>
                        @foreach ($nonMembers as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                        @endforeach
                    </select>
                </div>
                <button class="button button-neutral" type="submit">Add to tenant</button>
            </form>
        @endif

        {{-- Members list --}}
        <div class="table-wrap">
            @if ($members->isEmpty())
                <div class="empty-state">No members yet. Add users to this tenant above.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Status</th>
                            <th>Group Memberships</th>
                            <th>Joined</th>
                            <th class="row-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($members as $member)
                            <tr>
                                <td class="cell-user">
                                    <strong>{{ $member->name }}</strong>
                                    <span>{{ $member->email }}</span>
                                </td>
                                <td><span class="badge badge-{{ $member->status }}">{{ $member->status }}</span></td>
                                <td class="muted">
                                    @if ($member->userGroups->where('tenant_id', $tenant->id)->isNotEmpty())
                                        @foreach ($member->userGroups->where('tenant_id', $tenant->id) as $mGroup)
                                            <a href="{{ route('admin.tenants.group.show', [$tenant, $mGroup]) }}">{{ $mGroup->name }}</a><br>
                                        @endforeach
                                    @else
                                        <span class="muted">None</span>
                                    @endif
                                </td>
                                <td class="muted">{{ $member->pivot->created_at?->format('M j, Y') ?? '—' }}</td>
                                <td class="row-actions">
                                    <form method="post" action="{{ route('admin.tenants.members', $tenant) }}">
                                        @csrf
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="user_id" value="{{ $member->id }}">
                                        <a class="button button-link button-danger" role="button" href="#" onclick="event.preventDefault(); if (confirm('Remove this user from the tenant?')) this.closest('form').submit();">Remove</a>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="pagination">
            <span>Showing {{ $members->firstItem() ?? 0 }}–{{ $members->lastItem() ?? 0 }} of {{ $members->total() }}</span>
            {{ $members->links() }}
        </div>
    </div>
@endsection
