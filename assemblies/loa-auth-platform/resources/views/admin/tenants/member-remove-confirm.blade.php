@extends('layouts.admin')

@section('title', 'Remove ' . $user->name . ' from ' . $group->name . ' | LOA Admin')
@section('content')
    @include('admin.partials.breadcrumbs', ['items' => [
        ['label' => 'Tenants', 'url' => route('admin.tenants')],
        ['label' => $tenant->name, 'url' => route('admin.tenants.show', $tenant)],
        ['label' => 'Groups', 'url' => route('admin.tenants.groups', $tenant)],
        ['label' => $group->name, 'url' => route('admin.tenants.group.show', [$tenant, $group])],
        ['label' => 'Members', 'url' => route('admin.tenants.group.members', [$tenant, $group])],
        ['label' => 'Remove ' . $user->name],
    ]])
    <div class="page-header">
        <div>
            <h1>Remove member</h1>
            <p>Confirm removal of <strong>{{ $user->name }}</strong> ({{ $user->email }}) from group <strong>{{ $group->name }}</strong>.</p>
        </div>
    </div>

    @if ($isLastAdmin)
        <div class="detail-card" style="border-left:3px solid var(--danger);">
            <h2 style="color:var(--danger);">Cannot remove</h2>
            <p>You are the last platform administrator in this group. Removing yourself would leave the platform without an admin.</p>
            <a class="button" href="{{ route('admin.tenants.group.members', [$tenant, $group]) }}">Back to members</a>
        </div>
    @else
        <div class="detail-card">
            <h2>Removal details</h2>
            <div class="detail-grid">
                <div class="detail-field">
                    <label>User</label>
                    <span><strong>{{ $user->name }}</strong> ({{ $user->email }})</span>
                </div>
                <div class="detail-field">
                    <label>Group</label>
                    <span>{{ $group->name }}</span>
                </div>
                <div class="detail-field">
                    <label>Tenant</label>
                    <span>{{ $tenant->name }}</span>
                </div>
            </div>

            @if ($otherGroups->isNotEmpty())
                <div style="margin-top:1rem;padding:0.75rem;background:var(--surface-secondary);border-radius:var(--radius);">
                    <strong>This user is also a member of:</strong>
                    <ul style="margin:0.5rem 0 0 1.25rem;">
                        @foreach ($otherGroups as $og)
                            <li><a href="{{ route('admin.tenants.group.show', [$tenant, $og]) }}">{{ $og->name }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div style="margin-top:1.5rem;">
                <form method="post" action="{{ route('admin.tenants.group.members.remove', [$tenant, $group, $user->id]) }}" style="display:inline;">
                    @csrf
                    <button class="button button-danger" type="submit" onclick="return confirm('Are you sure you want to remove this user from the group?')">Confirm removal</button>
                </form>
                <a class="button" href="{{ route('admin.tenants.group.members', [$tenant, $group]) }}" style="margin-left:0.5rem;">Cancel</a>
            </div>
        </div>
    @endif
@endsection
