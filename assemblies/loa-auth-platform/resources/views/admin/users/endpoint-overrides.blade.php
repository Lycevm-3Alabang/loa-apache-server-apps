@extends('layouts.admin')

@section('title', 'Endpoint Overrides - ' . $user->name . ' | LOA Admin')
@section('content')
    <div class="page-header">
        <div>
            <h1>Endpoint Overrides</h1>
            <p>Manage per-user endpoint access overrides for <strong>{{ $user->name }}</strong> ({{ $user->email }}).</p>
        </div>
        <div style="display:flex;gap:0.5rem;">
            <a class="button button-ghost" href="{{ route('admin.users.show', $user) }}" style="border-color:var(--border);color:var(--text-secondary);">Back to user</a>
        </div>
    </div>

    {{-- User info --}}
    <div class="detail-card">
        <div class="detail-grid">
            <div class="detail-field">
                <label>User</label>
                <span>{{ $user->name }} ({{ $user->email }})</span>
            </div>
            <div class="detail-field">
                <label>Status</label>
                <span><span class="badge badge-{{ $user->status }}">{{ $user->status }}</span></span>
            </div>
            <div class="detail-field">
                <label>Existing Overrides</label>
                <span>{{ $overrides->count() }}</span>
            </div>
        </div>
    </div>

    {{-- Override form --}}
    <div class="detail-card">
        <form method="post" action="{{ route('admin.users.endpoint-overrides.upsert', $user) }}">
            @csrf
            <div class="section-header">
                <h2>Endpoint Overrides</h2>
                <button class="button" type="submit">Save all</button>
            </div>

            <p style="font-size:0.875rem;color:var(--text-secondary);margin-bottom:1rem;">
                User overrides <strong>replace</strong> group-resolution results entirely for that endpoint. A <code>deny</code> override can re-enable an endpoint that groups denied.
            </p>

            <div class="table-wrap">
                @if ($allEndpoints->isEmpty())
                    <div class="empty-state">No endpoints cataloged yet.</div>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th>Tenant</th>
                                <th>Method</th>
                                <th>Path</th>
                                <th>Required Level</th>
                                <th>Override Level</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($allEndpoints as $endpoint)
                                @php
                                    $existingOverride = $overrides->first(fn($o) => $o->method === $endpoint->method && $o->path === $endpoint->path && $o->tenant_id === $endpoint->tenant_id);
                                    $currentLevel = $existingOverride?->level ?? 'none';
                                @endphp
                                <tr>
                                    <td>{{ $endpoint->tenant_id ?? 'Platform' }}</td>
                                    <td><span class="badge">{{ $endpoint->method }}</span></td>
                                    <td><code style="font-size:0.8rem;">{{ $endpoint->path }}</code></td>
                                    <td><span class="badge badge-{{ $endpoint->required_level === 'admin' ? 'disabled' : 'active' }}">{{ $endpoint->required_level }}</span></td>
                                    <td>
                                        <select name="overrides[{{ $endpoint->tenant_id }}|{{ $endpoint->method }}|{{ $endpoint->path }}][level]" style="height:2.25rem;padding:0.25rem 0.5rem;border:1.5px solid var(--border);border-radius:var(--radius-xl);background:var(--surface-secondary);font-family:inherit;font-size:0.8rem;">
                                            <option value="none" {{ $currentLevel === 'none' ? 'selected' : '' }}>none</option>
                                            <option value="read" {{ $currentLevel === 'read' ? 'selected' : '' }}>read</option>
                                            <option value="write" {{ $currentLevel === 'write' ? 'selected' : '' }}>write</option>
                                            <option value="admin" {{ $currentLevel === 'admin' ? 'selected' : '' }}>admin</option>
                                            <option value="deny" {{ $currentLevel === 'deny' ? 'selected' : '' }} style="color:red;">deny</option>
                                        </select>
                                        <input type="hidden" name="overrides[{{ $endpoint->tenant_id }}|{{ $endpoint->method }}|{{ $endpoint->path }}][method]" value="{{ $endpoint->method }}">
                                        <input type="hidden" name="overrides[{{ $endpoint->tenant_id }}|{{ $endpoint->method }}|{{ $endpoint->path }}][path]" value="{{ $endpoint->path }}">
                                        <input type="hidden" name="overrides[{{ $endpoint->tenant_id }}|{{ $endpoint->method }}|{{ $endpoint->path }}][tenant_id]" value="{{ $endpoint->tenant_id }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </form>
    </div>
@endsection
