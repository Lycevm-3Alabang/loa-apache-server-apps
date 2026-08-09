@extends('layouts.admin')

@section('title', 'Bulk User Import - LOA Admin')

@section('content')
<div class="page-header">
    <div>
        <h1>Bulk User Import</h1>
        <p>Upload a CSV file to import users with tenant and group assignments.</p>
    </div>
    <a class="button button-ghost" href="{{ route('admin.users') }}" style="border-color:var(--border);color:var(--text-secondary);">Back to Users</a>
</div>

<div class="detail-card">
    <h2>CSV Format</h2>
    <p class="muted" style="margin-bottom:1rem;">Required headers (exact order):</p>
    <pre style="background:var(--surface-secondary);padding:1rem;border-radius:var(--radius-lg);font-family:monospace;font-size:0.8125rem;">name,email,tenant_app,user_group</pre>
    <p class="muted" style="margin-top:0.5rem;font-size:0.8125rem;">Example: John Doe,john@test.com,loa,cert-admin</p>
</div>

<div class="detail-card">
    <h2>Upload CSV</h2>
    <form id="upload-form" method="post" action="{{ route('admin.users.import.preview') }}" enctype="multipart/form-data">
        @csrf
        <div class="form-grid">
            <div class="form-row">
                <label for="file">CSV File</label>
                <input type="file" name="file" id="file" accept=".csv,.txt" required style="width:100%;">
            </div>
        </div>
        <button class="button" type="submit" style="margin-top:1rem;">Upload & Preview</button>
    </form>
</div>
@endsection
