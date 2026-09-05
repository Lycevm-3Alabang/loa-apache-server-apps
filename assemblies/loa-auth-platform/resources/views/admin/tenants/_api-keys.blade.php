@php
    $apiKeys = \App\Models\TenantApiKey::where('tenant_id', $tenant->id)->orderByDesc('created_at')->get();
@endphp

<div class="detail-card">
    <div class="section-header" style="display:flex;justify-content:space-between;align-items:center;">
        <h2>API Keys</h2>
        <button class="button button-ghost" type="button" id="toggle-create-api-key"
                style="border-color:var(--border);color:var(--text-secondary);height:2rem;font-size:0.8125rem;">+ Generate API Key</button>
    </div>
    <p style="font-size:0.8125rem;color:var(--text-secondary);margin-bottom:1rem;">
        API keys allow tenant applications to manage members programmatically via <code>X-Api-Key</code> header.
        Keys are <strong>not visible to tenant accounts</strong> — platform-admin only.
    </p>

    {{-- Inline create form --}}
    <div id="create-api-key-form" style="display:none;margin-bottom:1.25rem;padding:1rem;border:1.5px solid var(--border);border-radius:var(--radius-lg);background:var(--surface-secondary);">
        <div id="api-key-error" style="display:none;margin-bottom:0.75rem;padding:0.5rem 0.75rem;border:1.5px solid #dc2626;border-radius:var(--radius-sm);background:#fef2f2;color:#991b1b;font-size:0.8125rem;"></div>
        <form id="api-key-create-form" style="display:flex;gap:0.75rem;align-items:flex-end;flex-wrap:wrap;">
            <div style="flex:1 1 12rem;">
                <label for="ak-name" style="display:block;font-size:0.75rem;font-weight:600;margin-bottom:0.25rem;">Key Name</label>
                <input type="text" id="ak-name" name="name" required placeholder="e.g. Production App"
                       style="width:100%;height:2.25rem;padding:0 0.5rem;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:0.8125rem;">
            </div>
            <div style="flex:1 1 12rem;">
                <label for="ak-expires" style="display:block;font-size:0.75rem;font-weight:600;margin-bottom:0.25rem;">Expires (optional)</label>
                <input type="date" id="ak-expires" name="expires_at"
                       style="width:100%;height:2.25rem;padding:0 0.5rem;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:0.8125rem;">
            </div>
            <button class="button" type="submit" id="api-key-submit" style="height:2.25rem;font-size:0.8125rem;">Generate</button>
            <button class="button button-ghost" type="button" id="cancel-create-api-key"
                    style="height:2.25rem;font-size:0.8125rem;border-color:var(--border);color:var(--text-secondary);">Cancel</button>
        </form>
    </div>

    {{-- Secret display (shown after creation) --}}
    <div id="api-key-secret-display" style="display:none;margin-bottom:1.25rem;padding:1rem;border:2px solid #16a34a;border-radius:var(--radius-lg);background:#f0fdf4;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem;">
            <strong style="color:#16a34a;">API Key Generated</strong>
            <button type="button" id="close-secret-display" style="background:none;border:none;cursor:pointer;font-size:1.125rem;color:var(--text-secondary);">✕</button>
        </div>
        <p style="font-size:0.8125rem;color:#166534;margin-bottom:0.75rem;">
            Save the secret now. It will <strong>not</strong> be shown again.
        </p>
        <div style="margin-bottom:0.5rem;">
            <label style="display:block;font-size:0.75rem;font-weight:600;margin-bottom:0.25rem;">Key</label>
            <div style="display:flex;gap:0.5rem;align-items:center;">
                <code id="secret-key-value" style="flex:1;padding:0.5rem;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);font-size:0.8125rem;word-break:break-all;"></code>
                <button type="button" class="copy-btn" data-target="secret-key-value" style="height:2rem;font-size:0.75rem;">Copy</button>
            </div>
        </div>
        <div>
            <label style="display:block;font-size:0.75rem;font-weight:600;margin-bottom:0.25rem;">Secret</label>
            <div style="display:flex;gap:0.5rem;align-items:center;">
                <code id="secret-secret-value" style="flex:1;padding:0.5rem;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);font-size:0.8125rem;word-break:break-all;"></code>
                <button type="button" class="copy-btn" data-target="secret-secret-value" style="height:2rem;font-size:0.75rem;">Copy</button>
            </div>
        </div>
        <div style="margin-top:0.75rem;">
            <label style="display:block;font-size:0.75rem;font-weight:600;margin-bottom:0.25rem;">Header Example</label>
            <code id="secret-header-example" style="display:block;padding:0.5rem;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);font-size:0.8125rem;word-break:break-all;"></code>
        </div>
    </div>

    {{-- Keys table --}}
    @if ($apiKeys->isEmpty())
        <div class="empty-state">No API keys generated yet.</div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Key Preview</th>
                        <th>Created</th>
                        <th>Last Used</th>
                        <th>Expires</th>
                        <th>Status</th>
                        <th class="row-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($apiKeys as $key)
                        <tr>
                            <td><strong>{{ $key->name }}</strong></td>
                            <td><code style="font-size:0.8125rem;">tk_****</code></td>
                            <td class="muted">{{ $key->created_at?->format('M j, Y g:i A') ?? '—' }}</td>
                            <td class="muted">{{ $key->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                            <td class="muted">{{ $key->expires_at?->format('M j, Y') ?? 'Never' }}</td>
                            <td>
                                @if ($key->revoked_at)
                                    <span class="badge badge-disabled">Revoked</span>
                                @elseif ($key->expires_at && $key->expires_at->isPast())
                                    <span class="badge badge-disabled">Expired</span>
                                @else
                                    <span class="badge badge-active">Active</span>
                                @endif
                            </td>
                            <td class="row-actions">
                                @if (!$key->revoked_at)
                                    <form method="post" action="{{ route('admin.tenants.api-keys.destroy', [$tenant, $key->id]) }}" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="button button-link button-danger"
                                                onclick="return confirm('Revoke this API key? The tenant app will lose access immediately.');">Revoke</button>
                                    </form>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<script>
    (function () {
        var toggleBtn = document.getElementById('toggle-create-api-key');
        var cancelBtn = document.getElementById('cancel-create-api-key');
        var createForm = document.getElementById('create-api-key-form');
        var apiForm = document.getElementById('api-key-create-form');
        var submitBtn = document.getElementById('api-key-submit');
        var errorBox = document.getElementById('api-key-error');
        var secretDisplay = document.getElementById('api-key-secret-display');
        var closeSecretBtn = document.getElementById('close-secret-display');

        if (toggleBtn && createForm) {
            toggleBtn.addEventListener('click', function () {
                createForm.style.display = createForm.style.display === 'none' ? 'block' : 'none';
                errorBox.style.display = 'none';
                if (createForm.style.display === 'block') {
                    document.getElementById('ak-name').focus();
                }
            });
        }
        if (cancelBtn && createForm) {
            cancelBtn.addEventListener('click', function () {
                createForm.style.display = 'none';
                errorBox.style.display = 'none';
            });
        }
        if (closeSecretBtn && secretDisplay) {
            closeSecretBtn.addEventListener('click', function () {
                secretDisplay.style.display = 'none';
            });
        }

        if (apiForm) {
            apiForm.addEventListener('submit', function (e) {
                e.preventDefault();
                errorBox.style.display = 'none';
                submitBtn.disabled = true;
                submitBtn.textContent = 'Generating...';

                var name = document.getElementById('ak-name').value.trim();
                var expires = document.getElementById('ak-expires').value;

                var body = { name: name };
                if (expires) body.expires_at = expires;

                fetch('{{ route("admin.tenants.api-keys.store", $tenant) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(body)
                })
                .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, status: res.status, data: data }; }); })
                .then(function (result) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Generate';

                    if (!result.ok) {
                        var msg = result.data.message || 'Validation failed.';
                        if (result.data.errors) {
                            var parts = [];
                            for (var field in result.data.errors) {
                                parts.push(result.data.errors[field].join(' '));
                            }
                            msg = parts.join(' ');
                        }
                        errorBox.textContent = msg;
                        errorBox.style.display = 'block';
                        return;
                    }

                    document.getElementById('secret-key-value').textContent = result.data.key;
                    document.getElementById('secret-secret-value').textContent = result.data.secret;
                    document.getElementById('secret-header-example').textContent = 'X-Api-Key: ' + result.data.key + ':' + result.data.secret;

                    apiForm.reset();
                    createForm.style.display = 'none';
                    secretDisplay.style.display = 'block';
                    secretDisplay.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                })
                .catch(function () {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Generate';
                    errorBox.textContent = 'Network error. Please try again.';
                    errorBox.style.display = 'block';
                });
            });
        }

        document.querySelectorAll('.copy-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetId = btn.getAttribute('data-target');
                var el = document.getElementById(targetId);
                if (el) {
                    navigator.clipboard.writeText(el.textContent).then(function () {
                        btn.textContent = 'Copied!';
                        setTimeout(function () { btn.textContent = 'Copy'; }, 1500);
                    });
                }
            });
        });
    })();
</script>
