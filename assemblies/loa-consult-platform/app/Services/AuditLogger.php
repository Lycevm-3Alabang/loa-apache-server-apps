<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogger
{
    public function __construct(
        private readonly Request $request,
    ) {
    }

    public function record(
        string $action,
        string $source = 'api',
        ?string $entityType = null,
        ?string $entityId = null,
        ?array $details = null,
        ?string $userId = null,
        ?string $userEmail = null,
    ): AuditLog {
        return AuditLog::create([
            'organization_id' => $this->resolveOrganizationId(),
            'user_id' => $userId ?? $this->resolveUserId(),
            'user_email' => $userEmail ?? $this->resolveUserEmail(),
            'action' => $action,
            'source' => $source,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details,
            'ip_address' => $this->request->ip(),
            'user_agent' => substr((string) $this->request->userAgent(), 0, 255) ?: null,
            'created_at' => now(),
        ]);
    }

    public function fromClaims(string $action, array $claims, ?string $entityType = null, ?string $entityId = null, ?array $details = null): AuditLog
    {
        return $this->record(
            $action,
            'api',
            $entityType,
            $entityId,
            $details,
            $claims['sub'] ?? null,
            $claims['email'] ?? null,
        );
    }

    private function resolveOrganizationId(): string
    {
        return config('cert-platform.organization_id', '00000000-0000-0000-0000-000000000001');
    }

    private function resolveUserId(): ?string
    {
        $claims = $this->request->attributes->get('jwt_claims');

        return $claims['sub'] ?? null;
    }

    private function resolveUserEmail(): ?string
    {
        $claims = $this->request->attributes->get('jwt_claims');

        return $claims['email'] ?? null;
    }
}
