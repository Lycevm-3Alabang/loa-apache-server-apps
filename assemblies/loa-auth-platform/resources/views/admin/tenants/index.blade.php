@extends('layouts.admin')

@section('title', 'Tenant Management | LOA Admin')
@section('content')
    @include('admin.partials.breadcrumbs', ['items' => [
        ['label' => 'Tenants'],
    ]])
    <div class="page-header">
        <div>
            <h1>Tenant Management</h1>
            <p>Manage client tenants, their groups, and endpoint permissions.</p>
        </div>
        <a class="button" href="{{ route('admin.tenants.create') }}">Create tenant</a>
    </div>

    <div class="panel">
        <div class="table-wrap">
            @if ($tenants->isEmpty())
                <div class="empty-state">No tenants yet. Create one to get started.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Tenant</th>
                            <th>Status</th>
                            <th>Members</th>
                            <th>App URL</th>
                            <th>Created</th>
                            <th class="row-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tenants as $tenant)
                            <tr>
                                <td class="cell-user">
                                    <strong>{{ $tenant->name }}</strong>
                                    <span>{{ $tenant->slug }}</span>
                                </td>
                                <td><span class="badge badge-{{ $tenant->status === 'active' ? 'active' : 'disabled' }}">{{ $tenant->status }}</span></td>
                                <td class="muted">{{ $tenant->users_count }}</td>
                                <td class="muted">{{ $tenant->app_url ? parse_url($tenant->app_url, PHP_URL_HOST) : '—' }}</td>
                                <td class="muted">{{ $tenant->created_at?->format('M j, Y') ?? '—' }}</td>
                                <td class="row-actions">
                                    <a class="button button-link" href="{{ route('admin.tenants.show', $tenant) }}">Manage</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="pagination">
            <span>Showing {{ $tenants->firstItem() ?? 0 }}–{{ $tenants->lastItem() ?? 0 }} of {{ $tenants->total() }}</span>
            {{ $tenants->links() }}
        </div>
    </div>
@endsection
