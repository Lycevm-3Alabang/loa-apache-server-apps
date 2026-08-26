@extends('layouts.admin')

@section('title', 'Audit log | LOA Admin')
@section('content')
    @include('admin.partials.breadcrumbs', ['items' => [
        ['label' => 'Audit log'],
    ]])
    <div class="page-header">
        <div>
            <h1>Audit log</h1>
            <p>Append-only evidence of privileged admin actions. Entries can never be edited or removed.</p>
        </div>
        <a class="button" href="{{ route('admin.audit-logs.export', request()->query()) }}">Export CSV</a>
    </div>

    <div class="panel" style="margin-bottom:1rem;">
        <form method="get" action="{{ route('admin.audit-logs') }}" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:end;">
            <div>
                <label style="display:block;font-size:.75rem;color:#64748b;">Action</label>
                <input type="text" name="action" value="{{ $filters['action'] }}" placeholder="admin_group." style="height:2.25rem;border:1px solid #cbd5e1;border-radius:.5rem;padding:0 .6rem;">
            </div>
            <div>
                <label style="display:block;font-size:.75rem;color:#64748b;">Actor email</label>
                <input type="text" name="actor" value="{{ $filters['actor'] }}" placeholder="@lyceumalabang.edu.ph" style="height:2.25rem;border:1px solid #cbd5e1;border-radius:.5rem;padding:0 .6rem;">
            </div>
            <div>
                <label style="display:block;font-size:.75rem;color:#64748b;">Entity (type:id)</label>
                <input type="text" name="entity" value="{{ $filters['entity'] }}" placeholder="user:uuid" style="height:2.25rem;border:1px solid #cbd5e1;border-radius:.5rem;padding:0 .6rem;">
            </div>
            <div>
                <label style="display:block;font-size:.75rem;color:#64748b;">From</label>
                <input type="date" name="from" value="{{ $filters['from'] }}" style="height:2.25rem;border:1px solid #cbd5e1;border-radius:.5rem;padding:0 .6rem;">
            </div>
            <div>
                <label style="display:block;font-size:.75rem;color:#64748b;">To</label>
                <input type="date" name="to" value="{{ $filters['to'] }}" style="height:2.25rem;border:1px solid #cbd5e1;border-radius:.5rem;padding:0 .6rem;">
            </div>
            <button class="button" type="submit">Filter</button>
        </form>
    </div>

    <div class="panel">
        <div class="table-wrap">
            @if ($logs->isEmpty())
                <div class="empty-state">No audit entries match the current filters.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Actor</th>
                            <th>Action</th>
                            <th>Entity</th>
                            <th>Details</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td class="muted">{{ $log->created_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                                <td>{{ $log->actor_email ?? '—' }}</td>
                                <td><strong>{{ $log->action }}</strong></td>
                                <td class="muted">
                                    {{ $log->entity_type ? $log->entity_type.':'.Str::limit((string) $log->entity_id, 13, '…') : '—' }}
                                </td>
                                <td class="muted" style="max-width:22rem;overflow-wrap:anywhere;">
                                    {{ $log->details ? json_encode($log->details) : '—' }}
                                </td>
                                <td class="muted">{{ $log->ip_address ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div style="margin-top:1rem;">
        {{ $logs->links() }}
    </div>
@endsection
