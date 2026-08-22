@extends('layouts.admin')

@section('title', $group->name . ' | LOA Admin')
@section('content')
    @include('admin.partials.breadcrumbs', ['items' => [
        ['label' => 'Tenants', 'url' => route('admin.tenants')],
        ['label' => $tenant->name, 'url' => route('admin.tenants.show', $tenant)],
        ['label' => 'Groups', 'url' => route('admin.tenants.groups', $tenant)],
        ['label' => $group->name],
    ]])
    <div class="page-header">
        <div>
            <h1>{{ $group->name }}</h1>
            <p>Group detail for "{{ $tenant->name }}".</p>
        </div>
    </div>

    {{-- Group info --}}
    <div class="detail-card">
        <h2>Group details</h2>
        <div class="detail-grid">
            <div class="detail-field"><label>Name</label><span>{{ $group->name }}</span></div>
            <div class="detail-field"><label>Description</label><span>{{ $group->description ?? '—' }}</span></div>
            <div class="detail-field"><label>Priority</label><span>{{ $group->priority }}</span></div>
            <div class="detail-field"><label>Scope</label><span>{{ $group->tenant_id ? 'Tenant' : 'Platform' }}</span></div>
            <div class="detail-field"><label>Members</label><span>{{ $group->users()->count() }}</span></div>
            <div class="detail-field"><label>Created</label><span>{{ $group->created_at?->format('M j, Y g:i A') ?? '—' }}</span></div>
        </div>
    </div>

    {{-- Quick actions --}}
    <div class="detail-card">
        <h2>Actions</h2>
        <div style="display:flex;gap:1rem;flex-wrap:wrap;">
            <a class="button" href="{{ route('admin.tenants.group.endpoints', [$tenant, $group]) }}">Manage endpoints & permissions</a>
            <a class="button" href="{{ route('admin.tenants.group.members', [$tenant, $group]) }}">View members</a>
        </div>
    </div>
@endsection