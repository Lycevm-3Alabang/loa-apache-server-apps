@extends('layouts.admin')

@section('title', 'Group Endpoint Grants - ' . $group->name . ' | LOA Admin')
@section('content')
    @include('admin.partials.breadcrumbs', ['items' => [
        ['label' => 'Tenants', 'url' => route('admin.tenants')],
        ['label' => $tenant->name, 'url' => route('admin.tenants.show', $tenant)],
        ['label' => 'Groups', 'url' => route('admin.tenants.groups', $tenant)],
        ['label' => $group->name, 'url' => route('admin.tenants.group.show', [$tenant, $group])],
        ['label' => 'Endpoints & Permissions'],
    ]])
    <div class="page-header">
        <div>
            <h1>Group Endpoint Grants</h1>
            <p>Manage endpoint access levels for <strong>{{ $group->name }}</strong> in {{ $tenant->name }}.</p>
        </div>
    </div>

    {{-- Group info --}}
    <div class="detail-card">
        <div class="detail-grid">
            <div class="detail-field">
                <label>Group</label>
                <span>{{ $group->name }}</span>
            </div>
            <div class="detail-field">
                <label>Tenant</label>
                <span>{{ $tenant->name }}</span>
            </div>
            <div class="detail-field">
                <label>Scope</label>
                <span>{{ $group->tenant_id ? 'Tenant' : 'Platform' }}</span>
            </div>
        </div>
    </div>

    {{-- Endpoint grants form --}}
    <div class="detail-card">
        <form method="post" action="{{ route('admin.tenants.group.endpoints.save', [$tenant, $group]) }}">
            @csrf
            <div class="section-header">
                
<div style="display:flex; align-items:center; gap:1rem;">
    <label for="apply-to-all" style="font-weight:600;">Apply To All:</label>
    <select id="apply-to-all" style="padding:0.4rem; border:1px solid var(--border); border-radius:6px;"><option value="">— Select —</option><option value="read">read</option><option value="write">write</option><option value="admin">admin</option><option value="deny">deny</option></select>
    <button class="button" type="submit">Save all</button>
</div>

            </div>

            <div class="table-wrap">
                @if ($endpoints->isEmpty())
                    <div class="empty-state">No endpoints cataloged for this tenant. <a href="{{ route('admin.tenants.endpoints.manage', $tenant) }}">Add endpoints first</a>.</div>
                @else
                    <table id="endpoint-table">
                        <thead>
                            <tr>
                                <th>Method</th>
                                <th>Path</th>
                                <th>Label</th>
                                <th>Required Level</th>
                                <th>Granted Level</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($endpoints as $endpoint)
                                @php
                                    $key = $endpoint->method . '|' . $endpoint->path;
                                    $currentLevel = $grantMap[$key] ?? 'deny';
                                @endphp
                                <tr>
                                    <td><span class="badge">{{ $endpoint->method }}</span></td>
                                    <td><code style="font-size:0.8rem;">{{ $endpoint->path }}</code></td>
                                    <td>{{ $endpoint->label ?? '—' }}</td>
                                    <td><span class="badge badge-{{ $endpoint->required_level === 'admin' ? 'disabled' : 'active' }}">{{ $endpoint->required_level }}</span></td>
                                    <td>
                                        <select name="grants[{{ $endpoint->method }}|{{ $endpoint->path }}][level]" style="height:2.25rem;padding:0.25rem 0.5rem;border:1.5px solid var(--border);border-radius:var(--radius-xl);background:var(--surface-secondary);font-family:inherit;font-size:0.8rem;">
                                            <option value="deny" {{ $currentLevel === 'deny' ? 'selected' : '' }} style="color:red;">deny</option>
                                            <option value="read" {{ $currentLevel === 'read' ? 'selected' : '' }}>read</option>
                                            <option value="write" {{ $currentLevel === 'write' ? 'selected' : '' }}>write</option>
                                            <option value="admin" {{ $currentLevel === 'admin' ? 'selected' : '' }}>admin</option>
                                            <option value="deny" {{ $currentLevel === 'deny' ? 'selected' : '' }} style="color:red;">deny</option>
                                        </select>
                                        <input type="hidden" name="grants[{{ $endpoint->method }}|{{ $endpoint->path }}][method]" value="{{ $endpoint->method }}">
                                        <input type="hidden" name="grants[{{ $endpoint->method }}|{{ $endpoint->path }}][path]" value="{{ $endpoint->path }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </form>
    </div>
    <script>
        document.getElementById('apply-to-all').addEventListener('change', function () {
            var value = this.value;
            if (!value) return;
            var selects = document.querySelectorAll('#endpoint-table select');
            selects.forEach(function (s) { s.value = value; });
            this.value = '';
        });
    </script>
@endsection
