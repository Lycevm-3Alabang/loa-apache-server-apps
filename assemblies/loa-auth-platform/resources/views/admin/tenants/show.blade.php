@extends('layouts.admin')

@section('title', $tenant->name . ' | LOA Admin')
@section('content')
    @include('admin.partials.breadcrumbs', ['items' => [
        ['label' => 'Tenants', 'url' => route('admin.tenants')],
        ['label' => $tenant->name],
    ]])
    <div class="page-header">
        <div>
            <h1>{{ $tenant->name }}</h1>
            <p>Tenant detail and membership management.</p>
        </div>
    </div>

    {{-- Tenant info --}}
    <div class="detail-card">
        <h2>Tenant details</h2>
        <div class="detail-grid">
            <div class="detail-field">
                <label>Slug</label>
                <span>{{ $tenant->slug }}</span>
            </div>
            <div class="detail-field">
                <label>Status</label>
                <span><span class="badge badge-{{ $tenant->status === 'active' ? 'active' : 'disabled' }}">{{ $tenant->status }}</span></span>
            </div>
            <div class="detail-field">
                <label>App URL</label>
                <span>{{ $tenant->app_url ?? '—' }}</span>
            </div>
            <div class="detail-field">
                <label>Dev App URL</label>
                <span>{{ $tenant->dev_app_url ?? '—' }}</span>
            </div>
            <div class="detail-field">
                <label>Redirect Origins</label>
                <span>{{ $tenant->redirect_origins ? implode(', ', $tenant->redirect_origins) : '—' }}</span>
            </div>
            <div class="detail-field">
                <label>Dev Redirect Origins</label>
                <span>{{ $tenant->dev_redirect_origins ? implode(', ', $tenant->dev_redirect_origins) : '—' }}</span>
            </div>
            <div class="detail-field">
                <label>Members</label>
                <span>{{ $tenant->users_count }}</span>
            </div>
            <div class="detail-field">
                <label>Created</label>
                <span>{{ $tenant->created_at?->format('M j, Y g:i A') ?? '—' }}</span>
            </div>
        </div>

        <div class="quick-actions" aria-label="Tenant actions">
            <a class="action-tile" href="{{ route('admin.tenants.edit', $tenant) }}">
                <span class="tile-icon tile-slate" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
                </span>
                <span class="tile-body">
                    <strong>Edit tenant</strong>
                    <small>Name, URLs, and redirect origins</small>
                </span>
                <span class="tile-chevron" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </span>
            </a>
            <a class="action-tile" href="{{ route('admin.tenants.groups', $tenant) }}">
                <span class="tile-icon tile-info" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </span>
                <span class="tile-body">
                    <strong>Manage groups</strong>
                    <small>Groups, permissions, and members</small>
                </span>
                <span class="tile-chevron" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </span>
            </a>
            <a class="action-tile" href="{{ route('admin.tenants.endpoints.manage', $tenant) }}">
                <span class="tile-icon tile-violet" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                </span>
                <span class="tile-body">
                    <strong>Manage endpoints</strong>
                    <small>Endpoint catalog and access levels</small>
                </span>
                <span class="tile-chevron" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </span>
            </a>
            <a class="action-tile" href="{{ route('admin.tenants.access-config.import', $tenant) }}">
                <span class="tile-icon tile-brand" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                </span>
                <span class="tile-body">
                    <strong>Import/Export Config</strong>
                    <small>Transfer groups, grants, overrides</small>
                </span>
                <span class="tile-chevron" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </span>
            </a>
        </div>

        <div style="margin-top:1.25rem;">
            <form method="post" action="{{ route('admin.tenants.status', $tenant) }}" style="display:flex;gap:0.5rem;align-items:center;">
                @csrf
                @if ($tenant->status === 'active')
                    <input type="hidden" name="status" value="suspended">
                    <button class="button button-soft-danger" type="submit" onclick="return confirm('Suspend this tenant?');">Suspend tenant</button>
                @else
                    <input type="hidden" name="status" value="active">
                    <button class="button button-soft-success" type="submit">Activate tenant</button>
                @endif
            </form>
        </div>
    </div>

    {{-- Pending import banner --}}
    @include('admin.tenants._import-pending', ['tenant' => $tenant])

    {{-- Members --}}
    <div class="detail-card">
        <div class="section-header">
            <h2>Members ({{ $tenant->users_count }})</h2>
        </div>

        {{-- Add member toolbar: search -> stage -> add, plus bulk import secondary action --}}
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;margin-bottom:1.25rem;">
            @php $stagedGroupCount = 0; @endphp
            <div style="position:relative;flex:1 1 16rem;">
                <input type="text" id="member-search" name="q" autocomplete="off"
                       placeholder="Search by name or email…"
                       style="width:100%;height:2.5rem;padding:0.5rem 0.75rem;border:1.5px solid var(--border);border-radius:var(--radius-xl);background:var(--surface-secondary);font-family:inherit;font-size:0.875rem;">
                <div id="member-suggestions" style="display:none;position:absolute;top:calc(100% + 0.25rem);left:0;right:0;background:var(--surface);border:1.5px solid var(--border);border-radius:var(--radius-lg);box-shadow:var(--shadow-md, 0 8px 24px rgba(0,0,0,0.12));max-height:18rem;overflow-y:auto;z-index:20;"></div>
            </div>

            <span id="staged-user" style="display:none;align-items:center;gap:0.375rem;height:2.5rem;padding:0 0.75rem;border:1.5px solid var(--border-accent,#93c5fd);border-radius:var(--radius-xl);background:#eff6ff;font-size:0.8125rem;">
                <span id="staged-user-label" style="max-width:16rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                <button type="button" id="staged-user-clear" title="Unstage" style="background:none;border:none;cursor:pointer;font-size:0.875rem;line-height:1;color:var(--text-secondary);padding:0;">✕</button>
            </span>

            <form method="post" action="{{ route('admin.tenants.members', $tenant) }}" id="add-member-form" class="inline-form" style="display:flex;">
                @csrf
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="user_id" id="staged-user-id">
                <button class="button" type="submit" id="add-member-btn" disabled>Add to tenant</button>
            </form>

            <a class="button button-ghost" href="{{ route('admin.tenants.members.import', $tenant) }}"
               style="border-color:var(--border);color:var(--text-secondary);height:2.5rem;display:inline-flex;align-items:center;margin-left:auto;">⇪ Import members</a>
        </div>

        {{-- Members list --}}
        <div class="table-wrap">
            @if ($members->isEmpty())
                <div class="empty-state">No members yet. Search for a user above or import a CSV.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Status</th>
                            <th>Group Memberships</th>
                            <th>Joined</th>
                            <th class="row-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($members as $member)
                            <tr>
                                <td class="cell-user">
                                    <strong>{{ $member->name }}</strong>
                                    <span>{{ $member->email }}</span>
                                </td>
                                <td><span class="badge badge-{{ $member->status }}">{{ $member->status }}</span></td>
                                <td class="muted">
                                    @php $tenantScopedGroups = $member->userGroups->where('tenant_id', $tenant->id); @endphp
                                    @if ($tenantScopedGroups->isNotEmpty())
                                        @foreach ($tenantScopedGroups as $mGroup)
                                            <span style="display:inline-flex;align-items:center;gap:0.25rem;margin:0 0.375rem 0.375rem 0;padding:0.125rem 0.5rem;border:1px solid var(--border);border-radius:var(--radius-xl,999px);background:var(--surface-secondary);">
                                                <a href="{{ route('admin.tenants.group.show', [$tenant, $mGroup]) }}">{{ $mGroup->name }}</a>
                                                <form method="post" action="{{ route('admin.groups.members.remove', [$mGroup, $member->id]) }}" style="display:inline;">
                                                    @csrf
                                                    <button type="submit" title="Unenroll from {{ $mGroup->name }}"
                                                            onclick="return confirm('Unenroll this user from {{ $mGroup->name }}?');"
                                                            style="background:none;border:none;cursor:pointer;color:var(--text-secondary);font-size:0.75rem;line-height:1;padding:0;">✕</button>
                                                </form>
                                            </span>
                                        @endforeach
                                    @else
                                        <span class="muted">None</span>
                                    @endif
                                </td>
                                <td class="muted">{{ $member->pivot->created_at?->format('M j, Y') ?? '—' }}</td>
                                <td class="row-actions">
                                    <form method="post" action="{{ route('admin.tenants.members', $tenant) }}">
                                        @csrf
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="user_id" value="{{ $member->id }}">
                                        <a class="button button-link button-danger" role="button" href="#" onclick="event.preventDefault(); if (confirm('Remove this user from the tenant?')) this.closest('form').submit();">Remove</a>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="pagination">
            <span>Showing {{ $members->firstItem() ?? 0 }}–{{ $members->lastItem() ?? 0 }} of {{ $members->total() }}</span>
            {{ $members->links() }}
        </div>
    </div>

    <script>
        (function () {
            var input = document.getElementById('member-search');
            var panel = document.getElementById('member-suggestions');
            var stagedBox = document.getElementById('staged-user');
            var stagedLabel = document.getElementById('staged-user-label');
            var stagedClear = document.getElementById('staged-user-clear');
            var stagedId = document.getElementById('staged-user-id');
            var addBtn = document.getElementById('add-member-btn');
            var addForm = document.getElementById('add-member-form');
            var abortCtrl = null;
            var debounceTimer = null;
            var stagedUser = null;

            function setStaged(user) {
                stagedUser = user || null;
                if (stagedUser) {
                    stagedLabel.textContent = stagedUser.name + ' — ' + stagedUser.email;
                    stagedLabel.title = stagedUser.name + ' — ' + stagedUser.email;
                    stagedId.value = stagedUser.id;
                    stagedBox.style.display = 'inline-flex';
                    addBtn.disabled = false;
                    hidePanel();
                } else {
                    stagedId.value = '';
                    stagedBox.style.display = 'none';
                    addBtn.disabled = true;
                }
            }

            function hidePanel() {
                panel.style.display = 'none';
                panel.innerHTML = '';
            }

            function renderPanel(items, q) {
                if (!items.length) {
                    panel.innerHTML = '<div style="padding:0.5rem 0.75rem;font-size:0.8125rem;color:var(--text-secondary);">No matches for "' +
                        q.replace(/&/g, '&amp;').replace(/</g, '&lt;') + '"</div>';
                    panel.style.display = 'block';
                    return;
                }
                items.forEach(function (u) {
                    var row = document.createElement('div');
                    row.setAttribute('role', 'button');
                    row.style.cssText = 'display:flex;justify-content:space-between;gap:0.75rem;padding:0.5rem 0.75rem;cursor:pointer;font-size:0.8125rem;';
                    row.innerHTML =
                        '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + u.name + '">' + u.name + '</span>' +
                        '<span style="color:var(--text-secondary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + u.email + '">' + u.email + '</span>' +
                        (u.status === 'pending' ? '<span style="color:#b45309;white-space:nowrap;">pending</span>' : '');
                    row.addEventListener('click', function () { setStaged(u); });
                    row.addEventListener('mouseover', function () { row.style.background = 'var(--surface-secondary)'; });
                    row.addEventListener('mouseout', function () { row.style.background = ''; });
                    panel.appendChild(row);
                });
                panel.style.display = 'block';
            }

            input.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                var q = input.value.trim();

                if (q.length < 2) {
                    hidePanel();
                    return;
                }

                debounceTimer = setTimeout(function () {
                    if (abortCtrl) abortCtrl.abort();
                    abortCtrl = new AbortController();

                    panel.innerHTML = '<div style="padding:0.5rem 0.75rem;font-size:0.8125rem;color:var(--text-secondary);">Searching…</div>';
                    panel.style.display = 'block';

                    fetch('{{ route('admin.tenants.members.search', $tenant) }}?q=' + encodeURIComponent(q), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        signal: abortCtrl.signal,
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (json) { renderPanel(json.data || [], q); })
                        .catch(function () { if (panel.innerHTML.indexOf('Searching') !== -1) hidePanel(); });
                }, 250);
            });

            input.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    if (stagedUser) { setStaged(null); } else { hidePanel(); }
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (stagedUser && !addBtn.disabled) { addForm.requestSubmit ? addForm.requestSubmit() : addForm.submit(); }
                }
            });

            stagedClear.addEventListener('click', function () { setStaged(null); input.focus(); });
            document.addEventListener('click', function (e) {
                if (!panel.contains(e.target) && e.target !== input) { hidePanel(); }
            });
        })();
    </script>
@endsection
