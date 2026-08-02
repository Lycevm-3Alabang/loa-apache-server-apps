@extends('layouts.admin')

@section('title', $group->name . ' | LOA Admin')
@section('content')
    <div class="page-header">
        <div>
            <h1>{{ $group->name }}</h1>
            <p>Group detail, permissions, and membership management.</p>
        </div>
        <a class="button button-ghost" href="{{ route('admin.groups') }}" style="border-color:var(--border);color:var(--text-secondary);">Back to groups</a>
    </div>

    {{-- Group info --}}
    <div class="detail-card">
        <h2>Group details</h2>
        <div class="detail-grid">
            <div class="detail-field">
                <label>Name</label>
                <span>{{ $group->name }}</span>
            </div>
            <div class="detail-field">
                <label>Description</label>
                <span>{{ $group->description ?? '—' }}</span>
            </div>
            <div class="detail-field">
                <label>Scope</label>
                <span>{{ $group->tenant_id ? 'Tenant' : 'Platform' }}</span>
            </div>
            <div class="detail-field">
                <label>Members</label>
                <span>{{ $group->users_count ?? $group->users->count() }}</span>
            </div>
            <div class="detail-field">
                <label>Created</label>
                <span>{{ $group->created_at?->format('M j, Y g:i A') ?? '—' }}</span>
            </div>
        </div>
    </div>

    {{-- Permissions --}}
    <div class="detail-card">
        <div class="section-header">
            <h2>Permissions</h2>
        </div>
        <form method="post" action="{{ route('admin.groups.permissions', $group) }}">
            @csrf
            <div class="perm-grid">
                @foreach ($allPermissions as $perm)
                    @php
                        $granted = $group->permissions->contains('id', $perm->id)
                            ? $group->permissions->firstWhere('id', $perm->id)->pivot->granted
                            : false;
                    @endphp
                    <label class="perm-check">
                        <input type="checkbox" name="permissions[]" value="{{ $perm->id }}" @checked($granted)>
                        <span>{{ $perm->key }}</span>
                    </label>
                @endforeach
            </div>
            <div style="margin-top:1rem;">
                <button class="button" type="submit">Save permissions</button>
            </div>
        </form>
    </div>

    {{-- Members --}}
    <div class="detail-card">
        <div class="section-header">
            <h2>Members ({{ $group->users->count() }})</h2>
        </div>

        {{-- Add member --}}
        @if ($nonMembers->isNotEmpty())
            <form method="post" action="{{ route('admin.groups.members.store', $group) }}" class="inline-form" style="margin-bottom:1.25rem;">
                @csrf
                <div class="field">
                    <select name="user_id" required style="height:2.5rem;padding:0.5rem 0.75rem;border:1.5px solid var(--border);border-radius:var(--radius-xl);background:var(--surface-secondary);font-family:inherit;font-size:0.875rem;">
                        <option value="">Select a user…</option>
                        @foreach ($nonMembers as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                        @endforeach
                    </select>
                </div>
                <button class="button" type="submit">Add to group</button>
            </form>
        @endif

        {{-- Members list --}}
        <div class="table-wrap">
            @if ($group->users->isEmpty())
                <div class="empty-state">No members yet. Add users above.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Status</th>
                            <th class="row-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group->users as $member)
                            <tr>
                                <td class="cell-user">
                                    <strong>{{ $member->name }}</strong>
                                    <span>{{ $member->email }}</span>
                                </td>
                                <td><span class="badge badge-{{ $member->status }}">{{ $member->status }}</span></td>
                                <td class="row-actions">
                                    <form method="post" action="{{ route('admin.groups.members.remove', [$group, $member->id]) }}" onsubmit="return confirm('Remove this user from the group?');">
                                        @csrf
                                        <button class="button button-danger" type="submit">Remove</button>
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
