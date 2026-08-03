@extends('layouts.admin')

@section('title', 'Endpoint Catalog - ' . $tenant->name . ' | LOA Admin')
@section('content')
    <div class="page-header">
        <div>
            <h1>Endpoint Catalog</h1>
            <p>Manage guarded endpoints for {{ $tenant->name }}.</p>
        </div>
        <div style="display:flex;gap:0.5rem;">
            <a class="button button-ghost" href="{{ route('admin.tenants.show', $tenant) }}" style="border-color:var(--border);color:var(--text-secondary);">Back to tenant</a>
        </div>
    </div>

    {{-- Add endpoint form --}}
    <div class="detail-card">
        <h2>Add endpoint</h2>
        <form method="post" action="{{ route('admin.tenants.endpoints.store', $tenant) }}" class="inline-form">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 2fr 1fr 1fr auto;gap:0.75rem;align-items:end;">
                <div class="field">
                    <label for="method">Method</label>
                    <select name="method" id="method" required style="height:2.5rem;padding:0.5rem 0.75rem;border:1.5px solid var(--border);border-radius:var(--radius-xl);background:var(--surface-secondary);font-family:inherit;font-size:0.875rem;">
                        <option value="GET">GET</option>
                        <option value="POST">POST</option>
                        <option value="PUT">PUT</option>
                        <option value="PATCH">PATCH</option>
                        <option value="DELETE">DELETE</option>
                        <option value="*">*</option>
                    </select>
                </div>
                <div class="field">
                    <label for="path">Path</label>
                    <input type="text" name="path" id="path" placeholder="/api/v1/resource/{id}" required style="height:2.5rem;padding:0.5rem 0.75rem;border:1.5px solid var(--border);border-radius:var(--radius-xl);background:var(--surface-secondary);font-family:inherit;font-size:0.875rem;width:100%;">
                </div>
                <div class="field">
                    <label for="label">Label</label>
                    <input type="text" name="label" id="label" placeholder="List resources" style="height:2.5rem;padding:0.5rem 0.75rem;border:1.5px solid var(--border);border-radius:var(--radius-xl);background:var(--surface-secondary);font-family:inherit;font-size:0.875rem;width:100%;">
                </div>
                <div class="field">
                    <label for="required_level">Required Level</label>
                    <select name="required_level" id="required_level" required style="height:2.5rem;padding:0.5rem 0.75rem;border:1.5px solid var(--border);border-radius:var(--radius-xl);background:var(--surface-secondary);font-family:inherit;font-size:0.875rem;">
                        <option value="read">read</option>
                        <option value="write">write</option>
                        <option value="admin">admin</option>
                    </select>
                </div>
                <button class="button" type="submit">Add</button>
            </div>
            @error('method')<p class="error-text">{{ $message }}</p>@enderror
            @error('path')<p class="error-text">{{ $message }}</p>@enderror
            @error('required_level')<p class="error-text">{{ $message }}</p>@enderror
        </form>
    </div>

    {{-- Endpoint list --}}
    <div class="detail-card">
        <div class="section-header">
            <h2>Cataloged Endpoints ({{ $endpoints->count() }})</h2>
        </div>

        <div class="table-wrap">
            @if ($endpoints->isEmpty())
                <div class="empty-state">No endpoints cataloged yet. Add one above.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th>Path</th>
                            <th>Label</th>
                            <th>Required Level</th>
                            <th>Scope</th>
                            <th class="row-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($endpoints as $endpoint)
                            <tr>
                                <td><span class="badge badge-{{ $endpoint->method === 'DELETE' ? 'disabled' : 'active' }}">{{ $endpoint->method }}</span></td>
                                <td><code style="font-size:0.8rem;">{{ $endpoint->path }}</code></td>
                                <td>{{ $endpoint->label ?? '—' }}</td>
                                <td><span class="badge badge-{{ $endpoint->required_level === 'admin' ? 'disabled' : ($endpoint->required_level === 'write' ? 'active' : 'active') }}">{{ $endpoint->required_level }}</span></td>
                                <td>{{ $endpoint->tenant_id ? 'Tenant' : 'Platform' }}</td>
                                <td class="row-actions">
                                    <form method="post" action="{{ route('admin.tenants.endpoints.destroy', $tenant) }}" onsubmit="return confirm('Delete this endpoint?');">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="method" value="{{ $endpoint->method }}">
                                        <input type="hidden" name="path" value="{{ $endpoint->path }}">
                                        <input type="hidden" name="force" value="true">
                                        <button class="button button-danger" type="submit">Delete</button>
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
