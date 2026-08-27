@extends('layouts.admin')

@section('title', $group->name . ' Members | LOA Admin')
@section('content')
    @include('admin.partials.breadcrumbs', ['items' => [
        ['label' => 'Tenants', 'url' => route('admin.tenants')],
        ['label' => $tenant->name, 'url' => route('admin.tenants.show', $tenant)],
        ['label' => 'Groups', 'url' => route('admin.tenants.groups', $tenant)],
        ['label' => $group->name, 'url' => route('admin.tenants.group.show', [$tenant, $group])],
        ['label' => 'Members'],
    ]])
    <div class="page-header">
        <div>
            <h1>{{ $group->name }} — Members</h1>
            <p>Users who are members of this group within "{{ $tenant->name }}".</p>
        </div>
    </div>

    {{-- §12: Add member (two-tier search, multi-select) --}}
    <div class="detail-card">
        <div class="section-header">
            <h2>Add member</h2>
        </div>
        <form id="addMemberForm" method="post" action="{{ route('admin.tenants.group.members.store', [$tenant, $group]) }}">
            @csrf
            <div id="userIdInputs"></div>
            <div id="tierInputs"></div>
            <div class="field" style="position:relative;">
                <input type="text"
                       id="memberSearch"
                       placeholder="Search by name or email…"
                       autocomplete="off"
                       style="height:2.5rem;padding:0.5rem 0.75rem;border:1.5px solid var(--border);border-radius:var(--radius-xl);background:var(--surface-secondary);font-family:inherit;font-size:0.875rem;width:100%;max-width:24rem;">
                <div id="searchResults" style="display:none;position:absolute;top:100%;left:0;right:0;max-width:24rem;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow-lg);z-index:100;max-height:16rem;overflow-y:auto;"></div>
            </div>
            <div id="selectedChips" style="display:flex;flex-wrap:wrap;gap:0.375rem;margin:0.5rem 0;min-height:0;"></div>
            <button class="button" type="submit" id="addMemberBtn" disabled>Add N member(s)</button>
        </form>
    </div>

    {{-- Members list --}}
    <div class="detail-card">
        <div class="section-header">
            <h2>Members ({{ $members->count() }})</h2>
        </div>
        <div class="table-wrap">
            @if ($members->isEmpty())
                <div class="empty-state">No members in this group. Add users above.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Status</th>
                            <th>Other Group Memberships</th>
                            <th>Joined</th>
                            <th class="row-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($members as $member)
                            @php
                                $otherGroups = $member->userGroups
                                    ->where('tenant_id', $tenant->id)
                                    ->where('id', '!=', $group->id);
                            @endphp
                            <tr>
                                <td class="cell-user">
                                    <a href="{{ route('admin.users.show', $member->id) }}"><strong>{{ $member->name }}</strong></a>
                                    <span>{{ $member->email }}</span>
                                </td>
                                <td><span class="badge badge-{{ $member->status }}">{{ $member->status }}</span></td>
                                <td class="muted">
                                    @if ($otherGroups->isNotEmpty())
                                        @foreach ($otherGroups as $mGroup)
                                            <a href="{{ route('admin.tenants.group.show', [$tenant, $mGroup]) }}">{{ $mGroup->name }}</a><br>
                                        @endforeach
                                    @else
                                        <span class="muted">None</span>
                                    @endif
                                </td>
                                <td class="muted">{{ $member->pivot->created_at?->format('M j, Y') ?? '—' }}</td>
                                <td class="row-actions">
                                    <a class="button button-link button-danger"
                                       href="{{ route('admin.tenants.group.members.remove.confirm', [$tenant, $group, $member->id]) }}">Remove</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <script>
    (function () {
        const searchInput = document.getElementById('memberSearch');
        const resultsDiv = document.getElementById('searchResults');
        const chipsDiv = document.getElementById('selectedChips');
        const userIdInputs = document.getElementById('userIdInputs');
        const tierInputs = document.getElementById('tierInputs');
        const addBtn = document.getElementById('addMemberBtn');
        let debounceTimer = null;
        let selected = {};

        function updateForm() {
            userIdInputs.innerHTML = '';
            tierInputs.innerHTML = '';
            var count = Object.keys(selected).length;
            Object.keys(selected).forEach(function (id) {
                var u = selected[id];
                var h = document.createElement('input');
                h.type = 'hidden';
                h.name = 'user_ids[]';
                h.value = id;
                userIdInputs.appendChild(h);

                var t = document.createElement('input');
                t.type = 'hidden';
                t.name = 'tiers[]';
                t.value = u.tier;
                tierInputs.appendChild(t);
            });
            addBtn.disabled = count === 0;
            addBtn.textContent = count > 0 ? 'Add ' + count + ' member(s)' : 'Add N member(s)';
        }

        function renderChips() {
            chipsDiv.innerHTML = '';
            Object.keys(selected).forEach(function (id) {
                var u = selected[id];
                var chip = document.createElement('span');
                chip.style.cssText = 'display:inline-flex;align-items:center;gap:0.25rem;padding:0.25rem 0.5rem;border:1.5px solid ' + (u.tier === 'secondary' ? '#fbbf24' : 'var(--border-accent,#93c5fd)') + ';border-radius:var(--radius-xl);background:' + (u.tier === 'secondary' ? '#fef3c7' : '#eff6ff') + ';font-size:0.8125rem;';
                var tierLabel = u.tier === 'secondary' ? ' (new)' : '';
                chip.innerHTML = '<span style="max-width:14rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + u.name + ' — ' + u.email + ' [' + u.tier + ']">' + u.name + tierLabel + '</span>' +
                    '<button type="button" data-remove="' + id + '" title="Remove" style="background:none;border:none;cursor:pointer;font-size:0.75rem;line-height:1;color:var(--text-secondary);padding:0;">✕</button>';
                chipsDiv.appendChild(chip);
            });
            updateForm();
        }

        function addUser(id, name, email, tier) {
            if (selected[id]) return;
            selected[id] = { name: name, email: email, tier: tier };
            renderChips();
            searchInput.value = '';
            resultsDiv.style.display = 'none';
            searchInput.focus();
        }

        function removeUser(id) {
            delete selected[id];
            renderChips();
        }

        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            const q = this.value.trim();
            if (q.length < 2) { resultsDiv.style.display = 'none'; return; }
            debounceTimer = setTimeout(() => {
                fetch('{{ route("admin.tenants.group.members.search", [$tenant, $group]) }}?q=' + encodeURIComponent(q))
                    .then(r => r.json())
                    .then(json => {
                        if (!json.data || json.data.length === 0) {
                            resultsDiv.innerHTML = '<div style="padding:0.75rem;color:var(--text-muted);">No users found.</div>';
                            resultsDiv.style.display = 'block';
                            return;
                        }
                        resultsDiv.innerHTML = json.data.map(u =>
                            '<div class="search-result" data-id="' + u.id + '" data-name="' + u.name + '" data-email="' + u.email + '" data-tier="' + json.tier + '" style="padding:0.5rem 0.75rem;cursor:pointer;border-bottom:1px solid var(--border);">'
                            + (selected[u.id] ? '<span style="color:#16a34a;">✓</span> ' : '')
                            + '<strong>' + u.name + '</strong>'
                            + '<br><small style="color:var(--text-muted);">' + u.email + '</small>'
                            + '</div>'
                        ).join('');
                        resultsDiv.style.display = 'block';
                        resultsDiv.querySelectorAll('.search-result').forEach(el => {
                            el.addEventListener('click', function () {
                                addUser(this.dataset.id, this.dataset.name, this.dataset.email, this.dataset.tier);
                            });
                        });
                    });
            }, 300);
        });

        chipsDiv.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-remove]');
            if (btn) { removeUser(btn.getAttribute('data-remove')); }
        });

        document.addEventListener('click', function (e) {
            if (!resultsDiv.contains(e.target) && e.target !== searchInput) {
                resultsDiv.style.display = 'none';
            }
        });
    })();
    </script>
@endsection
