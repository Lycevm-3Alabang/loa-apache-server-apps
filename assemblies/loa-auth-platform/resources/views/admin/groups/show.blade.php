@extends('layouts.admin')

@section('title', $group->name . ' | LOA Admin')
@section('content')
    @include('admin.partials.breadcrumbs', ['items' => [
        ['label' => 'Groups', 'url' => route('admin.groups')],
        ['label' => $group->name],
    ]])
    <div class="page-header">
        <div>
            <h1>{{ $group->name }}</h1>
            <p>Group detail, permissions, and membership management.</p>
        </div>
    </div>

    {{-- Group info --}}
    <div class="detail-card">
        <h2>Group details</h2>
        <div class="detail-grid">
            <div class="detail-field">
                <label>Name</label>
                <span>{{ $group->name }}</span>
            </div>
            <div class="detail-field">
                <label>Description</label>
                <span>{{ $group->description ?? '—' }}</span>
            </div>
            <div class="detail-field">
                <label>Priority</label>
                <span>{{ $group->priority }} ({{ $group->priority === 1 ? 'highest' : ($group->priority <= 5 ? 'high' : 'normal') }})</span>
            </div>
            <div class="detail-field">
                <label>Scope</label>
                <span>{{ $group->tenant_id ? 'Tenant' : 'Platform' }}</span>
            </div>
            <div class="detail-field">
                <label>Members</label>
                <span>{{ $group->users_count ?? $group->users->count() }}</span>
            </div>
            <div class="detail-field">
                <label>Created</label>
                <span>{{ $group->created_at?->format('M j, Y g:i A') ?? '—' }}</span>
            </div>
        </div>
    </div>

    {{-- Permissions --}}
    <div class="detail-card">
        <div class="section-header">
            <h2>Permissions</h2>
        </div>
        <form method="post" action="{{ route('admin.groups.permissions', $group) }}">
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

    {{-- §12 M6: Members — platform groups only --}}
    @if ($group->tenant_id === null)
    <div class="detail-card">
        <div class="section-header">
            <h2>Members ({{ $group->users->count() }})</h2>
        </div>

        {{-- Add member (search-first multi-select) --}}
        <div style="margin-bottom:1.25rem;">
            <div style="position:relative;margin-bottom:0.5rem;">
                <input type="text" id="member-search" autocomplete="off"
                       placeholder="Search by name or email…"
                       style="width:100%;max-width:24rem;height:2.5rem;padding:0.5rem 0.75rem;border:1.5px solid var(--border);border-radius:var(--radius-xl);background:var(--surface-secondary);font-family:inherit;font-size:0.875rem;">
                <div id="member-suggestions" style="display:none;position:absolute;top:calc(100% + 0.25rem);left:0;right:0;max-width:24rem;background:var(--surface);border:1.5px solid var(--border);border-radius:var(--radius-lg);box-shadow:var(--shadow-md, 0 8px 24px rgba(0,0,0,0.12));max-height:18rem;overflow-y:auto;z-index:20;"></div>
            </div>
            <div id="selected-chips" style="display:flex;flex-wrap:wrap;gap:0.375rem;margin-bottom:0.5rem;min-height:0;"></div>
            <form method="post" action="{{ route('admin.groups.members.store', $group) }}" id="add-member-form" class="inline-form">
                @csrf
                <div id="user-id-inputs"></div>
                <button class="button" type="submit" id="add-member-btn" disabled>Add N member(s)</button>
            </form>
        </div>

        {{-- Members list --}}
        <div class="table-wrap">
            @if ($group->users->isEmpty())
                <div class="empty-state">No members yet. Add users above.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Status</th>
                            <th class="row-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group->users as $member)
                            <tr>
                                <td class="cell-user">
                                    <strong>{{ $member->name }}</strong>
                                    <span>{{ $member->email }}</span>
                                </td>
                                <td><span class="badge badge-{{ $member->status }}">{{ $member->status }}</span></td>
                                <td class="row-actions">
                                    <form method="post" action="{{ route('admin.groups.members.remove', [$group, $member->id]) }}">
                                        @csrf
                                        <a class="button button-link button-danger" role="button" href="#" onclick="event.preventDefault(); if (confirm('Remove this user from the group?')) this.closest('form').submit();">Remove</a>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
    @endif

    <script>
    (function () {
        var input = document.getElementById('member-search');
        if (!input) return;
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
                chip.innerHTML = '<span style="max-width:14rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + u.name + ' — ' + u.email + '">' + u.name + '</span>' +
                    '<button type="button" data-remove="' + id + '" title="Remove" style="background:none;border:none;cursor:pointer;font-size:0.75rem;line-height:1;color:var(--text-secondary);padding:0;">✕</button>';
                chipsContainer.appendChild(chip);
            });
            updateForm();
        }

        function addUser(u) {
            if (selected[u.id]) return;
            selected[u.id] = u;
            renderChips();
            panel.style.display = 'none';
            panel.innerHTML = '';
            input.value = '';
            input.focus();
        }

        function removeUser(id) {
            delete selected[id];
            renderChips();
        }

        input.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            var q = input.value.trim();
            if (q.length < 2) { panel.style.display = 'none'; return; }
            debounceTimer = setTimeout(function () {
                if (abortCtrl) abortCtrl.abort();
                abortCtrl = new AbortController();
                panel.innerHTML = '<div style="padding:0.5rem 0.75rem;font-size:0.8125rem;color:var(--text-secondary);">Searching…</div>';
                panel.style.display = 'block';
                fetch('{{ route("admin.groups.members.search", $group) }}?q=' + encodeURIComponent(q), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: abortCtrl.signal,
                })
                    .then(function (r) { return r.json(); })
                    .then(function (json) {
                        var items = json.data || [];
                        if (!items.length) {
                            panel.innerHTML = '<div style="padding:0.5rem 0.75rem;font-size:0.8125rem;color:var(--text-secondary);">No matches</div>';
                            return;
                        }
                        panel.innerHTML = '';
                        items.forEach(function (u) {
                            var row = document.createElement('div');
                            row.setAttribute('role', 'button');
                            row.style.cssText = 'display:flex;justify-content:space-between;gap:0.75rem;padding:0.5rem 0.75rem;cursor:pointer;font-size:0.8125rem;';
                            var check = selected[u.id] ? '<span style="color:#16a34a;">✓</span> ' : '';
                            row.innerHTML = '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + u.name + '">' + check + u.name + '</span>' +
                                '<span style="color:var(--text-secondary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + u.email + '">' + u.email + '</span>';
                            row.addEventListener('click', function () { addUser(u); });
                            row.addEventListener('mouseover', function () { row.style.background = 'var(--surface-secondary)'; });
                            row.addEventListener('mouseout', function () { row.style.background = ''; });
                            panel.appendChild(row);
                        });
                    })
                    .catch(function () {});
            }, 250);
        });

        chipsContainer.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-remove]');
            if (btn) { removeUser(btn.getAttribute('data-remove')); }
        });

        document.addEventListener('click', function (e) {
            if (!panel.contains(e.target) && e.target !== input) { panel.style.display = 'none'; }
        });
    })();
    </script>
@endsection
