<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantApiKey;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TenantApiKeyController extends Controller
{
    private AuditLogger $audit;

    public function __construct(AuditLogger $audit)
    {
        $this->audit = $audit;
    }

    public function index(Request $request, string $tenant): JsonResponse
    {
        $tenantModel = Tenant::findOrFail($tenant);

        $keys = TenantApiKey::where('tenant_id', $tenantModel->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (TenantApiKey $key) => [
                'id' => $key->id,
                'name' => $key->name,
                'key_preview' => substr($key->key_hash, 0, 8) . '****',
                'last_used_at' => $key->last_used_at,
                'expires_at' => $key->expires_at,
                'revoked_at' => $key->revoked_at,
                'is_active' => $key->isActive(),
                'created_at' => $key->created_at,
            ]);

        return response()->json(['data' => $keys]);
    }

    public function store(Request $request, string $tenant): JsonResponse
    {
        $tenantModel = Tenant::findOrFail($tenant);

        $activeCount = TenantApiKey::where('tenant_id', $tenantModel->id)
            ->whereNull('revoked_at')
            ->count();

        if ($activeCount >= 3) {
            return response()->json([
                'message' => 'Maximum of 3 active API keys per tenant reached. Revoke an existing key first.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'expires_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pair = TenantApiKey::generateKeyPair();

        $apiKey = TenantApiKey::create([
            'tenant_id' => $tenantModel->id,
            'name' => $request->input('name'),
            'key_hash' => $pair['key_hash'],
            'secret_hash' => $pair['secret_hash'],
            'expires_at' => $request->input('expires_at'),
            'created_by' => $request->user()?->id,
        ]);

        $this->audit->recordSafe(
            'api_key.created',
            'tenant_api_key',
            $apiKey->id,
            ['tenant' => $tenantModel->slug, 'name' => $apiKey->name],
        );

        return response()->json([
            'id' => $apiKey->id,
            'name' => $apiKey->name,
            'key' => $pair['key'],
            'secret' => $pair['secret'],
            'tenant_id' => $tenantModel->id,
            'expires_at' => $apiKey->expires_at,
            'created_at' => $apiKey->created_at,
        ], 201);
    }

    public function destroy(Request $request, string $tenant, string $keyId): JsonResponse
    {
        $tenantModel = Tenant::findOrFail($tenant);

        $apiKey = TenantApiKey::where('tenant_id', $tenantModel->id)
            ->where('id', $keyId)
            ->first();

        if (!$apiKey) {
            return response()->json(['message' => 'API key not found'], 404);
        }

        if ($apiKey->revoked_at) {
            return response()->json(['message' => 'API key is already revoked'], 409);
        }

        $apiKey->update(['revoked_at' => now()]);

        $this->audit->recordSafe(
            'api_key.revoked',
            'tenant_api_key',
            $apiKey->id,
            ['tenant' => $tenantModel->slug, 'name' => $apiKey->name],
        );

        return response()->json([
            'message' => 'API key revoked',
            'id' => $apiKey->id,
            'name' => $apiKey->name,
        ]);
    }
}
