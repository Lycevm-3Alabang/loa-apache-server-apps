<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only audit browser (admin-audit-log.md §6): newest-first listing with
 * combinable filters and streamed CSV export. Retrieval never mutates
 * evidence; no write paths exist by contract.
 */
class AuditLogController extends Controller
{
    private const CSV_COLUMNS = [
        'created_at', 'actor_email', 'action', 'source',
        'entity_type', 'entity_id', 'details', 'ip_address',
    ];

    public function index(): View
    {
        return view('admin.audit-logs.index', [
            'logs' => $this->filteredQuery()->paginate(50)->withQueryString(),
            'filters' => [
                'action' => $this->stringFilter('action'),
                'actor' => $this->stringFilter('actor'),
                'entity' => $this->stringFilter('entity'),
                'from' => $this->stringFilter('from'),
                'to' => $this->stringFilter('to'),
            ],
        ]);
    }

    public function export(): StreamedResponse
    {
        $filename = 'audit-logs-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, self::CSV_COLUMNS);

            $this->filteredQuery()
                ->lazy(500)
                ->each(function (AuditLog $log) use ($out) {
                    fputcsv($out, [
                        optional($log->created_at)->toIso8601String(),
                        $log->actor_email,
                        $log->action,
                        $log->source,
                        $log->entity_type,
                        $log->entity_id,
                        $log->details ? json_encode($log->details) : null,
                        $log->ip_address,
                    ]);
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function filteredQuery()
    {
        $query = AuditLog::query()->orderByDesc('created_at');

        if ($action = $this->stringFilter('action')) {
            // Prefix match with literal wildcard handling (§8).
            $query->where('action', 'like', str_replace(['%', '\\'], ['\%', '\\\\'], $action).'%');
        }

        if ($actor = $this->stringFilter('actor')) {
            $query->where('actor_email', 'like', '%'.str_replace(
                ['%', '_', '\\'],
                ['\%', '\_', '\\\\'],
                $actor,
            ).'%');
        }

        if ($entity = $this->stringFilter('entity')) {
            [$type, $id] = array_pad(explode(':', $entity, 2), 2, null);
            $query->where('entity_type', '=', $type)
                ->where('entity_id', '=', $id);
        }

        if ($from = $this->stringFilter('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $this->stringFilter('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }

    private function stringFilter(string $key): string
    {
        return trim((string) request()->query($key, ''));
    }
}
