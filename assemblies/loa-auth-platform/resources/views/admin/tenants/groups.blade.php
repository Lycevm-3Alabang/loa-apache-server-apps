@extends('layouts.admin')

@section('title', $tenant->name . ' Groups | LOA Admin')
@section('content')
    <div class="page-header">
        <div>
            <h1>{{ $tenant->name }} — Groups</h1>
            <p>Manage tenant groups and their endpoint permissions.</p>
        </div>
        <a class="button button-ghost" href="{{ route('admin.tenants.show', $tenant) }}" style="border-color:var(--border);color:var(--text-secondary);">Back to tenant</a>
    </div>

    {{-- Create group --}}
    <div class="detail-card">
        <h2>Create group</h2>
        <form method="post" action="{{ route('admin.tenants.groups.store', $tenant) }}" class="inline-form">
            @csrf
            <div class="field">
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="Group name" style="height:2.5rem;padding:0.5rem 0.75rem;border:1.5px solid var(--border);border-radius:var(--radius-xl);background:var(--surface-secondary);font-family:inherit;font-size:0.875rem;">
            </div>
            <div class="field">
                <input type="text" name="description" value="{{ old('description') }}" placeholder="Description (optional)" style="height:2.5rem;padding:0.5rem 0.75rem;border:1.5px solid var(--border);border-radius:var(--radius-xl);background:var(--surface-secondary);font-family:inherit;font-size:0.875rem;">
            </div>
            <div class="field">
                <input type="number" name="priority" value="{{ old('priority', 10) }}" min="1" max="100" placeholder="Priority (1=highest)" style="height:2.5rem;padding:0.5rem 0.75rem;border:1.5px solid var(--border);border-radius:var(--radius-xl);background:var(--surface-secondary);font-family:inherit;font-size:0.875rem;width:8rem;">
            </div>
            <button class="button" type="submit">Create</button>
        </form>
    </div>

    {{-- Existing groups --}}
    @if ($groups->isEmpty())
        <div class="detail-card">
            <div class="empty-state">No groups yet. Create one above.</div>
        </div>
    @else
        @foreach ($groups as $group)
            <div class="detail-card">
                <div class="section-header">
                    <h2>{{ $group->name }}</h2>
                    <span style="font-size:0.8rem;color:var(--text-muted);margin-left:0.5rem;">priority: {{ $group->priority }}</span>
                </div>

                @if ($group->description)
                    <p style="margin:0 0 1rem;color:var(--text-muted);font-size:0.875rem;">{{ $group->description }}</p>
                @endif

                {{-- Permission form --}}
                <form method="post" action="{{ route('admin.tenants.groups.permissions', [$tenant, $group]) }}">
                    @csrf
                    <div class="perm-grid">
                        @foreach ($allPermissions as $perm)
                            @php
                                $granted = $group->permissions->contains('id', $perm->id)
                                    ? $group->permissions->firstWhere('id', $perm->id)->pivot->granted
                                    : false;
                            @endphp
                            <label class="perm-check">
                                <input type="checkbox" name="permissions[]" value="{{ $perm->id }}" @checked($granted)>
                                <span>{{ $perm->key }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div style="margin-top:1rem;">
                        <button class="button" type="submit">Save permissions</button>
                    </div>
                </form>
            </div>
        @endforeach
    @endif
@endsection
