@extends('layouts.admin')

@section('title', $user->name . ' | LOA Admin')
@section('content')
    @include('admin.partials.breadcrumbs', ['items' => [
        ['label' => 'Users', 'url' => route('admin.users')],
        ['label' => $user->name],
    ]])
    <div class="page-header">
        <div>
            <h1>{{ $user->name }}</h1>
            <p>User profile, group memberships, and permission management.</p>
        </div>
    </div>

    {{-- User info --}}
    <div class="detail-card">
        <h2>User details</h2>
        <div class="detail-grid">
            <div class="detail-field">
                <label>Email</label>
                <span>{{ $user->email }}</span>
            </div>
            <div class="detail-field">
                <label>Name</label>
                <span>{{ $user->name }}</span>
            </div>
            <div class="detail-field">
                <label>Status</label>
                <span><span class="badge badge-{{ $user->status }}">{{ $user->status }}</span></span>
            </div>
            <div class="detail-field">
                <label>Created</label>
                <span>{{ $user->created_at?->format('M j, Y g:i A') ?? '—' }}</span>
            </div>
        </div>
    </div>

    {{-- Group Membership --}}
    <div class="detail-card">
        <div class="section-header">
            <h2>Group Membership ({{ $groups->count() }})</h2>
        </div>

        {{-- Add to group --}}
        @if ($allGroups->isNotEmpty())
            <form method="post" action="{{ route('admin.users.groups.store', $user) }}" class="inline-form" style="margin-bottom:1.25rem;">
                @csrf
                <div class="field">
                    <select name="group_id" required style="height:2.5rem;padding:0.5rem 0.75rem;border:1.5px solid var(--border);border-radius:var(--radius-xl);background:var(--surface-secondary);font-family:inherit;font-size:0.875rem;">
                        <option value="">Select a group…</option>
                        @foreach ($allGroups as $group)
                            @unless ($groups->contains('id', $group->id))
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endunless
                        @endforeach
                    </select>
                </div>
                <button class="button" type="submit">Add to group</button>
            </form>
        @endif

        {{-- Groups list --}}
        <div class="table-wrap">
            @if ($groups->isEmpty())
                <div class="empty-state">User is not a member of any groups.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Group</th>
                            <th>Description</th>
                            <th>Scope</th>
                            <th class="row-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($groups as $group)
                            <tr>
                                <td class="cell-user">
                                    <strong>
                                        @if ($group->tenant_id)
                                            <a href="{{ route('admin.tenants.group.show', [$group->tenant_id, $group->id]) }}">{{ $group->name }}</a>
                                        @else
                                            <a href="{{ route('admin.groups.show', $group->id) }}">{{ $group->name }}</a>
                                        @endif
                                    </strong>
                                </td>
                                <td class="muted">{{ $group->description ?? '—' }}</td>
                                <td class="muted">{{ $group->tenant_id ? 'Tenant' : 'Platform' }}</td>
                                <td class="row-actions">
                                    <form method="post" action="{{ route('admin.users.groups.remove', [$user, $group->id]) }}">
                                        @csrf
                                        <a class="button button-link button-danger" role="button" href="#" onclick="event.preventDefault(); if (confirm('Remove from this group?')) this.closest('form').submit();">Remove</a>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- Effective Permissions --}}
    <div class="detail-card">
        <div class="section-header">
            <h2>Effective Permissions</h2>
        </div>
        @if (empty($effectivePermissions))
            <div class="empty-state">No effective permissions.</div>
        @else
            <div class="perm-grid" style="pointer-events:none;opacity:0.7;">
                @foreach ($allPermissions as $perm)
                    @php
                        $active = in_array($perm->key, $effectivePermissions);
                    @endphp
                    <label class="perm-check">
                        <input type="checkbox" disabled @checked($active)>
                        <span>{{ $perm->key }}</span>
                    </label>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Permission Overrides --}}
    <div class="detail-card">
        <div class="section-header">
            <h2>Permission Overrides</h2>
        </div>

        {{-- Grant override --}}
        <form method="post" action="{{ route('admin.users.permissions.store', $user) }}" class="inline-form" style="margin-bottom:1.25rem;">
            @csrf
            <div class="field">
                <select name="permission_key" required style="height:2.5rem;padding:0.5rem 0.75rem;border:1.5px solid var(--border);border-radius:var(--radius-xl);background:var(--surface-secondary);font-family:inherit;font-size:0.875rem;">
                    <option value="">Select a permission…</option>
                    @foreach ($allPermissions as $perm)
                        <option value="{{ $perm->key }}">{{ $perm->key }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <select name="granted" required style="height:2.5rem;padding:0.5rem 0.75rem;border:1.5px solid var(--border);border-radius:var(--radius-xl);background:var(--surface-secondary);font-family:inherit;font-size:0.875rem;">
                    <option value="1">Grant</option>
                    <option value="0">Deny</option>
                </select>
            </div>
            <button class="button" type="submit">Add override</button>
        </form>

        {{-- Overrides list --}}
        <div class="table-wrap">
            @if ($overrides->isEmpty())
                <div class="empty-state">No permission overrides.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Permission</th>
                            <th>Decision</th>
                            <th>Scope</th>
                            <th class="row-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($overrides as $override)
                            <tr>
                                <td><strong>{{ $override->key }}</strong></td>
                                <td>
                                    @if ($override->pivot->granted)
                                        <span class="badge badge-active">Granted</span>
                                    @else
                                        <span class="badge badge-disabled">Denied</span>
                                    @endif
                                </td>
                                <td class="muted">{{ $override->pivot->tenant_id ? 'Tenant' : 'Global' }}</td>
                                <td class="row-actions">
                                    <form method="post" action="{{ route('admin.users.permissions.remove', [$user, $override->key]) }}">
                                        @csrf
                                        <a class="button button-link button-danger" role="button" href="#" onclick="event.preventDefault(); if (confirm('Remove this permission override?')) this.closest('form').submit();">Remove</a>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@endsection
