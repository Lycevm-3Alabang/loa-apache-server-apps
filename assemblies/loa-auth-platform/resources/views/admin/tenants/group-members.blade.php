@extends('layouts.admin')

@section('title', $group->name . ' Members | LOA Admin')
@section('content')
    @include('admin.partials.breadcrumbs', ['items' => [
        ['label' => 'Tenants', 'url' => route('admin.tenants')],
        ['label' => $tenant->name, 'url' => route('admin.tenants.show', $tenant)],
        ['label' => 'Groups', 'url' => route('admin.tenants.groups', $tenant)],
        ['label' => $group->name, 'url' => route('admin.tenants.group.show', [$tenant, $group])],
        ['label' => 'Members'],
    ]])
    <div class="page-header">
        <div>
            <h1>{{ $group->name }} — Members</h1>
            <p>Users who are members of this group within "{{ $tenant->name }}".</p>
        </div>
    </div>

    <div class="detail-card">
        <div class="table-wrap">
            @if ($members->isEmpty())
                <div class="empty-state">No members in this group.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Status</th>
                            <th>Other Group Memberships</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($members as $member)
                            @php
                                // Get groups in this tenant that the user is also a member of (excluding current group)
                                $otherGroups = $member->userGroups
                                    ->where('tenant_id', $tenant->id)
                                    ->where('id', '!=', $group->id);
                            @endphp
                            <tr>
                                <td class="cell-user">
                                    <a href="{{ route('admin.users.show', $member->id) }}"><strong>{{ $member->name }}</strong></a>
                                    <span>{{ $member->email }}</span>
                                </td>
                                <td><span class="badge badge-{{ $member->status }}">{{ $member->status }}</span></td>
                                <td class="muted">
                                    @if ($otherGroups->isNotEmpty())
                                        @foreach ($otherGroups as $mGroup)
                                            <a href="{{ route('admin.tenants.group.show', [$tenant, $mGroup]) }}">{{ $mGroup->name }}</a><br>
                                        @endforeach
                                    @else
                                        <span class="muted">None</span>
                                    @endif
                                </td>
                                <td class="muted">{{ $member->pivot->created_at?->format('M j, Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@endsection