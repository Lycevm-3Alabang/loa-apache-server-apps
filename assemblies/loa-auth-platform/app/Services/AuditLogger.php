<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Audit writer for the auth-platform admin surface
 * (admin-audit-log.md §4), consuming kernels/audit.md v1.0 semantics:
 * append-only records with actor attribution and request context.
 */
class AuditLogger
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    /**
     * Raw writer — throws on storage failure. Prefer recordSafe() at call
     * sites so auditing can never break the primary action (§4).
     */
    public function record(
        string $action,
        ?string $entityType = null,
        ?string $entityId = null,
        ?array $details = null,
        ?string $actorId = null,
        ?string $actorEmail = null,
    ): AuditLog {
        $actor = Auth::guard('web')->user();

        return AuditLog::create([
            'actor_id' => $actorId ?? $actor?->id,
            'actor_email' => $actorEmail ?? $actor?->email,
            'action' => $action,
            'source' => 'web',
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details,
            'ip_address' => $this->request->ip(),
            'user_agent' => substr((string) $this->request->userAgent(), 0, 255) ?: null,
            'created_at' => now(),
        ]);
    }

    /**
     * Fault-isolated wrapper: audit loss is reported but never breaks the
     * primary write path (admin-audit-log.md §4).
     */
    public function recordSafe(
        string $action,
        ?string $entityType = null,
        ?string $entityId = null,
        ?array $details = null,
        ?string $actorId = null,
        ?string $actorEmail = null,
    ): void {
        try {
            $this->record($action, $entityType, $entityId, $details, $actorId, $actorEmail);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
