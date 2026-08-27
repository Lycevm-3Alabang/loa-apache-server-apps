<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserGroup;
use App\Models\PasswordSetToken;
use App\Services\TenantService;
use App\Services\AuthorizationService;
use App\Services\IdentityService;
use App\Services\AuditLogger;
use App\Mail\SetPasswordMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class TenantMemberApiController extends Controller
{
    private TenantService $tenants;
    private AuthorizationService $authorization;
    private IdentityService $identity;
    private AuditLogger $audit;

    public function __construct(
        TenantService $tenants,
        AuthorizationService $authorization,
        IdentityService $identity,
        AuditLogger $audit,
    ) {
        $this->tenants = $tenants;
        $this->authorization = $authorization;
        $this->identity = $identity;
        $this->audit = $audit;
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');

        $query = User::whereHas('tenants', fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('name');

        $status = $request->query('status');
        if ($status && in_array($status, ['pending', 'active', 'disabled'])) {
            $query->where('status', $status);
        }

        $cursor = $request->query('cursor');
        if ($cursor) {
            $query->where('id', '>', $cursor);
        }

        $limit = min(max((int) $request->query('limit', 20), 1), 100);

        $users = $query->take($limit + 1)->get();

        $hasMore = $users->count() > $limit;
        $users = $users->take($limit);

        $nextCursor = $hasMore ? $users->last()->id : null;

        return response()->json([
            'data' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'joined_at' => $user->created_at,
            ])->values(),
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->input('email'))->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($this->tenants->isMember($user->id, $tenantId)) {
            return response()->json(['message' => 'User is already a member of this tenant'], 409);
        }

        $this->tenants->addUserToTenant($user->id, $tenantId);

        $this->audit->recordSafe(
            'tenant.member_added',
            'tenant',
            $tenantId,
            ['member_email' => $user->email, 'source' => 'api_key'],
        );

        return response()->json([
            'message' => 'User added to tenant',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'joined_at' => $user->fresh()?->created_at ?? $user->created_at,
            ],
        ], 201);
    }

    public function destroy(Request $request, string $userId): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');

        $user = User::find($userId);

        if (!$user || !$this->tenants->isMember($user->id, $tenantId)) {
            return response()->json(['message' => 'User is not a member of this tenant'], 404);
        }

        $this->tenants->removeUserFromTenant($user->id, $tenantId);

        $this->audit->recordSafe(
            'tenant.member_removed',
            'tenant',
            $tenantId,
            ['member_email' => $user->email, 'source' => 'api_key'],
        );

        return response()->json([
            'message' => 'Membership revoked',
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
            ],
        ]);
    }

    public function invite(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'groups' => 'nullable|array',
            'groups.*' => 'string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $this->identity->register(
            $request->input('email'),
            '',
            $request->input('name'),
        );
        $user->update(['status' => 'pending']);

        $this->tenants->addUserToTenant($user->id, $tenantId);

        $groupNames = $request->input('groups', []);
        $assignedGroups = [];

        foreach ($groupNames as $groupName) {
            $group = UserGroup::where('name', $groupName)
                ->where('tenant_id', $tenantId)
                ->first();

            if ($group) {
                $this->authorization->addToGroup($user->id, $group->id);
                $assignedGroups[] = $groupName;
            }
        }

        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);

        PasswordSetToken::where('user_id', $user->id)->delete();

        PasswordSetToken::create([
            'user_id' => $user->id,
            'token' => $hashedToken,
            'expires_at' => now()->addHours(48),
        ]);

        Mail::to($user->email)->queue(new SetPasswordMail($user, $rawToken));

        $this->audit->recordSafe(
            'user.created',
            'user',
            $user->id,
            ['email' => $user->email, 'name' => $user->name, 'source' => 'api_key'],
        );

        $this->audit->recordSafe(
            'tenant.member_invited',
            'tenant',
            $tenantId,
            ['member_email' => $user->email, 'groups' => $assignedGroups, 'source' => 'api_key'],
        );

        return response()->json([
            'message' => 'Invitation sent',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => 'pending',
                'joined_at' => $user->created_at,
            ],
        ], 201);
    }
}
