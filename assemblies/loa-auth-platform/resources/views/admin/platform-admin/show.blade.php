@extends('layouts.admin')

@section('title', 'Platform Admin | LOA Admin')
@section('content')
    @include('admin.partials.breadcrumbs', ['items' => [
        ['label' => 'Platform Admin'],
    ]])
    <div class="page-header">
        <div>
            <h1>Platform Admin</h1>
            <p>Manage platform administrator membership. Adding a member automatically grants <strong>Platform Admin</strong> (<code>loa-auth-admin</code>).</p>
        </div>
    </div>

    {{-- Members --}}
    <div class="detail-card">
        <div class="section-header" style="display:flex;justify-content:space-between;align-items:center;">
            <h2>Members ({{ $members->total() }})</h2>
            <div style="display:flex;gap:0.5rem;align-items:center;">
                <button class="button button-ghost" type="button" id="toggle-create-user"
                        style="border-color:var(--border);color:var(--text-secondary);height:2rem;font-size:0.8125rem;">+ Create user</button>
            </div>
        </div>

        {{-- Inline create user form: no group dropdown, always Platform Admin (loa-auth-admin) --}}
        <div id="create-user-form" style="display:none;margin-bottom:1.25rem;padding:1rem;border:1.5px solid var(--border);border-radius:var(--radius-lg);background:var(--surface-secondary);">
            <form method="post" action="{{ route('admin.platform-admin.users.store') }}" style="display:flex;gap:0.75rem;align-items:flex-end;flex-wrap:wrap;">
                @csrf
                <div style="flex:1 1 10rem;">
                    <label for="cu-name" style="display:block;font-size:0.75rem;font-weight:600;margin-bottom:0.25rem;">Name</label>
                    <input type="text" id="cu-name" name="name" required value="{{ old('name') }}"
                           style="width:100%;height:2.25rem;padding:0 0.5rem;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:0.8125rem;">
                </div>
                <div style="flex:1 1 14rem;">
                    <label for="cu-email" style="display:block;font-size:0.75rem;font-weight:600;margin-bottom:0.25rem;">Email</label>
                    <input type="email" id="cu-email" name="email" required value="{{ old('email') }}"
                           style="width:100%;height:2.25rem;padding:0 0.5rem;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:0.8125rem;">
                </div>
                <div style="flex:1 1 10rem;">
                    <label style="display:block;font-size:0.75rem;font-weight:600;margin-bottom:0.25rem;">Group</label>
                    <div style="height:2.25rem;display:flex;align-items:center;padding:0 0.5rem;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:0.8125rem;background:var(--surface);color:var(--text-secondary);">Platform Admin</div>
                </div>
                <button class="button" type="submit" style="height:2.25rem;font-size:0.8125rem;">Create &amp; Invite</button>
                <button class="button button-ghost" type="button" id="cancel-create-user"
                        style="height:2.25rem;font-size:0.8125rem;border-color:var(--border);color:var(--text-secondary);">Cancel</button>
            </form>
            <p class="muted" style="margin:0.5rem 0 0;font-size:0.75rem;">New member is automatically added to Platform Admin (<code>{{ $adminGroup->name }}</code>) and receives a set-password email.</p>
        </div>

        {{-- Add member toolbar: search -> multi-select chips -> batch add --}}
        <div style="margin-bottom:1.25rem;">
            <div style="position:relative;margin-bottom:0.5rem;">
                <input type="text" id="member-search" name="q" autocomplete="off"
                       placeholder="Search by name or email…"
                       style="width:100%;height:2.5rem;padding:0.5rem 0.75rem;border:1.5px solid var(--border);border-radius:var(--radius-xl);background:var(--surface-secondary);font-family:inherit;font-size:0.875rem;">
                <div id="member-suggestions" style="display:none;position:absolute;top:calc(100% + 0.25rem);left:0;right:0;background:var(--surface);border:1.5px solid var(--border);border-radius:var(--radius-lg);box-shadow:var(--shadow-md, 0 8px 24px rgba(0,0,0,0.12));max-height:18rem;overflow-y:auto;z-index:20;"></div>
            </div>

            <div id="selected-chips" style="display:flex;flex-wrap:wrap;gap:0.375rem;margin-bottom:0.5rem;min-height:0;"></div>

            <form method="post" action="{{ route('admin.platform-admin.members.store') }}" id="add-member-form" class="inline-form">
                @csrf
                <div id="user-id-inputs"></div>
                <button class="button" type="submit" id="add-member-btn" disabled>Add N member(s)</button>
            </form>
            <p class="muted" style="margin:0.5rem 0 0;font-size:0.75rem;">Adding an existing user grants Platform Admin immediately. No email is sent.</p>
        </div>

        {{-- Members list --}}
        <div class="table-wrap">
            @if ($members->isEmpty())
                <div class="empty-state">No platform admin members yet. Search for a user above or create one.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Status</th>
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
                                <td class="muted">{{ $member->pivot->created_at?->format('M j, Y') ?? '—' }}</td>
                                <td class="row-actions">
                                    <form method="post" action="{{ route('admin.platform-admin.members.remove', $member->id) }}">
                                        @csrf
                                        <a class="button button-link button-danger" role="button" href="#" onclick="event.preventDefault(); if (confirm('Revoke this user\'s platform admin membership? The user stays in the system.')) this.closest('form').submit();">Remove</a>
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
            var chipsContainer = document.getElementById('selected-chips');
            var userIdInputs = document.getElementById('user-id-inputs');
            var addBtn = document.getElementById('add-member-btn');
            var abortCtrl = null;
            var debounceTimer = null;
            var selected = {};

            function updateForm() {
                userIdInputs.innerHTML = '';
                var count = Object.keys(selected).length;
                Object.keys(selected).forEach(function (id) {
                    var h = document.createElement('input');
                    h.type = 'hidden';
                    h.name = 'user_ids[]';
                    h.value = id;
                    userIdInputs.appendChild(h);
                });
                addBtn.disabled = count === 0;
                addBtn.textContent = count > 0 ? 'Add ' + count + ' member(s)' : 'Add N member(s)';
            }

            function renderChips() {
                chipsContainer.innerHTML = '';
                Object.keys(selected).forEach(function (id) {
                    var u = selected[id];
                    var chip = document.createElement('span');
                    chip.style.cssText = 'display:inline-flex;align-items:center;gap:0.25rem;padding:0.25rem 0.5rem;border:1.5px solid var(--border-accent,#93c5fd);border-radius:var(--radius-xl);background:#eff6ff;font-size:0.8125rem;';
                    chip.textContent = u.name;
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.setAttribute('data-remove', id);
                    btn.title = 'Remove';
                    btn.style.cssText = 'background:none;border:none;cursor:pointer;font-size:0.75rem;line-height:1;color:var(--text-secondary);padding:0;';
                    btn.textContent = '✕';
                    chip.appendChild(btn);
                    chipsContainer.appendChild(chip);
                });
                updateForm();
            }

            function addUser(u) {
                if (selected[u.id]) return;
                selected[u.id] = u;
                renderChips();
                hidePanel();
                input.value = '';
                input.focus();
            }

            function removeUser(id) {
                delete selected[id];
                renderChips();
            }

            function hidePanel() {
                panel.style.display = 'none';
                panel.innerHTML = '';
            }

            function escapeHtml(s) {
                return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            function renderPanel(items, q) {
                panel.innerHTML = '';
                if (!items.length) {
                    panel.innerHTML = '<div style="padding:0.5rem 0.75rem;font-size:0.8125rem;color:var(--text-secondary);">No matches for "' + escapeHtml(q) + '"</div>';
                    panel.style.display = 'block';
                    return;
                }
                items.forEach(function (u) {
                    var row = document.createElement('div');
                    row.setAttribute('role', 'button');
                    row.style.cssText = 'display:flex;justify-content:space-between;gap:0.75rem;padding:0.5rem 0.75rem;cursor:pointer;font-size:0.8125rem;';
                    var checkMark = selected[u.id] ? '<span style="color:#16a34a;">✓</span> ' : '';
                    row.innerHTML =
                        '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + checkMark + escapeHtml(u.name) + '</span>' +
                        '<span style="color:var(--text-secondary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + escapeHtml(u.email) + '</span>' +
                        (u.status === 'pending' ? '<span style="color:#b45309;white-space:nowrap;">pending</span>' : '');
                    row.addEventListener('click', function () { addUser(u); });
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

                    fetch('{{ route('admin.platform-admin.members.search') }}?q=' + encodeURIComponent(q), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        signal: abortCtrl.signal,
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (json) { renderPanel(json.data || [], q); })
                        .catch(function () { /* aborted or failed; leave panel as-is */ });
                }, 250);
            });

            input.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    hidePanel();
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                }
            });

            chipsContainer.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-remove]');
                if (btn) { removeUser(btn.getAttribute('data-remove')); }
            });

            document.addEventListener('click', function (e) {
                if (!panel.contains(e.target) && e.target !== input) { hidePanel(); }
            });
        })();

        var toggleBtn = document.getElementById('toggle-create-user');
        var cancelBtn = document.getElementById('cancel-create-user');
        var createForm = document.getElementById('create-user-form');

        if (toggleBtn && createForm) {
            toggleBtn.addEventListener('click', function () {
                createForm.style.display = createForm.style.display === 'none' ? 'block' : 'none';
                if (createForm.style.display === 'block') {
                    document.getElementById('cu-name').focus();
                }
            });
        }
        if (cancelBtn && createForm) {
            cancelBtn.addEventListener('click', function () {
                createForm.style.display = 'none';
            });
        }
    </script>
@endsection
