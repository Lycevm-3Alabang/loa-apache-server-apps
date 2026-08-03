@extends('layouts.admin')

@section('title', 'Import Access Config - ' . $tenant->name . ' | LOA Admin')
@section('content')
    <div class="page-header">
        <div>
            <h1>Import Access Config</h1>
            <p>Import groups, grants, and user overrides for {{ $tenant->name }}.</p>
        </div>
        <div style="display:flex;gap:0.5rem;">
            <a class="button button-ghost" href="{{ route('admin.tenants.show', $tenant) }}" style="border-color:var(--border);color:var(--text-secondary);">Back to tenant</a>
        </div>
    </div>

    {{-- Action buttons --}}
    <div class="detail-card">
        <h2>Actions</h2>
        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
            <a class="button button-ghost" href="{{ route('admin.tenants.access-config.template', $tenant) }}" style="border-color:var(--border);">Download Template</a>
            <a class="button button-ghost" href="{{ route('admin.tenants.access-config.export', $tenant) }}" style="border-color:var(--border);">Export Current Config</a>
        </div>
    </div>

    {{-- Import form --}}
    <div class="detail-card">
        <h2>Import from JSON</h2>
        <p class="muted" style="margin-bottom:1rem;">Upload a JSON file or paste the access config payload below. The preview will show what will be created, updated, or skipped before applying.</p>

        <form id="import-form" method="post" action="{{ route('admin.tenants.access-config.import.store', $tenant) }}" enctype="multipart/form-data" onsubmit="return handleImport(event);">
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
                        <textarea name="payload" id="payload" rows="12" placeholder='{"version":"1.0","groups":[...],"user_overrides":[...]}' style="padding:0.75rem;border:1.5px solid var(--border);border-radius:var(--radius-xl);background:var(--surface-secondary);font-family:monospace;font-size:0.8rem;width:100%;resize:vertical;"></textarea>
                    </div>
                </div>
                <div class="form-row" style="display:flex;gap:0.75rem;align-items:center;">
                    <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;">
                        <input type="checkbox" name="confirm" id="confirm" value="1" style="width:1rem;height:1rem;">
                        I understand this will modify groups and permissions
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

    <script>
        async function handleImport(e) {
            e.preventDefault();
            const form = e.target;
            const previewBtn = document.getElementById('preview-btn');
            const applyBtn = document.getElementById('apply-btn');
            const confirmCheckbox = document.getElementById('confirm');
            const previewDiv = document.getElementById('preview-results');
            const previewContent = document.getElementById('preview-content');

            const isApply = applyBtn && !applyBtn.disabled && confirmCheckbox.checked;
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
                    html += '<tr><td style="padding:0.25rem 0;">Groups to create:</td><td style="padding:0.25rem 0;">' + (data.groups.create.length ? data.groups.create.join(', ') : '—') + '</td></tr>';
                    html += '<tr><td style="padding:0.25rem 0;">Groups to update:</td><td style="padding:0.25rem 0;">' + (data.groups.update.length ? data.groups.update.join(', ') : '—') + '</td></tr>';
                    html += '<tr><td style="padding:0.25rem 0;">Grants to upsert:</td><td style="padding:0.25rem 0;">' + data.grants.upsert + '</td></tr>';
                    html += '<tr><td style="padding:0.25rem 0;">User overrides to upsert:</td><td style="padding:0.25rem 0;">' + data.user_overrides.upsert + '</td></tr>';
                    if (data.user_overrides.errors.length) {
                        html += '<tr><td style="padding:0.25rem 0;color:var(--error);">Errors:</td><td style="padding:0.25rem 0;color:var(--error);">' + data.user_overrides.errors.join('; ') + '</td></tr>';
                    }
                    if (!data.endpoint_validation.valid) {
                        html += '<tr><td style="padding:0.25rem 0;color:var(--error);">Missing endpoints:</td><td style="padding:0.25rem 0;color:var(--error);">' + data.endpoint_validation.missing_endpoints.join('; ') + '</td></tr>';
                    }
                    html += '</table>';
                    previewContent.innerHTML = html;
                    previewDiv.style.display = 'block';
                    applyBtn.disabled = false;
                } else if (data.status === 'applied') {
                    let html = '<p style="color:var(--success);"><strong>Applied successfully!</strong></p>';
                    html += '<table style="width:100%;border-collapse:collapse;margin-top:0.5rem;">';
                    html += '<tr><td style="padding:0.25rem 0;">Groups created:</td><td style="padding:0.25rem 0;">' + data.groups.created + '</td></tr>';
                    html += '<tr><td style="padding:0.25rem 0;">Groups updated:</td><td style="padding:0.25rem 0;">' + data.groups.updated + '</td></tr>';
                    html += '<tr><td style="padding:0.25rem 0;">Grants upserted:</td><td style="padding:0.25rem 0;">' + data.grants.upserted + '</td></tr>';
                    html += '<tr><td style="padding:0.25rem 0;">User overrides upserted:</td><td style="padding:0.25rem 0;">' + data.user_overrides.upserted + '</td></tr>';
                    if (data.user_overrides.errors.length) {
                        html += '<tr><td style="padding:0.25rem 0;color:var(--error);">Errors:</td><td style="padding:0.25rem 0;color:var(--error);">' + data.user_overrides.errors.join('; ') + '</td></tr>';
                    }
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
