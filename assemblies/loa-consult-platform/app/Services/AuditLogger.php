<?php

namespace App\Services;

use App\Models\AuditLog;

/**
 * Consult audit writer — data-model.md §3.4 shape (L119).
 *
 * No org FK (single tenant), no source/entity/ip columns — those are cert-only
 * and were dropped in this port. Still uncallable until the audit_logs
 * migration lands via an approved slice (Specified — not migrated); callers
 * MUST wrap calls in try/catch (fail-soft) until then.
 */
class AuditLogger
{
    public function record(
        string $action,
        ?array $details = null,
        ?string $userId = null,
        ?string $userEmail = null,
    ): AuditLog {
        // Fail closed with a catchable exception until the audit_logs slice
        // lands (no model/table yet). Callers MUST stay fail-soft: a missing
        // class would otherwise surface as a fatal Error.
        if (!class_exists(AuditLog::class)) {
            throw new \RuntimeException('Audit store unavailable (audit_logs not migrated)');
        }

        return AuditLog::create([
            'user_id' => $userId,
            'user_email' => $userEmail,
            'action' => $action,
            'details' => $details !== null ? json_encode($details) : null,
            'is_active' => true,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    public function fromClaims(string $action, array $claims, ?array $details = null): AuditLog
    {
        return $this->record(
            $action,
            $details,
            $claims['sub'] ?? null,
            $claims['email'] ?? null,
        );
    }
}
