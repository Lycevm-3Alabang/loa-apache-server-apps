@extends('layouts.admin')

@section('title', 'User Management | LOA Admin')
@section('content')
    <div class="page-header">
        <div>
            <h1>User Management</h1>
            <p>Search, filter, and control account access.</p>
        </div>
        <a class="button" href="{{ route('admin.users.create') }}">Create User</a>
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
                                    <strong>{{ $user->name }}</strong>
                                    <span>{{ $user->email }}</span>
                                </td>
                                <td><span class="badge badge-{{ $user->status }}">{{ $user->status }}</span></td>
                                <td class="muted">{{ $user->failed_attempts }}</td>
                                <td class="muted">{{ $user->locked_until?->format('M j, Y g:i A') ?? '—' }}</td>
                                <td class="muted">{{ $user->created_at?->format('M j, Y') ?? '—' }}</td>
                                <td class="row-actions">
                                    <a class="button" href="{{ route('admin.users.show', $user->id) }}" style="margin-right:0.375rem;">View</a>
                                    @if ($user->status === 'active' && $user->id !== $currentUserId)
                                        <form method="post" action="{{ route('admin.users.status', $user->id) }}" onsubmit="return confirm('Disable this account?');" style="display:inline;">
                                            @csrf
                                            <input type="hidden" name="status" value="disabled">
                                            <button class="button button-danger" type="submit">Disable</button>
                                        </form>
                                    @elseif ($user->status === 'disabled')
                                        <form method="post" action="{{ route('admin.users.status', $user->id) }}" style="display:inline;">
                                            @csrf
                                            <input type="hidden" name="status" value="active">
                                            <button class="button" type="submit">Enable</button>
                                        </form>
                                    @else
                                        <span class="muted">—</span>
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
