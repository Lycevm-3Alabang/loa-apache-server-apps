@extends('layouts.admin')

@section('title', 'Groups | LOA Admin')
@section('content')
    <div class="page-header">
        <div>
            <h1>Groups</h1>
            <p>Manage platform groups and their permissions.</p>
        </div>
        <a class="button" href="{{ route('admin.groups.create') }}">Create Group</a>
    </div>

    <div class="panel">
        <div class="table-wrap">
            @if ($groups->isEmpty())
                <div class="empty-state">No groups yet. Create one to get started.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Group</th>
                            <th>Priority</th>
                            <th>Members</th>
                            <th>Created</th>
                            <th class="row-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($groups as $group)
                            <tr>
                                <td class="cell-user">
                                    <strong>{{ $group->name }}</strong>
                                    @if ($group->description)
                                        <span>{{ $group->description }}</span>
                                    @endif
                                </td>
                                <td class="muted">{{ $group->priority }}</td>
                                <td class="muted">{{ $group->users_count }}</td>
                                <td class="muted">{{ $group->created_at?->format('M j, Y') ?? '—' }}</td>
                                <td class="row-actions">
                                    <a class="button" href="{{ route('admin.groups.show', $group) }}">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@endsection
