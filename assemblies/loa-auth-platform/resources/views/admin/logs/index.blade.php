@extends('layouts.admin')

@section('title', 'Application Logs | LOA Admin')
@section('content')
    @include('admin.partials.breadcrumbs', ['items' => [
        ['label' => 'Logs'],
    ]])
    <div class="page-header">
        <div>
            <h1>Application Logs</h1>
            <p>Showing last {{ number_format($lines) }} of {{ number_format($totalLines) }} lines &middot; {{ $fileSize }}</p>
        </div>
        <div class="page-actions">
            <form method="get" action="{{ route('admin.logs') }}" style="display:flex;gap:.5rem;align-items:center;">
                <label style="font-size:.8125rem;color:#64748b;">Lines:</label>
                <input type="number" name="lines" value="{{ $lines }}" min="10" max="5000" style="width:5rem;height:2.25rem;border:1px solid #cbd5e1;border-radius:.5rem;padding:0 .6rem;">
                <button class="button button-ghost" type="submit">Refresh</button>
            </form>
            @if ($fileExists)
                <a class="button" href="{{ route('admin.logs.download') }}">Download log</a>
            @endif
        </div>
    </div>

    <div class="panel">
        <pre style="margin:0;padding:1.25rem;overflow:auto;max-height:70vh;font-size:.8125rem;line-height:1.6;background:#1e293b;color:#e2e8f0;border-radius:0 0 1rem 1rem;white-space:pre-wrap;word-break:break-all;">{{ $logs }}</pre>
    </div>
@endsection
