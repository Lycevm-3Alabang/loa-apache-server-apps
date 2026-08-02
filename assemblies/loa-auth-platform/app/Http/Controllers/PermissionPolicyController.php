<?php

namespace App\Http\Controllers;

use App\Models\Claim;
use App\Models\GroupClaim;
use App\Models\RoutePolicy;
use App\Models\UserClaimOverride;
use App\Services\PermissionPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionPolicyController extends Controller
{
    public function __construct(
        private readonly PermissionPolicyService $policy,
    ) {
    }

    public function claimsIndex(Request $request): JsonResponse
    {
        $claims = Claim::orderBy('key')->get();

        return response()->json(['claims' => $claims]);
    }

    public function claimsStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $claim = Claim::create($validated);

        return response()->json(['claim' => $claim], 201);
    }

    public function claimsUpdate(Request $request, Claim $claim): JsonResponse
    {
        $validated = $request->validate([
            'description' => 'nullable|string',
        ]);

        $claim->update($validated);

        return response()->json(['claim' => $claim]);
    }

    public function claimsDestroy(Request $request, Claim $claim): JsonResponse
    {
        $claim->delete();

        return response()->json(['message' => 'Claim deleted']);
    }

    public function routePoliciesIndex(Request $request): JsonResponse
    {
        $app = $request->query('app');
        $query = RoutePolicy::query()->orderBy('app')->orderBy('method')->orderBy('path');

        if ($app) {
            $query->where('app', $app);
        }

        return response()->json(['policies' => $query->paginate(50)]);
    }

    public function routePoliciesStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'app' => 'required|string|max:255',
            'method' => 'required|string|max:10',
            'path' => 'required|string',
            'claim_key' => 'required|string',
            'filter' => 'required|in:all,author,scope,none',
        ]);

        $policy = RoutePolicy::create($validated);

        return response()->json(['policy' => $policy], 201);
    }

    public function routePoliciesUpdate(Request $request, RoutePolicy $policy): JsonResponse
    {
        $validated = $request->validate([
            'filter' => 'sometimes|required|in:all,author,scope,none',
        ]);

        $policy->update($validated);

        return response()->json(['policy' => $policy]);
    }

    public function routePoliciesDestroy(Request $request, RoutePolicy $policy): JsonResponse
    {
        $policy->delete();

        return response()->json(['message' => 'Policy deleted']);
    }

    public function groupClaimsIndex(Request $request): JsonResponse
    {
        $groupId = $request->query('group_id');
        $query = GroupClaim::with('claim')->orderBy('group_id');

        if ($groupId) {
            $query->where('group_id', $groupId);
        }

        return response()->json(['group_claims' => $query->paginate(50)]);
    }

    public function groupClaimsStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group_id' => 'required|integer|exists:user_groups,id',
            'claim_key' => 'required|string',
            'scope_type' => 'required|in:none,author,scope',
            'scope_id' => 'nullable|string',
        ]);

        $groupClaim = GroupClaim::create($validated);

        return response()->json(['group_claim' => $groupClaim], 201);
    }

    public function groupClaimsDestroy(Request $request, GroupClaim $groupClaim): JsonResponse
    {
        $groupClaim->delete();

        return response()->json(['message' => 'Group claim removed']);
    }

    public function userOverridesIndex(Request $request): JsonResponse
    {
        $userId = $request->query('user_id');
        $query = UserClaimOverride::with('claim')->orderBy('user_id');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return response()->json(['overrides' => $query->paginate(50)]);
    }

    public function userOverridesStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'claim_key' => 'required|string',
            'granted' => 'required|boolean',
        ]);

        $override = UserClaimOverride::create($validated);

        return response()->json(['override' => $override], 201);
    }

    public function userOverridesDestroy(Request $request, UserClaimOverride $override): JsonResponse
    {
        $override->delete();

        return response()->json(['message' => 'Override removed']);
    }

    public function authorize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'app' => 'required|string',
            'method' => 'required|string',
            'path' => 'required|string',
        ]);

        $tenantId = $request->query('tenant_id');

        $result = $this->policy->authorize(
            $validated['user_id'],
            $tenantId,
            $validated['app'],
            $validated['method'],
            $validated['path']
        );

        return response()->json($result);
    }
}