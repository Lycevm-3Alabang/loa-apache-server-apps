@extends('layouts.admin')

@section('title', 'Endpoint Catalog - ' . $tenant->name . ' | LOA Admin')
@section('content')
    @include('admin.partials.breadcrumbs', ['items' => [
        ['label' => 'Tenants', 'url' => route('admin.tenants')],
        ['label' => $tenant->name, 'url' => route('admin.tenants.show', $tenant)],
        ['label' => 'Endpoint Catalog'],
    ]])
    <div class="page-header">
        <div>
            <h1>Endpoint Catalog</h1>
            <p>Manage guarded endpoints for {{ $tenant->name }}.</p>
        </div>
        <div style="display:flex;gap:0.5rem;">
            <a class="button button-ghost" href="{{ route('admin.tenants.endpoints.export', $tenant) }}" style="border-color:var(--border);color:var(--text-secondary);">Export JSON</a>
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

            <style>
                #import-form .button-ghost {
                    color: var(--slate-700);
                    border-color: var(--border);
                }
                .import-loading {
                    position: relative;
                    pointer-events: none;
                    opacity: 0.6;
                }
                .import-loading::after {
                    content: '';
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    width: 1.1rem;
                    height: 1.1rem;
                    margin: -0.55rem 0 0 -0.55rem;
                    border: 2px solid var(--border);
                    border-top-color: var(--brand-600, #2563eb);
                    border-radius: 50%;
                    animation: spin 0.6s linear infinite;
                }
                @keyframes spin { to { transform: rotate(360deg); } }
                .import-btn-text { position: relative; z-index: 1; }
                .import-status {
                    display: none;
                    margin-top: 1rem;
                    padding: 0.75rem 1rem;
                    border-radius: var(--radius-xl);
                    font-size: 0.875rem;
                    font-weight: 500;
                }
                .import-status.success { display: block; background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
                .import-status.error { display: block; background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
                .import-status.processing { display: block; background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
            </style>

            <div id="import-status" class="import-status"></div>

            <div style="margin-top:1rem;display:flex;gap:0.75rem;align-items:center;">
                <button class="button" type="submit" id="preview-btn">
                    <span class="import-btn-text">Preview Import</span>
                </button>
                <button class="button button-ghost" type="submit" id="apply-btn" disabled style="border-color:var(--border);">
                    <span class="import-btn-text">Apply Import</span>
                </button>
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
                                    <form method="post" action="{{ route('admin.tenants.endpoints.destroy', $tenant) }}">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="method" value="{{ $endpoint->method }}">
                                        <input type="hidden" name="path" value="{{ $endpoint->path }}">
                                        <input type="hidden" name="force" value="true">
                                        <a class="button button-link button-danger" role="button" href="#" onclick="event.preventDefault(); if (confirm('Delete this endpoint?')) this.closest('form').submit();">Delete</a>
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
        function setFormLoading(form, loading, message) {
            const btns = form.querySelectorAll('button[type="submit"]');
            const inputs = form.querySelectorAll('input, textarea, select');
            const status = document.getElementById('import-status');

            btns.forEach(b => {
                b.disabled = loading;
                if (loading) b.classList.add('import-loading');
                else b.classList.remove('import-loading');
                const txt = b.querySelector('.import-btn-text');
                if (txt) {
                    if (loading) txt.dataset.original = txt.textContent;
                    txt.textContent = loading ? message : (txt.dataset.original || txt.textContent);
                }
            });

            inputs.forEach(i => {
                if (loading) i.dataset.wasDisabled = i.disabled;
                i.disabled = loading ? true : (i.dataset.wasDisabled === 'true');
            });

            if (loading) {
                status.className = 'import-status processing';
                status.textContent = message;
            }
        }

        function showStatus(type, message) {
            const status = document.getElementById('import-status');
            status.className = 'import-status ' + type;
            status.textContent = message;
        }

        async function refreshTable() {
            try {
                const resp = await fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const html = await resp.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newSection = doc.querySelector('.detail-card:last-child');
                const oldSection = document.querySelector('.detail-card:last-child');
                if (newSection && oldSection) oldSection.outerHTML = newSection.outerHTML;
            } catch (_) {}
        }

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
            const actionLabel = isApply ? 'Applying...' : 'Previewing...';

            const formData = new FormData(form);
            if (isApply) {
                formData.set('confirm', '1');
            } else {
                formData.delete('confirm');
            }

            setFormLoading(form, true, actionLabel);

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                const data = await response.json();

                if (!response.ok) {
                    let msg = data.message || 'Request failed';
                    if (data.errors) msg += '\n' + JSON.stringify(data.errors, null, 2);
                    showStatus('error', 'Error: ' + msg);
                    previewDiv.style.display = 'none';
                    return false;
                }

                if (data.status === 'preview') {
                    let html = 'Preview (no changes applied) — ';
                    html += 'Create: ' + (data.endpoints.create.length || 0) + ' | ';
                    html += 'Update: ' + (data.endpoints.update.length || 0);
                    if (data.endpoints.create.length) {
                        html += '\nTo create: ' + data.endpoints.create.join(', ');
                    }
                    if (data.endpoints.update.length) {
                        html += '\nTo update: ' + data.endpoints.update.join(', ');
                    }
                    showStatus('processing', html);
                    previewDiv.style.display = 'none';
                    applyBtn.disabled = false;
                } else if (data.status === 'applied') {
                    showStatus('success', 'Applied! Created: ' + data.created + ' | Updated: ' + data.updated + ' — refreshing table...');
                    applyBtn.disabled = true;
                    previewDiv.style.display = 'none';
                    await refreshTable();
                    setTimeout(() => showStatus('success', 'Done. Created: ' + data.created + ' | Updated: ' + data.updated), 3000);
                }
            } catch (err) {
                showStatus('error', 'Request failed: ' + err.message);
                previewDiv.style.display = 'none';
            } finally {
                setFormLoading(form, false, '');
            }

            return false;
        }
    </script>
@endsection
