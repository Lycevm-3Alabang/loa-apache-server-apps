@extends('layouts.admin')

@section('title', 'User Management | LOA Admin')
@section('content')
    @include('admin.partials.breadcrumbs', ['items' => [
        ['label' => 'Users'],
    ]])
    <div class="page-header">
        <div>
            <h1>User Management</h1>
            <p>Search, filter, and control account access.</p>
        </div>
        <div class="page-actions">
            <a class="button" href="{{ route('admin.users.create') }}">Create User</a>
        </div>
    </div>

    <div class="panel">
        <div class="panel-toolbar">
            <div class="filters">
                <form method="get" action="{{ route('admin.users') }}">
                    <div class="field">
                        <input type="search" name="q" value="{{ $q }}" placeholder="Search name or email" aria-label="Search users">
                    </div>
                    <div class="field">
                        <select name="status" aria-label="Filter by status">
                            <option value="all" @selected($status === 'all')>All statuses</option>
                            <option value="active" @selected($status === 'active')>Active</option>
                            <option value="pending" @selected($status === 'pending')>Pending</option>
                            <option value="disabled" @selected($status === 'disabled')>Disabled</option>
                            <option value="locked" @selected($status === 'locked')>Locked</option>
                        </select>
                    </div>
                    <button class="button" type="submit">Filter</button>
                </form>
            </div>
        </div>

        <div class="table-wrap">
            @if ($users->isEmpty())
                <div class="empty-state">No users match the current filters.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Groups</th>
                            <th>Status</th>
                            <th>Failed attempts</th>
                            <th>Locked until</th>
                            <th>Created</th>
                            <th class="row-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td class="cell-user">
                                    <strong>{{ $user->name }} @if ($user->inGroup((string) config('auth-web.admin_group')))<span class="muted">(Admin)</span>@endif</strong>
                                    <span>{{ $user->email }}</span>
                                </td>
                                <td class="muted">
                                    @php $groups = $user->userGroups->sortBy('name'); @endphp
                                    @if ($groups->isNotEmpty())
                                        @foreach ($groups as $group)
                                            <span style="display:inline-flex;align-items:center;gap:0.25rem;margin:0 0.375rem 0.375rem 0;padding:0.125rem 0.5rem;border:1px solid var(--border);border-radius:var(--radius-xl,999px);background:var(--surface-secondary);font-size:0.8125rem;">
                                                @if ($group->tenant_id)
                                                    <a href="{{ route('admin.tenants.group.show', [$group->tenant_id, $group]) }}">{{ $group->name }}</a>
                                                @else
                                                    <a href="{{ route('admin.groups.show', $group) }}">{{ $group->name }}</a>
                                                @endif
                                            </span>
                                        @endforeach
                                    @else
                                        <span>—</span>
                                    @endif
                                </td>
                                <td><span class="badge badge-{{ $user->status }}">{{ $user->status }}</span></td>
                                <td class="muted">{{ $user->failed_attempts }}</td>
                                <td class="muted">{{ $user->locked_until?->format('M j, Y g:i A') ?? '—' }}</td>
                                <td class="muted">{{ $user->created_at?->format('M j, Y') ?? '—' }}</td>
                                <td class="row-actions">
                                    <a class="button button-link" href="{{ route('admin.users.show', $user->id) }}">View</a>
                                    @if ($user->status === 'pending' || $user->status === 'locked')
                                        <form method="post" action="{{ route('admin.users.status', $user->id) }}">
                                            @csrf
                                            <input type="hidden" name="status" value="active">
                                            <a class="button button-link" role="button" href="#" onclick="event.preventDefault(); if (confirm('Activate this account?')) this.closest('form').submit();">Activate</a>
                                        </form>
                                    @elseif ($user->status === 'active' && $user->id !== $currentUserId && !$user->inGroup((string) config('auth-web.admin_group')))
                                        <form method="post" action="{{ route('admin.users.status', $user->id) }}">
                                            @csrf
                                            <input type="hidden" name="status" value="disabled">
                                            <a class="button button-link button-danger" role="button" href="#" onclick="event.preventDefault(); if (confirm('Disable this account?')) this.closest('form').submit();">Disable</a>
                                        </form>
                                    @elseif ($user->status === 'disabled')
                                        <form method="post" action="{{ route('admin.users.status', $user->id) }}">
                                            @csrf
                                            <input type="hidden" name="status" value="active">
                                            <a class="button button-link" role="button" href="#" onclick="event.preventDefault(); this.closest('form').submit();">Enable</a>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="pagination">
            <span>Showing {{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }} of {{ $users->total() }}</span>
            {{ $users->links() }}
        </div>
    </div>
@endsection
