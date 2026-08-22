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

        <div class="quick-actions" aria-label="Group actions">
            <a class="action-tile" href="{{ route('admin.tenants.group.endpoints', [$tenant, $group]) }}">
                <span class="tile-icon tile-violet" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                </span>
                <span class="tile-body">
                    <strong>Endpoints &amp; permissions</strong>
                    <small>Endpoint grants and access levels</small>
                </span>
                <span class="tile-chevron" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </span>
            </a>
            <a class="action-tile" href="{{ route('admin.tenants.group.members', [$tenant, $group]) }}">
                <span class="tile-icon tile-info" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </span>
                <span class="tile-body">
                    <strong>Members</strong>
                    <small>{{ $group->users()->count() }} user{{ $group->users()->count() === 1 ? '' : 's' }} in this group</small>
                </span>
                <span class="tile-chevron" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </span>
            </a>
        </div>
    </div>
@endsection