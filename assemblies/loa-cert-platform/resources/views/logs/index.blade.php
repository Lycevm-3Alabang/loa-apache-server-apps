@extends('layouts.app')

@section('title', 'Application Logs | Cert Platform')
@section('content')
    <div class="page-header">
        <div>
            <h1>Application Logs</h1>
            <p>Showing last {{ number_format($lines) }} of {{ number_format($totalLines) }} lines &middot; {{ $fileSize }}</p>
        </div>
        <div class="page-actions">
            <form method="get" action="{{ route('logs.index') }}" style="display:flex;gap:.5rem;align-items:center;">
                <label style="font-size:.8125rem;color:#64748b;">Lines:</label>
                <input type="number" name="lines" value="{{ $lines }}" min="10" max="5000" style="width:5rem;height:2.25rem;border:1px solid #cbd5e1;border-radius:.5rem;padding:0 .6rem;">
                <button class="button button-ghost" type="submit">Refresh</button>
            </form>
            @if ($fileExists)
                <a class="button" href="{{ route('logs.download') }}">Download log</a>
            @endif
        </div>
    </div>

    <div class="panel">
        <pre>{{ $logs }}</pre>
    </div>
@endsection
