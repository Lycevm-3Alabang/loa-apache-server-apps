@php
    $pendingRows = collect(session('tenant_member_import_rows', []));
    $pendingReady = $pendingRows->whereIn('status', ['ready', 'ready_existing'])->count();
    $pendingFile = session('tenant_member_import_file', '');
@endphp

@if ($pendingRows->isNotEmpty())
<div class="detail-card" style="margin-bottom:1.5rem;border-color:var(--border-accent,#93c5fd);background:#eff6ff;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
        <div>
            <strong>ⓘ Pending member import</strong>
            <div class="muted" style="font-size:0.8125rem;margin-top:0.25rem;">
                {{ $pendingFile !== '' ? "\"{$pendingFile}\" — " : '' }}{{ $pendingReady }} row{{ $pendingReady === 1 ? '' : 's' }} waiting to be processed.
                Completed batches are already saved.
            </div>
        </div>
        <div style="display:flex;gap:0.5rem;align-items:center;">
            @if (isset($tenant) && $tenant)
                <a class="button button-ghost" href="{{ route('admin.tenants.members.import', $tenant) }}" style="border-color:var(--border);">Resume</a>
                <form method="post" action="{{ route('admin.tenants.members.import.discard', $tenant) }}"
                      onsubmit="return confirm('Discard this pending import? Unprocessed rows will be lost.');">
                    @csrf
                    <button class="button button-ghost" type="submit" style="border-color:var(--border);color:var(--text-secondary);">Discard</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endif
