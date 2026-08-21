<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\ActivationService;
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
    ) {
    }

    // ─── v1: User management ───────────────────────────────────────

    public function index(Request $request): View
    {
        $query = User::query()->orderByDesc('created_at');

        $q = trim((string) $request->query('q', ''));

        if ($q !== '') {
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%"));
        }

        $status = (string) $request->query('status', 'all');

        if (in_array($status, ['active', 'disabled', 'locked'], true)) {
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

        try {
            $this->identity->setUserStatus($id, $request->input('status'));
        } catch (\Throwable) {
            return back()->with('error', 'Unable to update user status.');
        }

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
        ]);
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

    public function tenantsShow(Tenant $tenant): View
    {
        $tenant->loadCount('users');

        $members = $tenant->users()
            ->with('userGroups')
            ->orderBy('email')
            ->paginate(25);

        $nonMembers = User::whereNotIn('id', $tenant->users()->pluck('users.id'))
            ->orderBy('email')
            ->get();

        return view('admin.tenants.show', [
            'tenant' => $tenant,
            'members' => $members,
            'nonMembers' => $nonMembers,
        ]);
    }

    public function tenantsGroupsStore(Request $request, Tenant $tenant): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'priority' => 'required|integer|min:1|max:100',
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

    public function tenantsGroupsPermissions(Request $request, Tenant $tenant, UserGroup $group): RedirectResponse
    {
        if ($group->tenant_id !== $tenant->id) {
            return back()->with('error', 'Group does not belong to this tenant.');
        }

        $validator = Validator::make($request->all(), [
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        if ($validator->fails()) {
            return back()->with('error', 'Invalid permission data.');
        }

        $allowedIds = $request->input('permissions', []);

        DB::transaction(function () use ($group, $tenant, $allowedIds) {
            $allPermissions = Permission::pluck('id');

            foreach ($allPermissions as $permId) {
                $group->permissions()->updateExistingPivot(
                    $permId,
                    ['granted' => in_array($permId, $allowedIds, true), 'tenant_id' => $tenant->id],
                );
            }
        });

        return back()->with('status', 'Permissions updated.');
    }

    // ─── v2: Tenant members ────────────────────────────────────────

    public function tenantsMembersStore(Request $request, Tenant $tenant): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'action' => 'required|in:add,remove',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $userId = $request->input('user_id');
        $action = $request->input('action');

        try {
            if ($action === 'add') {
                $this->tenants->addUserToTenant($userId, $tenant->id);
                return back()->with('status', 'User added to tenant.');
            }

            $this->tenants->removeUserFromTenant($userId, $tenant->id);

            return back()->with('status', 'User removed from tenant.');
        } catch (\Throwable) {
            return back()->with('error', 'Unable to update membership.');
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
            'priority' => 'required|integer|min:1|max:100',
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
        $group->load(['users', 'permissions']);

        $allPermissions = Permission::orderBy('key')->get();

        $nonMembers = User::whereNotIn('id', $group->users->pluck('id'))
            ->orderBy('email')
            ->get();

        return view('admin.groups.show', [
            'group' => $group,
            'allPermissions' => $allPermissions,
            'nonMembers' => $nonMembers,
        ]);
    }

    public function groupsPermissions(Request $request, UserGroup $group): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        if ($validator->fails()) {
            return back()->with('error', 'Invalid permission data.');
        }

        $allowedIds = $request->input('permissions', []);

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

    public function groupsMembersStore(Request $request, UserGroup $group): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $userId = $request->input('user_id');

        if ($group->users()->where('users.id', $userId)->exists()) {
            return back()->with('error', 'User is already in this group.');
        }

        $this->authorization->addToGroup($userId, $group->id);

        return back()->with('status', 'User added to group.');
    }

    public function groupsMembersRemove(UserGroup $group, string $userId): RedirectResponse
    {
        $this->authorization->removeFromGroup($userId, $group->id);

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

    public function storeUserGroup(Request $request, string $id): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'group_id' => 'required|exists:user_groups,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $userId = $id;
        $groupId = $request->input('group_id');

        try {
            $this->authorization->addToGroup($userId, $groupId);
        } catch (\Throwable) {
            return back()->with('error', 'Unable to add user to group.');
        }

        return back()->with('status', 'User added to group.');
    }

    public function removeUserGroup(string $id, string $groupId): RedirectResponse
    {
        $this->authorization->removeFromGroup($id, $groupId);

        return back()->with('status', 'User removed from group.');
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
            'grants.*.level' => 'required|in:none,read,write,admin,deny',
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
                if ($level === 'none') {
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
            'overrides.*.level' => 'required|in:none,read,write,admin,deny',
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
                if ($level === 'none') {
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
