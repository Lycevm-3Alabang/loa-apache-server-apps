<?php

namespace App\Http\Controllers;

use App\Mail\SetPasswordMail;
use App\Models\PasswordSetToken;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\ActivationService;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Services\IdentityService;
use App\Services\PasswordResetNotificationService;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WebAdminController extends Controller
{
    public function __construct(
        private readonly IdentityService $identity,
        private readonly TenantService $tenants,
        private readonly AuthorizationService $authorization,
        private readonly PasswordResetNotificationService $passwordResetNotifications,
        private readonly ActivationService $activation,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * Emits group-membership audit rows (admin-audit-log.md §5): every
     * membership change gets group.member_*, and the platform-admin group
     * additionally gets the dedicated admin_group.* evidence keys.
     */
    private function auditGroupMembership(string $direction, UserGroup $group, string $userId): void
    {
        $memberEmail = User::find($userId)?->email;

        $this->audit->recordSafe(
            "group.member_{$direction}",
            'user',
            $userId,
            ['group' => $group->name, 'member_email' => $memberEmail],
        );

        if ($group->name === (string) config('auth-web.admin_group')) {
            $evidenceKey = $direction === 'added' ? 'admin_group.granted' : 'admin_group.revoked';

            $this->audit->recordSafe(
                $evidenceKey,
                'user',
                $userId,
                ['group' => $group->name, 'member_email' => $memberEmail],
            );
        }
    }

    // ─── v1: User management ───────────────────────────────────────

    public function index(Request $request): View
    {
        $query = User::query()->with('userGroups')->orderByDesc('created_at');

        $q = trim((string) $request->query('q', ''));

        if ($q !== '') {
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%"));
        }

        $status = (string) $request->query('status', 'all');

        if (in_array($status, ['active', 'disabled', 'locked', 'pending'], true)) {
            $query->where('status', $status);
        }

        return view('admin.users.index', [
            'users' => $query->paginate(25)->withQueryString(),
            'q' => $q,
            'status' => $status,
            'currentUserId' => Auth::guard('web')->id(),
        ]);
    }

    public function updateStatus(Request $request, string $id): RedirectResponse
    {
        $admin = Auth::guard('web')->user();

        if (!$this->authorization->hasPermission($admin->id, 'users.manage')) {
            return back()->with('error', 'You are not allowed to manage users.');
        }

        if ($admin->id === $id) {
            return back()->with('error', 'You cannot disable your own account.');
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,disabled',
        ]);

        if ($validator->fails()) {
            return back()->with('error', 'Invalid status.');
        }

        $user = User::find($id);

        if (!$user) {
            return back()->with('error', 'User not found.');
        }

        if ($request->input('status') === 'disabled'
            && $user->inGroup((string) config('auth-web.admin_group'))) {
            return back()->with('error', 'Platform administrators cannot be deactivated.');
        }

        try {
            $this->identity->setUserStatus($id, $request->input('status'));
        } catch (\Throwable) {
            return back()->with('error', 'Unable to update user status.');
        }

        $this->audit->recordSafe(
            'user.status_changed',
            'user',
            $user->id,
            ['from' => $user->status, 'to' => $request->input('status')],
        );

        return back()->with('status', 'User status updated.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    // ─── v3: Admin user creation ──────────────────────────────────

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $admin = Auth::guard('web')->user();

        if (!$this->authorization->hasPermission($admin->id, 'users.manage')) {
            return back()->with('error', 'You are not allowed to manage users.');
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255|unique:users,email',
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            // Create user (status will be set to 'pending')
            $user = $this->identity->register($request->input('email'), '', $request->input('name'));
            
            // Override status to pending
            $user->update(['status' => 'pending']);
            
            // Generate activation token
            $rawToken = $this->activation->createActivation($user);
            
            // Send activation email (using the existing mail sending mechanism)
            Mail::send('emails.activate-account', ['user' => $user, 'token' => $rawToken], function ($m) use ($user) {
                $m->to($user->email)->subject('Activate your LOA Platform account');
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('admin.users')->with('status', 'User created. Activation email sent.');
    }

    // ─── v2: Tenant management ─────────────────────────────────────

    public function resendActivation(Request $request, string $id): RedirectResponse
    {
        // Check if admin has permission to manage users
        $admin = Auth::guard('web')->user();
        if (!$this->authorization->hasPermission($admin->id, 'users.manage')) {
            return back()->with('error', 'You are not allowed to manage users.');
        }
        
        // Look up user by ID
        $user = User::find($id);
        if (!$user) {
            return back()->with('error', 'User not found.');
        }
        
        // Check that the user is pending activation
        if ($user->status !== 'pending') {
            return back()->with('error', 'This user is not pending activation.');
        }
        
        try {
            // Generate new activation token (replaces old one)
            $rawToken = $this->activation->resendActivation($user);
            
            // Send activation email
            Mail::send('emails.activate-account', ['user' => $user, 'token' => $rawToken], function ($m) use ($user) {
                $m->to($user->email)->subject('Activate your LOA Platform account');
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to resend activation email: ' . $e->getMessage());
        }
        
        return back()->with('status', 'Activation email resent successfully.');
    }

    public function deleteUser(Request $request, string $id): RedirectResponse
    {
        $admin = Auth::guard('web')->user();

        if (!$this->authorization->hasPermission($admin->id, 'users.manage')) {
            return back()->with('error', 'You are not allowed to manage users.');
        }

        if ($admin->id === $id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user = User::find($id);

        if (!$user) {
            return back()->with('error', 'User not found.');
        }

        if ($user->inGroup((string) config('auth-web.admin_group'))) {
            return back()->with('error', 'Platform administrators cannot be deleted.');
        }

        $email = $user->email;

        try {
            $user->delete();
        } catch (\Throwable) {
            return back()->with('error', 'Unable to delete user.');
        }

        $this->audit->recordSafe(
            'user.deleted',
            'user',
            $user->id,
            ['email' => $email],
        );

        return redirect()->route('admin.users')->with('status', "User {$email} has been permanently deleted.");
    }

    // ─── v2: Tenant management ─────────────────────────────────────

    public function tenantsIndex(): View
    {
        $tenants = Tenant::withCount('users')
            ->orderBy('name')
            ->paginate(25);

        return view('admin.tenants.index', [
            'tenants' => $tenants,
        ]);
    }

    public function tenantsCreate(): View
    {
        return view('admin.tenants.create');
    }

    public function tenantsStore(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'app_url' => 'nullable|url|max:255',
            'dev_app_url' => 'nullable|url|max:255',
            'redirect_origins' => 'nullable|string',
            'dev_redirect_origins' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $origins = array_values(array_filter(array_map(
            fn (string $url): string => trim($url),
            explode(',', $request->input('redirect_origins', '')),
        )));

        $devOrigins = array_values(array_filter(array_map(
            fn (string $url): string => trim($url),
            explode(',', $request->input('dev_redirect_origins', '')),
        )));

        try {
            $this->tenants->createTenant([
                'slug' => $request->input('slug'),
                'name' => $request->input('name'),
                'app_url' => $request->input('app_url'),
                'dev_app_url' => $request->input('dev_app_url'),
                'redirect_origins' => $origins,
                'dev_redirect_origins' => $devOrigins,
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('admin.tenants')->with('status', 'Tenant created.');
    }

    public function tenantsEdit(Tenant $tenant): View
    {
        return view('admin.tenants.edit', [
            'tenant' => $tenant,
        ]);
    }

    public function tenantsUpdate(Request $request, Tenant $tenant): RedirectResponse
    {
        if ($tenant->isPlatform()) {
            abort(422, 'The auth tenant cannot be edited.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'app_url' => 'nullable|url|max:255',
            'dev_app_url' => 'nullable|url|max:255',
            'redirect_origins' => 'nullable|string',
            'dev_redirect_origins' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $origins = array_values(array_filter(array_map(
            fn (string $url): string => trim($url),
            explode(',', $request->input('redirect_origins', '')),
        )));

        $devOrigins = array_values(array_filter(array_map(
            fn (string $url): string => trim($url),
            explode(',', $request->input('dev_redirect_origins', '')),
        )));

        try {
            $this->tenants->updateTenant($tenant->id, [
                'name' => $request->input('name'),
                'app_url' => $request->input('app_url'),
                'dev_app_url' => $request->input('dev_app_url'),
                'redirect_origins' => $origins,
                'dev_redirect_origins' => $devOrigins,
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('admin.tenants.show', $tenant)->with('status', 'Tenant updated successfully.');
    }

    public function tenantsStatus(Request $request, Tenant $tenant): RedirectResponse
    {
        if ($tenant->isPlatform()) {
            abort(422, 'The auth tenant cannot be suspended or activated.');
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,suspended',
        ]);

        if ($validator->fails()) {
            return back()->with('error', 'Invalid status.');
        }

        $targetStatus = $request->input('status');

        try {
            if ($targetStatus === 'suspended') {
                $this->tenants->suspendTenant($tenant->id);
            } else {
                $this->tenants->activateTenant($tenant->id);
            }
        } catch (\Throwable) {
            return back()->with('error', 'Unable to update tenant status.');
        }

        return back()->with('status', "Tenant {$targetStatus}.");
    }

    // ─── v2: Tenant groups + permissions ───────────────────────────

    public function tenantsGroups(Tenant $tenant): View
    {
        $groups = $tenant->userGroups()
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return view('admin.tenants.groups', [
            'tenant' => $tenant,
            'groups' => $groups,
        ]);
    }

    public function tenantsGroupShow(Tenant $tenant, UserGroup $group): View
    {
        if ($group->tenant_id !== $tenant->id) {
            abort(404);
        }

        $group->load(['permissions']);

        return view('admin.tenants.group-show', [
            'tenant' => $tenant,
            'group' => $group,
            'membersCount' => $group->users()->count(),
            'allPermissions' => Permission::orderBy('key')->get(),
        ]);
    }

    public function tenantsGroupsPermissionsStore(Request $request, Tenant $tenant, UserGroup $group): RedirectResponse
    {
        if ($group->tenant_id !== $tenant->id) {
            abort(404);
        }

        $validator = Validator::make($request->all(), [
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        if ($validator->fails()) {
            return back()->with('error', 'Invalid permission data.');
        }

        $allowedIds = array_map('intval', $request->input('permissions', []));

        DB::transaction(function () use ($group, $allowedIds) {
            $allPermissions = Permission::pluck('id');
            $syncData = [];

            foreach ($allPermissions as $permId) {
                $syncData[$permId] = [
                    'granted' => in_array($permId, $allowedIds, true),
                    'tenant_id' => null,
                ];
            }

            $group->permissions()->sync($syncData);
        });

        return back()->with('status', 'Permissions updated.');
    }

    public function tenantsGroupEndpoints(Tenant $tenant, UserGroup $group): View
    {
        if ($group->tenant_id !== $tenant->id) {
            abort(404);
        }

        $group->load('permissions');

        $endpoints = \App\Models\TenantAppEndpoint::where(function ($q) use ($tenant) {
            $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id);
        })->orderBy('method')->orderBy('path')->get();

        $grants = \App\Models\TenantEndpointGrant::where('group_id', $group->id)
            ->where(function ($q) use ($tenant) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id);
            })->get();

        $grantMap = [];
        foreach ($grants as $grant) {
            $key = $grant->method . '|' . $grant->path;
            $grantMap[$key] = $grant->level;
        }

        return view('admin.tenants.group-endpoints', [
            'tenant' => $tenant,
            'group' => $group,
            'endpoints' => $endpoints,
            'grantMap' => $grantMap,
        ]);
    }

    public function tenantsGroupMembers(Tenant $tenant, UserGroup $group): View
    {
        if ($group->tenant_id !== $tenant->id) {
            abort(404);
        }

        $members = $group->users()
            ->withPivot('created_at')
            ->with('userGroups')
            ->orderBy('email')
            ->get();

        $tenantGroups = $tenant->userGroups()->orderBy('name')->get();

        return view('admin.tenants.group-members', [
            'tenant' => $tenant,
            'group' => $group,
            'members' => $members,
            'tenantGroups' => $tenantGroups,
        ]);
    }

    public function searchMembers(Request $request, Tenant $tenant): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $like = '%' . addcslashes(strtolower($term), '%_\\') . '%';
        $exactEmail = strtolower($term);
        $escapeChar = '\\';

        $users = User::query()
            ->where(function ($q) use ($like, $escapeChar) {
                $q->whereRaw('LOWER(name) LIKE ? ESCAPE ?', [$like, $escapeChar])
                    ->orWhereRaw('LOWER(email) LIKE ? ESCAPE ?', [$like, $escapeChar]);
            })
            ->where('status', '!=', 'disabled')
            ->whereNotExists(function ($sub) use ($tenant) {
                $sub->selectRaw(1)
                    ->from('user_tenants')
                    ->whereColumn('user_tenants.user_id', 'users.id')
                    ->where('user_tenants.tenant_id', $tenant->id);
            })
            ->orderByRaw('CASE WHEN LOWER(email) = ? THEN 0 ELSE 1 END', [$exactEmail])
            ->orderBy('email')
            ->limit(20)
            ->get(['id', 'name', 'email', 'status']);

        return response()->json(['data' => $users]);
    }

    // ─── §12: Tenant group membership management ────────────────────

    public function tenantGroupMemberSearch(Request $request, Tenant $tenant, UserGroup $group): JsonResponse
    {
        if ($group->tenant_id !== $tenant->id) {
            abort(404);
        }

        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['data' => [], 'tier' => 'none']);
        }

        $like = '%' . addcslashes(strtolower($term), '%_\\') . '%';
        $exactEmail = strtolower($term);
        $escapeChar = '\\';

        $nameEmail = fn ($q) => $q->whereRaw('LOWER(name) LIKE ? ESCAPE ?', [$like, $escapeChar])
            ->orWhereRaw('LOWER(email) LIKE ? ESCAPE ?', [$like, $escapeChar]);

        // Primary tier: tenant members NOT in this group
        $primary = $tenant->users()
            ->where('status', '!=', 'disabled')
            ->where(fn ($q) => $nameEmail($q))
            ->whereNotIn('users.id', fn ($q) => $q->select('user_id')->from('user_user_group')->where('user_group_id', $group->id))
            ->orderByRaw('CASE WHEN LOWER(email) = ? THEN 0 ELSE 1 END', [$exactEmail])
            ->orderBy('email')
            ->limit(20)
            ->get(['users.id', 'name', 'email', 'status']);

        if ($primary->isNotEmpty()) {
            return response()->json(['data' => $primary, 'tier' => 'primary']);
        }

        // Secondary tier: non-tenant users (need tenant pivot on add)
        $secondary = User::query()
            ->where('status', '!=', 'disabled')
            ->where(fn ($q) => $nameEmail($q))
            ->whereNotIn('id', fn ($q) => $q->select('user_id')->from('user_tenants')->where('tenant_id', $tenant->id))
            ->orderByRaw('CASE WHEN LOWER(email) = ? THEN 0 ELSE 1 END', [$exactEmail])
            ->orderBy('email')
            ->limit(20)
            ->get(['id', 'name', 'email', 'status']);

        return response()->json(['data' => $secondary, 'tier' => 'secondary']);
    }

    public function tenantGroupMembersStore(Request $request, Tenant $tenant, UserGroup $group): RedirectResponse
    {
        if ($group->tenant_id !== $tenant->id) {
            abort(404);
        }

        $userIds = $request->input('user_ids', []);
        $tiers = $request->input('tiers', []);
        $singleId = $request->input('user_id');
        $singleTier = $request->input('tier');

        if (!empty($singleId) && empty($userIds)) {
            $userIds = [$singleId];
            $tiers = [$singleTier ?? 'primary'];
        }

        if (empty($userIds)) {
            return back()->with('error', 'No users selected.');
        }

        $added = 0;
        foreach ($userIds as $index => $userId) {
            $tier = $tiers[$index] ?? 'primary';

            if ($group->users()->where('users.id', $userId)->exists()) {
                if (count($userIds) === 1) {
                    return back()->with('error', 'User is already a member of this group.');
                }
                continue;
            }

            try {
                $this->authorization->addToGroup($userId, $group->id);

                $this->auditGroupMembership('added', $group, $userId);
                $added++;
            } catch (\Throwable) {
                // skip individual failures
            }
        }

        return back()->with('status', $added . ' member(s) added to group.');
    }

    public function tenantGroupMemberRemoveConfirm(Tenant $tenant, UserGroup $group, string $userId): View
    {
        if ($group->tenant_id !== $tenant->id) {
            abort(404);
        }

        $user = User::findOrFail($userId);

        if (!$group->users()->where('users.id', $userId)->exists()) {
            abort(404);
        }

        $isLastAdmin = $group->name === (string) config('auth-web.admin_group')
            && $group->users()->where('users.id', '!=', $userId)->count() === 0
            && $userId === Auth::id();

        return view('admin.tenants.member-remove-confirm', [
            'tenant' => $tenant,
            'group' => $group,
            'user' => $user,
            'isLastAdmin' => $isLastAdmin,
            'otherGroups' => $user->userGroups()
                ->where('tenant_id', $tenant->id)
                ->where('id', '!=', $group->id)
                ->get(),
        ]);
    }

    public function tenantGroupMemberRemove(Request $request, Tenant $tenant, UserGroup $group, string $userId): RedirectResponse
    {
        if ($group->tenant_id !== $tenant->id) {
            abort(404);
        }

        $user = User::findOrFail($userId);

        if (!$group->users()->where('users.id', $userId)->exists()) {
            return back()->with('error', 'User is not in this group.');
        }

        // M8: prevent self-revocation of platform admin group
        if ($group->name === (string) config('auth-web.admin_group') && $userId === Auth::id()) {
            return back()->with('error', 'You cannot revoke your own platform admin membership.');
        }

        $this->authorization->removeFromGroup($userId, $group->id);

        $this->auditGroupMembership('removed', $group, $userId);

        return redirect()->route('admin.tenants.group.members', ['tenant' => $tenant->id, 'group' => $group->id])
            ->with('status', 'Member removed from group.');
    }

    public function tenantGroupPlatformPermissions(Request $request, Tenant $tenant, UserGroup $group): RedirectResponse
    {
        if ($group->tenant_id !== $tenant->id) {
            abort(404);
        }

        $validator = Validator::make($request->all(), [
            'permissions' => 'required|array',
            'permissions.*.id' => 'required|exists:permissions,id',
            'permissions.*.granted' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        foreach ($request->input('permissions') as $item) {
            $this->authorization->grantGroupPermission($group->id, Permission::find($item['id'])->key);
            if (!$item['granted']) {
                $this->authorization->revokeGroupPermission($group->id, Permission::find($item['id'])->key);
            }
        }

        return back()->with('status', 'Platform permissions updated.');
    }

    public function tenantsShow(Tenant $tenant): View
    {
        $tenant->loadCount('users');

        $members = $tenant->users()
            ->with('userGroups')
            ->orderBy('email')
            ->paginate(25);

        $pendingMembers = $tenant->users()
            ->with('userGroups')
            ->where('users.status', 'pending')
            ->orderBy('users.created_at', 'desc')
            ->get();

        $groups = $tenant->userGroups()->orderBy('name')->get();

        return view('admin.tenants.show', [
            'tenant' => $tenant,
            'members' => $members,
            'pendingMembers' => $pendingMembers,
            'groups' => $groups,
        ]);
    }

    public function tenantsGroupsStore(Request $request, Tenant $tenant): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'priority' => 'required|integer|min:1|max:10000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $existing = UserGroup::where('tenant_id', $tenant->id)
            ->where('name', $request->input('name'))
            ->exists();

        if ($existing) {
            return back()->with('error', 'A group with that name already exists in this tenant.');
        }

        UserGroup::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'priority' => $request->input('priority', 10),
            'tenant_id' => $tenant->id,
        ]);

        return back()->with('status', 'Group created.');
    }

    // ─── v2: Tenant members ────────────────────────────────────────

    public function tenantsMembersStore(Request $request, Tenant $tenant): RedirectResponse
    {
        $action = $request->input('action');

        if ($action === 'remove') {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
            ]);

            if ($validator->fails()) {
                return back()->withErrors($validator);
            }

            $userId = $request->input('user_id');
            $memberEmail = User::find($userId)?->email;

            try {
                $this->tenants->removeUserFromTenant($userId, $tenant->id);

                $this->audit->recordSafe(
                    'tenant.member_removed',
                    'tenant',
                    $tenant->id,
                    ['tenant' => $tenant->slug, 'member_email' => $memberEmail],
                );

                return back()->with('status', 'User removed from tenant.');
            } catch (\Throwable) {
                return back()->with('error', 'Unable to update membership.');
            }
        }

        $userIds = $request->input('user_ids', []);
        $singleId = $request->input('user_id');
        if (!empty($singleId) && empty($userIds)) {
            $userIds = [$singleId];
        }

        if (empty($userIds)) {
            return back()->with('error', 'No users selected.');
        }

        $added = 0;
        foreach ($userIds as $userId) {
            try {
                $this->tenants->addUserToTenant($userId, $tenant->id);
                $memberEmail = User::find($userId)?->email;

                $this->audit->recordSafe(
                    'tenant.member_added',
                    'tenant',
                    $tenant->id,
                    ['tenant' => $tenant->slug, 'member_email' => $memberEmail],
                );

                $added++;
            } catch (\Throwable) {
                // skip individual failures
            }
        }

        return back()->with('status', $added . ' user(s) added to tenant.');
    }

    public function tenantsCreateUser(Request $request, Tenant $tenant): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'group_id' => 'required|exists:user_groups,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        if (!$tenant->userGroups()->where('user_groups.id', $request->input('group_id'))->exists()) {
            return back()->withErrors(['group_id' => 'Selected group does not belong to this tenant.'])->withInput();
        }

        try {
            $user = $this->identity->register(
                $request->input('email'),
                '',
                $request->input('name'),
            );
            $user->update(['status' => 'pending']);

            $user->tenants()->syncWithoutDetaching([$tenant->id]);

            $this->authorization->addToGroup($user->id, $request->input('group_id'));

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
                ['email' => $user->email, 'name' => $user->name, 'tenant' => $tenant->slug],
            );

            $this->audit->recordSafe(
                'tenant.member_added',
                'tenant',
                $tenant->id,
                ['tenant' => $tenant->slug, 'member_email' => $user->email],
            );

            return back()->with('status', 'User created and set-password email sent.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Unable to create user: ' . $e->getMessage())->withInput();
        }
    }

    // ─── v4: Group & permission management ──────────────────────────

    public function groupsIndex(): View
    {
        $groups = UserGroup::withCount('users')
            ->orderBy('name')
            ->get();

        return view('admin.groups.index', ['groups' => $groups]);
    }

    public function groupsCreate(): View
    {
        return view('admin.groups.create');
    }

    public function groupsStore(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'priority' => 'required|integer|min:1|max:10000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $existing = UserGroup::whereNull('tenant_id')
            ->where('name', $request->input('name'))
            ->exists();

        if ($existing) {
            return back()->with('error', 'A group with that name already exists.')->withInput();
        }

        UserGroup::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'priority' => $request->input('priority', 10),
            'tenant_id' => null,
        ]);

        return redirect()->route('admin.groups')->with('status', 'Group created.');
    }

    public function groupsShow(UserGroup $group): View
    {
        // §12 M6: Platform group page — reject tenant-scoped groups
        if ($group->tenant_id !== null) {
            abort(404);
        }

        $group->load(['users', 'permissions']);

        $allPermissions = Permission::orderBy('key')->get();

        return view('admin.groups.show', [
            'group' => $group,
            'allPermissions' => $allPermissions,
        ]);
    }

    public function groupsPermissions(Request $request, UserGroup $group): RedirectResponse
    {
        // §12 M6: Platform group permissions — reject tenant-scoped groups
        if ($group->tenant_id !== null) {
            abort(404);
        }

        $validator = Validator::make($request->all(), [
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        if ($validator->fails()) {
            return back()->with('error', 'Invalid permission data.');
        }

        $allowedIds = array_map('intval', $request->input('permissions', []));

        DB::transaction(function () use ($group, $allowedIds) {
            $allPermissions = Permission::pluck('id');
            $syncData = [];

            foreach ($allPermissions as $permId) {
                $syncData[$permId] = [
                    'granted' => in_array($permId, $allowedIds, true),
                    'tenant_id' => null,
                ];
            }

            $group->permissions()->sync($syncData);
        });

        return back()->with('status', 'Permissions updated.');
    }

    public function platformGroupMemberSearch(Request $request, UserGroup $group): JsonResponse
    {
        if ($group->tenant_id !== null) {
            abort(404);
        }

        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $like = '%' . addcslashes(strtolower($term), '%_\\') . '%';
        $escapeChar = '\\';

        $users = User::query()
            ->where(function ($q) use ($like, $escapeChar) {
                $q->whereRaw('LOWER(name) LIKE ? ESCAPE ?', [$like, $escapeChar])
                    ->orWhereRaw('LOWER(email) LIKE ? ESCAPE ?', [$like, $escapeChar]);
            })
            ->where('status', '!=', 'disabled')
            ->whereNotIn('id', function ($sub) use ($group) {
                $sub->select('user_id')->from('user_user_group')
                    ->where('user_group_id', $group->id);
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email', 'status']);

        return response()->json(['data' => $users]);
    }

    public function groupsMembersStore(Request $request, UserGroup $group): RedirectResponse
    {
        // §12 M6: Platform group member management — reject tenant-scoped groups
        if ($group->tenant_id !== null) {
            abort(404);
        }

        $userIds = $request->input('user_ids', []);
        $singleId = $request->input('user_id');

        if (!empty($singleId) && empty($userIds)) {
            $userIds = [$singleId];
        }

        if (empty($userIds)) {
            return back()->with('error', 'No users selected.');
        }

        $added = 0;
        foreach ($userIds as $userId) {
            if ($group->users()->where('users.id', $userId)->exists()) {
                continue;
            }

            $this->authorization->addToGroup($userId, $group->id);
            $this->auditGroupMembership('added', $group, $userId);
            $added++;
        }

        return back()->with('status', $added . ' user(s) added to group.');
    }

    public function groupsMembersRemove(UserGroup $group, string $userId): RedirectResponse
    {
        // §12 M6: Platform group member management — reject tenant-scoped groups
        if ($group->tenant_id !== null) {
            abort(404);
        }

        $this->authorization->removeFromGroup($userId, $group->id);

        $this->auditGroupMembership('removed', $group, $userId);

        return back()->with('status', 'User removed from group.');
    }

    // ─── v4: User detail ───────────────────────────────────────────

    public function showUser(string $id): View
    {
        $user = User::findOrFail($id);

        $groups = $user->userGroups()->orderBy('name')->get();

        $effectivePermissions = $this->authorization->getPermissions($user->id);

        $overrides = $user->userPermissions()->get();

        $allGroups = UserGroup::orderBy('name')->get();

        $allPermissions = Permission::orderBy('key')->get();

        return view('admin.users.show', [
            'user' => $user,
            'groups' => $groups,
            'effectivePermissions' => $effectivePermissions,
            'overrides' => $overrides,
            'allGroups' => $allGroups,
            'allPermissions' => $allPermissions,
        ]);
    }

    public function storeUserPermission(Request $request, string $id): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'permission_key' => 'required|string|exists:permissions,key',
            'granted' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $permission = Permission::where('key', $request->input('permission_key'))->first();

        DB::table('user_permission')->updateOrInsert(
            [
                'user_id' => $id,
                'permission_id' => $permission->id,
                'tenant_id' => null,
            ],
            [
                'granted' => $request->boolean('granted'),
            ],
        );

        return back()->with('status', 'Permission override saved.');
    }

    public function removeUserPermission(string $id, string $key): RedirectResponse
    {
        $permission = Permission::where('key', $key)->first();

        if ($permission) {
            DB::table('user_permission')
                ->where('user_id', $id)
                ->where('permission_id', $permission->id)
                ->delete();
        }

        return back()->with('status', 'Permission override removed.');
    }

    // ─── v5: Tenant endpoint catalog ────────────────────────────────

    public function tenantsEndpoints(string $tenant): View
    {
        $tenant = Tenant::findOrFail($tenant);

        $endpoints = \App\Models\TenantAppEndpoint::where(function ($q) use ($tenant) {
            $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id);
        })->orderBy('method')->orderBy('path')->get();

        return view('admin.tenants.endpoints', [
            'tenant' => $tenant,
            'endpoints' => $endpoints,
        ]);
    }

    public function tenantsEndpointsStore(Request $request, string $tenant): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'method' => 'required|string|in:GET,POST,PUT,PATCH,DELETE,*',
            'path' => 'required|string|max:512',
            'label' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'required_level' => 'required|in:read,write,admin',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $tenant = Tenant::findOrFail($tenant);
        $method = strtoupper($request->input('method'));
        $path = \App\Models\TenantAppEndpoint::normalizePath($request->input('path'));

        $existing = \App\Models\TenantAppEndpoint::where('tenant_id', $tenant->id)
            ->where('method', $method)
            ->where('path', $path)
            ->exists();

        if ($existing) {
            return back()->with('error', 'Endpoint already exists for this tenant.')->withInput();
        }

        \App\Models\TenantAppEndpoint::create([
            'tenant_id' => $tenant->id,
            'method' => $method,
            'path' => $path,
            'label' => $request->input('label'),
            'description' => $request->input('description'),
            'required_level' => $request->input('required_level'),
        ]);

        return back()->with('status', 'Endpoint added to catalog.');
    }

    public function tenantsEndpointsDestroy(Request $request, string $tenant): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'method' => 'required|string',
            'path' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $tenant = Tenant::findOrFail($tenant);
        $method = strtoupper($request->input('method'));
        $path = \App\Models\TenantAppEndpoint::normalizePath($request->input('path'));

        $hasGrants = \App\Models\TenantEndpointGrant::where('method', $method)
            ->where('path', $path)
            ->where('tenant_id', $tenant->id)
            ->exists();

        $hasOverrides = \App\Models\TenantEndpointOverride::where('method', $method)
            ->where('path', $path)
            ->where('tenant_id', $tenant->id)
            ->exists();

        if ($hasGrants || $hasOverrides) {
            $force = $request->boolean('force');
            if (!$force) {
                return back()->with('error', 'Endpoint has existing grants or overrides. Use force=true to delete.');
            }
        }

        \App\Models\TenantAppEndpoint::where('tenant_id', $tenant->id)
            ->where('method', $method)
            ->where('path', $path)
            ->delete();

        return back()->with('status', 'Endpoint removed from catalog.');
    }

    // ─── Endpoint catalog export/import ──────────────────────

    public function tenantsEndpointsExport(string $tenant): StreamedResponse
    {
        $tenant = Tenant::findOrFail($tenant);

        $endpoints = \App\Models\TenantAppEndpoint::where(function ($q) use ($tenant) {
            $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id);
        })->orderBy('method')->orderBy('path')->get();

        $payload = [
            'version' => '1.0',
            'exported_at' => now()->toIso8601String(),
            'tenant_slug' => $tenant->slug,
            'endpoints' => $endpoints->map(fn ($ep) => [
                'method' => $ep->method,
                'path' => $ep->path,
                'label' => $ep->label,
                'description' => $ep->description,
                'required_level' => $ep->required_level,
            ])->toArray(),
        ];

        $filename = 'endpoint-catalog-' . $tenant->slug . '-' . now()->format('Y-m-d') . '.json';

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, $filename, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function tenantsEndpointsImportForm(string $tenant): RedirectResponse
    {
        return redirect()->route('admin.tenants.endpoints.manage', $tenant);
    }

    public function tenantsEndpointsImport(Request $request, string $tenant): JsonResponse
    {
        $tenantModel = Tenant::findOrFail($tenant);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            if (!$file->isValid() || $file->getMimeType() !== 'application/json') {
                return response()->json(['status' => 'error', 'message' => 'Invalid JSON file'], 422);
            }
            $content = file_get_contents($file->getRealPath());
        } else {
            $content = $request->input('payload') ?? $request->getContent();
        }

        $data = json_decode($content, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'status' => 'error',
                'message' => 'Malformed JSON: ' . json_last_error_msg(),
            ], 422);
        }

        $validator = Validator::make($data, [
            'version' => 'required|string|in:1.0',
            'endpoints' => 'required|array',
            'endpoints.*.method' => 'required|string|in:GET,POST,PUT,PATCH,DELETE,*',
            'endpoints.*.path' => 'required|string|max:512',
            'endpoints.*.label' => 'nullable|string|max:255',
            'endpoints.*.description' => 'nullable|string',
            'endpoints.*.required_level' => 'required|in:read,write,admin',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $isDryRun = $request->boolean('dry_run') || !$request->boolean('confirm');
        $endpointsInput = $data['endpoints'];

        $preview = [
            'endpoints' => ['create' => [], 'update' => [], 'skip' => [], 'errors' => []],
        ];

        foreach ($endpointsInput as $ep) {
            $path = \App\Models\TenantAppEndpoint::normalizePath($ep['path']);
            $method = strtoupper($ep['method']);

            $existing = \App\Models\TenantAppEndpoint::where('tenant_id', $tenantModel->id)
                ->where('method', $method)
                ->where('path', $path)
                ->first();

            if ($existing) {
                $preview['endpoints']['update'][] = $method . ' ' . $path;
            } else {
                $preview['endpoints']['create'][] = $method . ' ' . $path;
            }
        }

        if ($isDryRun) {
            return response()->json(['status' => 'preview'] + $preview);
        }

        $result = DB::transaction(function () use ($tenantModel, $endpointsInput, &$preview) {
            $created = 0;
            $updated = 0;

            foreach ($endpointsInput as $ep) {
                $path = \App\Models\TenantAppEndpoint::normalizePath($ep['path']);
                $method = strtoupper($ep['method']);

                $existing = \App\Models\TenantAppEndpoint::where('tenant_id', $tenantModel->id)
                    ->where('method', $method)
                    ->where('path', $path)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'label' => $ep['label'] ?? $existing->label,
                        'description' => $ep['description'] ?? $existing->description,
                        'required_level' => $ep['required_level'],
                    ]);
                    $updated++;
                } else {
                    \App\Models\TenantAppEndpoint::create([
                        'tenant_id' => $tenantModel->id,
                        'method' => $method,
                        'path' => $path,
                        'label' => $ep['label'] ?? null,
                        'description' => $ep['description'] ?? null,
                        'required_level' => $ep['required_level'],
                    ]);
                    $created++;
                }
            }

            return ['created' => $created, 'updated' => $updated];
        });

        return response()->json(['status' => 'applied'] + $result);
    }

    // ─── v5: Group endpoint grants ──────────────────────────────────

    public function tenantsGroupsEndpoints(string $tenant, string $group): View
    {
        $tenant = Tenant::findOrFail($tenant);
        $group = UserGroup::findOrFail($group);

        $endpoints = \App\Models\TenantAppEndpoint::where(function ($q) use ($tenant) {
            $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id);
        })->orderBy('method')->orderBy('path')->get();

        $grants = \App\Models\TenantEndpointGrant::where('group_id', $group->id)
            ->where(function ($q) use ($tenant) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id);
            })->get();

        $grantMap = [];
        foreach ($grants as $grant) {
            $key = $grant->method . '|' . $grant->path;
            $grantMap[$key] = $grant->level;
        }

        return view('admin.tenants.group-endpoints', [
            'tenant' => $tenant,
            'group' => $group,
            'endpoints' => $endpoints,
            'grantMap' => $grantMap,
        ]);
    }

    public function tenantsGroupsEndpointsStore(Request $request, string $tenant, string $group): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'grants' => 'nullable|array',
            'grants.*.method' => 'required|string',
            'grants.*.path' => 'required|string',
            'grants.*.level' => 'required|in:read,write,admin,deny',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $tenant = Tenant::findOrFail($tenant);
        $group = UserGroup::findOrFail($group);

        $grants = $request->input('grants', []);

        DB::transaction(function () use ($group, $tenant, $grants) {
            \App\Models\TenantEndpointGrant::where('group_id', $group->id)
                ->where('tenant_id', $tenant->id)
                ->delete();

            foreach ($grants as $grant) {
                $level = $grant['level'];
                if ($level === 'deny') {
                    continue;
                }

                $method = strtoupper($grant['method']);
                $path = \App\Models\TenantAppEndpoint::normalizePath($grant['path']);

                \App\Models\TenantEndpointGrant::create([
                    'group_id' => $group->id,
                    'tenant_id' => $tenant->id,
                    'method' => $method,
                    'path' => $path,
                    'level' => $level,
                ]);
            }
        });

        return back()->with('status', 'Group endpoint grants updated.');
    }

    // ─── v5: User endpoint overrides ────────────────────────────────

    public function usersEndpointOverrides(string $id): View
    {
        $user = User::findOrFail($id);

        $overrides = \App\Models\TenantEndpointOverride::where('user_id', $user->id)
            ->orderBy('tenant_id')
            ->orderBy('method')
            ->orderBy('path')
            ->get();

        $tenants = Tenant::orderBy('name')->get();

        $allEndpoints = \App\Models\TenantAppEndpoint::orderBy('tenant_id')
            ->orderBy('method')
            ->orderBy('path')
            ->get();

        return view('admin.users.endpoint-overrides', [
            'user' => $user,
            'overrides' => $overrides,
            'tenants' => $tenants,
            'allEndpoints' => $allEndpoints,
        ]);
    }

    public function usersEndpointOverridesStore(Request $request, string $id): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'overrides' => 'nullable|array',
            'overrides.*.method' => 'required|string',
            'overrides.*.path' => 'required|string',
            'overrides.*.level' => 'required|in:read,write,admin,deny',
            'overrides.*.tenant_id' => 'nullable|exists:tenants,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $user = User::findOrFail($id);
        $overrides = $request->input('overrides', []);

        DB::transaction(function () use ($user, $overrides) {
            \App\Models\TenantEndpointOverride::where('user_id', $user->id)->delete();

            foreach ($overrides as $override) {
                $level = $override['level'];
                if ($level === 'deny') {
                    continue;
                }

                $method = strtoupper($override['method']);
                $path = \App\Models\TenantAppEndpoint::normalizePath($override['path']);
                $tenantId = $override['tenant_id'] ?? null;

                \App\Models\TenantEndpointOverride::create([
                    'user_id' => $user->id,
                    'tenant_id' => $tenantId,
                    'method' => $method,
                    'path' => $path,
                    'level' => $level,
                ]);
            }
        });

        return back()->with('status', 'User endpoint overrides updated.');
    }
}
