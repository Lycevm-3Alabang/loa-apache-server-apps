@extends('layouts.admin')

@section('title', 'Import Members Preview - LOA Admin')

@section('content')
@include('admin.partials.breadcrumbs', ['items' => [
    ['label' => 'Tenants', 'url' => route('admin.tenants')],
    ['label' => $tenant->name, 'url' => route('admin.tenants.show', $tenant)],
    ['label' => 'Import members', 'url' => route('admin.tenants.members.import', $tenant)],
    ['label' => 'Preview'],
]])
<div class="page-header">
    <div>
        <h1>Import Members Preview</h1>
        <p>Review rows before adding them to <strong>{{ $tenant->name }}</strong>.</p>
    </div>
    <a class="button button-ghost" href="{{ route('admin.tenants.members.import', $tenant) }}" style="border-color:var(--border);color:var(--text-secondary);">Start Over</a>
</div>

{{-- Summary --}}
<div class="detail-card" style="margin-bottom:1.5rem;">
    <h2>Import Summary</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(12rem,1fr));gap:1rem;">
        <div><strong>Total Rows:</strong> {{ $summary['total'] }}</div>
        <div><strong>Ready:</strong> {{ $summary['ready'] }}</div>
        <div><strong>Existing Users:</strong> {{ $summary['existing_user'] }}</div>
        <div><strong>Errors:</strong> {{ $summary['errors'] }}</div>
    </div>
</div>

{{-- Filters --}}
<div class="panel-toolbar" style="margin-bottom:1rem;">
    <div class="filters">
        <form id="filter-form" style="display:flex;gap:0.625rem;align-items:center;">
            <div class="field">
                <select name="filter" id="filter" style="width:100%;max-width:200px;">
                    <option value="all">All</option>
                    <option value="ok">OK (Ready + Existing)</option>
                    <option value="errors">With Errors</option>
                    <option value="to_resolve">To Resolve</option>
                </select>
            </div>
            <div class="field">
                <select name="page_size" id="page_size" style="width:100%;max-width:120px;">
                    <option value="25">25 / page</option>
                    <option value="50">50 / page</option>
                    <option value="100">100 / page</option>
                </select>
            </div>
        </form>
    </div>
</div>

{{-- Preview Table --}}
<form id="import-process-form" method="post" action="{{ route('admin.tenants.members.import.process', $tenant) }}">
    @csrf
    <input type="hidden" name="removed_rows" id="removed-rows-input">

    <div class="table-wrap">
        <table id="preview-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>User Group</th>
                    <th>Status</th>
                    <th>Remarks</th>
                    <th style="white-space:nowrap;">Actions</th>
                </tr>
            </thead>
            <tbody data-rows="{{ json_encode($rows) }}">
                @foreach ($rows as $index => $row)
                <tr class="row-{{ $index }}" data-status="{{ $row['status'] }}" data-index="{{ $index }}">
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['email'] }}</td>
                    <td>{{ $row['user_group'] }}</td>
                    <td>
                        @if ($row['status'] === 'ready')
                            <span class="badge badge-active">Ready</span>
                        @elseif ($row['status'] === 'ready_existing')
                            <span class="badge badge-active">Existing User</span>
                        @else
                            <span class="badge badge-disabled">Error</span>
                        @endif
                    </td>
                    <td class="muted">{{ $row['remarks'] }}</td>
                    <td class="row-actions">
                        <a href="#" class="button-link" style="font-size:0.75rem;" onclick="event.preventDefault(); removeRow({{ $index }})">Remove</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</form>

{{-- Confirm & Process --}}
@php
    $readyCount = $summary['ready'] + $summary['existing_user'];
@endphp

<div class="detail-card" style="margin-top:1.5rem;" id="confirm-and-process">
    <h2>Confirm Import</h2>
    <p id="confirm-summary" style="margin-bottom:1rem;">Ready to process {{ $readyCount }} rows ({{ $summary['errors'] }} errors, 0 removed).</p>
    <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
        <input type="checkbox" name="confirm" id="confirm-checkbox" style="width:1rem;height:1rem;">
        I understand this will create/attach users to this tenant and assign their groups.
    </label>
    <button class="button" type="button" id="process-btn" disabled style="margin-top:1rem;">Process Import</button>
</div>

<script>
    const removedRows = [];
    let pageSize = 25;

    function removeRow(index) {
        if (removedRows.includes(index)) return;
        removedRows.push(index);
        document.querySelector('.row-' + index).style.display = 'none';
        updateSummary();
    }

    function updateSummary() {
        const removedCount = removedRows.length;
        const readyCount = {{ $summary['ready'] }} + {{ $summary['existing_user'] }};
        document.querySelector('#confirm-summary').textContent =
            'Ready to process ' + (readyCount - removedCount) + ' rows ({{ $summary['errors'] }} errors, ' + removedCount + ' removed).';
        document.getElementById('removed-rows-input').value = JSON.stringify(removedRows);
    }

    function applyFilter() {
        const filter = document.getElementById('filter').value;
        const rows = document.querySelectorAll('#preview-table tbody tr');
        rows.forEach(row => {
            const status = row.dataset.status;
            if (filter === 'all') {
                row.style.display = '';
            } else if (filter === 'ok') {
                row.style.display = (status === 'ready' || status === 'ready_existing') ? '' : 'none';
            } else if (filter === 'errors' || filter === 'to_resolve') {
                row.style.display = status === 'error' ? '' : 'none';
            }
        });
    }

    function applyPagination() {
        pageSize = parseInt(document.getElementById('page_size').value) || 25;
        renderPage(1);
    }

    let currentPage = 1;

    function renderPage(page) {
        currentPage = page;
        const filter = document.getElementById('filter').value;
        const allRows = Array.from(document.querySelectorAll('#preview-table tbody tr'))
            .filter(row => !removedRows.includes(parseInt(row.dataset.index)));

        let visibleRows = allRows.filter(row => {
            const status = row.dataset.status;
            if (filter === 'all') return true;
            if (filter === 'ok') return status === 'ready' || status === 'ready_existing';
            if (filter === 'errors' || filter === 'to_resolve') return status === 'error';
            return true;
        });

        const totalPages = Math.ceil(visibleRows.length / pageSize) || 1;
        const start = (currentPage - 1) * pageSize;
        const end = start + pageSize;

        allRows.forEach(row => row.style.display = 'none');
        visibleRows.slice(start, end).forEach(row => row.style.display = '');

        renderPagination(visibleRows.length, totalPages);
    }

    function renderPagination(totalRows, totalPages) {
        let container = document.getElementById('pagination');
        if (!container) {
            container = document.createElement('div');
            container.id = 'pagination';
            container.style.marginTop = '1rem';
            document.querySelector('.table-wrap').appendChild(container);
        }

        let html = '<div style="display:flex;gap:0.5rem;align-items:center;justify-content:center;">';
        html += '<button type="button" ' + (currentPage <= 1 ? 'disabled' : '') + ' onclick="renderPage(' + (currentPage - 1) + ')" class="button button-ghost" style="height:2rem;font-size:0.75rem;">Prev</button>';
        for (let i = 1; i <= totalPages; i++) {
            html += '<button type="button" onclick="renderPage(' + i + ')" class="button button-ghost" style="height:2rem;font-size:0.75rem;' + (i === currentPage ? 'font-weight:bold;border-color:var(--border-accent);' : '') + '">' + i + '</button>';
        }
        html += '<button type="button" ' + (currentPage >= totalPages ? 'disabled' : '') + ' onclick="renderPage(' + (currentPage + 1) + ')" class="button button-ghost" style="height:2rem;font-size:0.75rem;">Next</button>';
        html += '</div>';
        container.innerHTML = html;
    }

    document.getElementById('filter').addEventListener('change', function() {
        applyFilter();
        renderPage(1);
    });

    document.getElementById('page_size').addEventListener('change', applyPagination);

    document.getElementById('process-btn').addEventListener('click', function() {
        if (!document.getElementById('confirm-checkbox').checked) {
            alert('Please confirm the import.');
            return;
        }

        const btn = this;
        btn.disabled = true;
        runChunkedImport(btn);
    });

    async function runChunkedImport(btn) {
        const originalText = btn.textContent;
        const summaryEl = document.getElementById('confirm-summary');
        let cursor = 0;
        let aggProcessed = 0;
        let aggFailed = 0;

        try {
            while (true) {
                const form = document.getElementById('import-process-form');
                const body = new URLSearchParams(new FormData(form));
                body.set('cursor', String(cursor));

                btn.textContent = 'Processing... ' + (aggProcessed + aggFailed) + ' done';

                const response = await fetch('{{ route('admin.tenants.members.import.process', $tenant) }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': '{{ csrf_token() }}',
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: body,
                });

                const data = await response.json();

                if (data.status !== 'applied') {
                    alert(data.message || 'An error occurred during import.');
                    return;
                }

                aggProcessed += data.processed;
                aggFailed += data.failed;
                cursor = data.next_cursor;

                summaryEl.textContent = 'Processing... ' + (aggProcessed + aggFailed) + ' of ' + data.total + ' rows (' + aggFailed + ' failed so far).';

                if (data.done) {
                    refreshResults({ processed: aggProcessed, failed: aggFailed, total_failed: aggFailed });
                    return;
                }
            }
        } catch (error) {
            alert('Network error after processing ' + (aggProcessed + aggFailed) + ' rows. Your progress is saved - click Process Import again to resume.');
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    function refreshResults(data) {
        const confirmCard = document.querySelector('#confirm-and-process');
        if (!confirmCard) return;

        let html = '<div class="detail-card" style="margin-top:1.5rem;" id="confirm-and-process">';
        html += '<h2>Import Results</h2>';
        html += '<p style="margin-bottom:1rem;"><strong>Successful:</strong> ' + data.processed + '<br><strong>Failed:</strong> ' + data.failed + '</p>';

        if (data.failed > 0) {
            html += '<div style="margin-bottom:1rem;"><a href="{{ route('admin.tenants.members.import.failed', $tenant) }}" class="button button-ghost">Download Failed Rows</a></div>';
        }

        html += '<a class="button button-ghost" href="{{ route('admin.tenants.members.import', $tenant) }}" style="border-color:var(--border);color:var(--text-secondary);margin-top:1rem;">Start Over</a>';
        html += '</div>';

        confirmCard.innerHTML = html;

        confirmCard.scrollIntoView({ behavior: 'smooth' });
    }

    document.getElementById('confirm-checkbox').addEventListener('change', function() {
        document.getElementById('process-btn').disabled = !this.checked;
    });

    renderPage(1);
</script>
@endsection
