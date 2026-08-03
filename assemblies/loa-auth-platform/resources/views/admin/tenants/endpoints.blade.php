@extends('layouts.admin')

@section('title', 'Endpoint Catalog - ' . $tenant->name . ' | LOA Admin')
@section('content')
    <div class="page-header">
        <div>
            <h1>Endpoint Catalog</h1>
            <p>Manage guarded endpoints for {{ $tenant->name }}.</p>
        </div>
        <div style="display:flex;gap:0.5rem;">
            <a class="button button-ghost" href="{{ route('admin.tenants.endpoints.export', $tenant) }}" style="border-color:var(--border);color:var(--text-secondary);">Export JSON</a>
            <a class="button button-ghost" href="{{ route('admin.tenants.show', $tenant) }}" style="border-color:var(--border);color:var(--text-secondary);">Back to tenant</a>
        </div>
    </div>

    {{-- Import endpoint form --}}
    <div class="detail-card">
        <h2>Import from JSON</h2>
        <p class="muted" style="margin-bottom:1rem;">Upload a JSON file or paste the endpoint catalog payload below. The preview will show what will be created or updated before applying.</p>

        <form id="import-form" method="post" action="{{ route('admin.tenants.endpoints.import.manage.store', $tenant) }}" enctype="multipart/form-data" onsubmit="return handleImport(event);">
            @csrf
            <div class="form-grid">
                <div class="form-row">
                    <div class="field">
                        <label for="file">JSON File</label>
                        <input type="file" name="file" id="file" accept=".json" style="height:2.5rem;padding:0.5rem 0.75rem;border:1.5px solid var(--border);border-radius:var(--radius-xl);background:var(--surface-secondary);font-family:inherit;font-size:0.875rem;width:100%;">
                    </div>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label for="payload">Or Paste JSON</label>
                        <textarea name="payload" id="payload" rows="10" placeholder='{"version":"1.0","endpoints":[{"method":"GET","path":"/api/v1/resource","label":"List resources","required_level":"read"}]}' style="padding:0.75rem;border:1.5px solid var(--border);border-radius:var(--radius-xl);background:var(--surface-secondary);font-family:monospace;font-size:0.8rem;width:100%;resize:vertical;"></textarea>
                    </div>
                </div>
                <div class="form-row" style="display:flex;gap:0.75rem;align-items:center;">
                    <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;">
                        <input type="checkbox" name="confirm" id="confirm" value="1" style="width:1rem;height:1rem;">
                        I understand this will modify the endpoint catalog
                    </label>
                </div>
            </div>

            {{-- Preview results --}}
            <div id="preview-results" style="display:none;margin-top:1.5rem;">
                <h3>Preview Results</h3>
                <div id="preview-content" style="padding:1rem;border:1.5px solid var(--border);border-radius:var(--radius-xl);background:var(--surface-secondary);font-size:0.875rem;"></div>
            </div>

            <div style="margin-top:1rem;display:flex;gap:0.75rem;">
                <button class="button" type="submit" id="preview-btn">Preview Import</button>
                <button class="button button-ghost" type="submit" id="apply-btn" disabled style="border-color:var(--border);">Apply Import</button>
            </div>
        </form>
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

    <script>
        async function handleImport(e) {
            e.preventDefault();
            const form = e.target;
            const previewBtn = document.getElementById('preview-btn');
            const applyBtn = document.getElementById('apply-btn');
            const confirmCheckbox = document.getElementById('confirm');
            const previewDiv = document.getElementById('preview-results');
            const previewContent = document.getElementById('preview-content');

            const isApply = applyBtn && !applyBtn.disabled && confirmCheckbox && confirmCheckbox.checked;
            const url = form.action + (isApply ? '?confirm=1' : '?dry_run=1');

            const formData = new FormData(form);
            if (isApply) {
                formData.set('confirm', '1');
            } else {
                formData.delete('confirm');
            }

            previewBtn.disabled = true;
            previewBtn.textContent = 'Processing...';

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const data = await response.json();

                if (!response.ok) {
                    previewContent.innerHTML = '<p style="color:var(--error);">Error: ' + (data.message || 'Request failed') + '</p>';
                    if (data.errors) {
                        previewContent.innerHTML += '<pre style="margin-top:0.5rem;font-size:0.8rem;">' + JSON.stringify(data.errors, null, 2) + '</pre>';
                    }
                    previewDiv.style.display = 'block';
                    return false;
                }

                if (data.status === 'preview') {
                    let html = '<p><strong>Status:</strong> Preview (no changes applied)</p>';
                    html += '<table style="width:100%;border-collapse:collapse;margin-top:0.5rem;">';
                    html += '<tr><td style="padding:0.25rem 0;">Endpoints to create:</td><td style="padding:0.25rem 0;">' + (data.endpoints.create.length ? data.endpoints.create.join(', ') : '—') + '</td></tr>';
                    html += '<tr><td style="padding:0.25rem 0;">Endpoints to update:</td><td style="padding:0.25rem 0;">' + (data.endpoints.update.length ? data.endpoints.update.join(', ') : '—') + '</td></tr>';
                    html += '</table>';
                    previewContent.innerHTML = html;
                    previewDiv.style.display = 'block';
                    applyBtn.disabled = false;
                } else if (data.status === 'applied') {
                    let html = '<p style="color:var(--success);"><strong>Applied successfully!</strong></p>';
                    html += '<table style="width:100%;border-collapse:collapse;margin-top:0.5rem;">';
                    html += '<tr><td style="padding:0.25rem 0;">Endpoints created:</td><td style="padding:0.25rem 0;">' + data.created + '</td></tr>';
                    html += '<tr><td style="padding:0.25rem 0;">Endpoints updated:</td><td style="padding:0.25rem 0;">' + data.updated + '</td></tr>';
                    html += '</table>';
                    previewContent.innerHTML = html;
                    previewDiv.style.display = 'block';
                    applyBtn.disabled = true;
                }
            } catch (err) {
                previewContent.innerHTML = '<p style="color:var(--error);">Request failed: ' + err.message + '</p>';
                previewDiv.style.display = 'block';
            } finally {
                previewBtn.disabled = false;
                previewBtn.textContent = 'Preview Import';
            }

            return false;
        }
    </script>
@endsection
