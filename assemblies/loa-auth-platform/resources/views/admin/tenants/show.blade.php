@extends('layouts.admin')

@section('title', $tenant->name . ' | LOA Admin')
@section('content')
    <div class="page-header">
        <div>
            <h1>{{ $tenant->name }}</h1>
            <p>Tenant detail and membership management.</p>
        </div>
        <div style="display:flex;gap:0.5rem;">
            <a class="button" href="{{ route('admin.tenants.edit', $tenant) }}">Edit tenant</a>
            <a class="button" href="{{ route('admin.tenants.groups', $tenant) }}">Manage groups</a>
            <a class="button" href="{{ route('admin.tenants.endpoints.manage', $tenant) }}">Manage endpoints</a>
            <a class="button" href="{{ route('admin.tenants.access-config.import', $tenant) }}">Import/Export Config</a>
            <a class="button button-ghost" href="{{ route('admin.tenants') }}" style="border-color:var(--border);color:var(--text-secondary);">Back to tenants</a>
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

        <div style="margin-top:1.25rem;">
            <form method="post" action="{{ route('admin.tenants.status', $tenant) }}" style="display:flex;gap:0.5rem;align-items:center;">
                @csrf
                @if ($tenant->status === 'active')
                    <input type="hidden" name="status" value="suspended">
                    <button class="button button-danger" type="submit" onclick="return confirm('Suspend this tenant?');">Suspend tenant</button>
                @else
                    <input type="hidden" name="status" value="active">
                    <button class="button" type="submit">Activate tenant</button>
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
                <button class="button" type="submit">Add to tenant</button>
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
                                    <form method="post" action="{{ route('admin.tenants.members', $tenant) }}" onsubmit="return confirm('Remove this user from the tenant?');">
                                        @csrf
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="user_id" value="{{ $member->id }}">
                                        <button class="button button-danger" type="submit">Remove</button>
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
