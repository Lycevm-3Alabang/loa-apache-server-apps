@extends('layouts.admin')

@section('title', 'Import Members Results - LOA Admin')

@section('content')
@include('admin.partials.breadcrumbs', ['items' => [
    ['label' => 'Tenants', 'url' => route('admin.tenants')],
    ['label' => $tenant->name, 'url' => route('admin.tenants.show', $tenant)],
    ['label' => 'Import members', 'url' => route('admin.tenants.members.import', $tenant)],
    ['label' => 'Results'],
]])
<div class="page-header">
    <div>
        <h1>Import Results</h1>
        <p>Processing complete.</p>
    </div>
    <a class="button button-ghost" href="{{ route('admin.tenants.members.import', $tenant) }}" style="border-color:var(--border);color:var(--text-secondary);">Start New Import</a>
</div>

{{-- Summary --}}
<div class="detail-card" style="margin-bottom:1.5rem;">
    <h2>Results Summary</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(12rem,1fr));gap:1rem;">
        <div><strong>Successful:</strong> {{ $summary['successful'] }}</div>
        <div><strong>Failed:</strong> {{ $summary['failed'] }}</div>
    </div>
</div>

{{-- Failed rows download --}}
@if ($summary['failed'] > 0)
<div class="detail-card" style="margin-bottom:1.5rem;">
    <h2>Failed Rows</h2>
    <p class="muted">Download the failed rows as CSV for review.</p>
    <a class="button button-ghost" href="{{ route('admin.tenants.members.import.failed', $tenant) }}" style="border-color:var(--border);">Download Failed CSV</a>
</div>
@endif

{{-- Results Table --}}
@if (isset($results) && count($results) > 0)
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>User Group</th>
                <th>Status</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($results as $row)
            <tr data-status="{{ $row['status'] }}">
                <td>{{ $row['name'] }}</td>
                <td>{{ $row['email'] }}</td>
                <td>{{ $row['user_group'] }}</td>
                <td>
                    @if ($row['status'] === 'ready')
                        <span class="badge badge-active">Success</span>
                    @elseif ($row['status'] === 'ready_existing')
                        <span class="badge badge-active">Updated</span>
                    @else
                        <span class="badge badge-disabled">Failed</span>
                    @endif
                </td>
                <td class="muted">{{ $row['remarks'] ?? '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@else
<div class="empty-state">
    All rows imported successfully.
</div>
@endif

@endsection
