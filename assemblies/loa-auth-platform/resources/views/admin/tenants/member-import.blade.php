@extends('layouts.admin')

@section('title', 'Import Members - LOA Admin')

@section('content')
@include('admin.partials.breadcrumbs', ['items' => [
    ['label' => 'Tenants', 'url' => route('admin.tenants')],
    ['label' => $tenant->name, 'url' => route('admin.tenants.show', $tenant)],
    ['label' => 'Import members'],
]])
<div class="page-header">
    <div>
        <h1>Import Members</h1>
        <p>Bulk-add users to <strong>{{ $tenant->name }}</strong> with a group assignment.</p>
    </div>
</div>

@include('admin.tenants._import-pending', ['tenant' => $tenant])

<div class="detail-card">
    <h2>CSV Format</h2>
    <p class="muted" style="margin-bottom:1rem;">Required headers (exact order):</p>
    <pre style="background:var(--surface-secondary);padding:1rem;border-radius:var(--radius-lg);font-family:monospace;font-size:0.8125rem;">name,email,user_group</pre>
    <p class="muted" style="margin-top:0.5rem;font-size:0.8125rem;">Example: John Doe,john@test.com,cert-admin</p>
    <p class="muted" style="margin-top:0.5rem;font-size:0.8125rem;">The tenant is implied by this page — no tenant column needed.</p>
    <a href="{{ route('admin.tenants.members.import.template', $tenant) }}" class="button button-secondary" style="margin-top:1rem;">Download Template</a>
</div>

<div class="detail-card">
    <h2>Valid groups for this tenant</h2>
    @if ($groups->isEmpty())
        <div class="empty-state">This tenant has no groups yet. <a href="{{ route('admin.tenants.groups', $tenant) }}">Create one first</a>.</div>
    @else
        <ul class="muted" style="columns:2;margin:0;padding-left:1.25rem;">
            @foreach ($groups as $group)
                <li>{{ $group->name }}</li>
            @endforeach
        </ul>
    @endif
</div>

<div class="detail-card">
    <h2>Upload CSV</h2>
    @if (isset($error))
        <div class="alert alert-danger" style="margin-bottom:1rem;padding:0.75rem 1rem;border-radius:var(--radius-lg);background:#fef2f2;border:1px solid #fecaca;color:#991b1b;">{{ $error }}</div>
    @endif
    @if ($groups->isEmpty())
        <p class="muted">Create at least one group before importing members.</p>
    @endif
    <form method="post" action="{{ route('admin.tenants.members.import.preview', $tenant) }}" enctype="multipart/form-data">
        @csrf
        <div class="form-grid">
            <div class="form-row">
                <label for="file">CSV File</label>
                <input type="file" name="file" id="file" accept=".csv,.txt" required style="width:100%;">
            </div>
        </div>
        <button class="button" type="submit" style="margin-top:1rem;" {{ $groups->isEmpty() ? 'disabled' : '' }}>Upload &amp; Preview</button>
    </form>
</div>
@endsection
