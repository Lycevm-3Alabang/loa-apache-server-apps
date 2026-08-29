<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\RefreshToken;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PasswordResetNotificationService;
use App\Services\PortalRouter;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Post-login portal surface (dashboard-account.md): the console dashboard at
 * `/` for every authenticated user, tile entry into tenant apps with
 * server-side membership re-checks, and a minimal account page.
 */
class PortalController extends Controller
{
    public function __construct(
        private readonly PortalRouter $router,
        private readonly AuditLogger $audit,
        private readonly PasswordResetNotificationService $passwordResets,
        private readonly TenantService $tenants,
    ) {
    }

    /**
     * Default page (dashboard-account.md v1.1): the console-styled dashboard
     * for EVERY authenticated user — enrolled app tiles + account summary.
     * Only a validated ?redirect= intent routes away (straight into that
     * tenant app); guests fall through to login / sso-login. There is no
     * auto-enter: membership count never skips the dashboard.
     */
    public function home(Request $request): View|RedirectResponse
    {
        if (!Auth::guard('web')->check()) {
            // Preserve the full query string (?redirect= etc.) so the login
            // pipeline's own safeRedirectUrl() validation still applies.
            $query = $request->query();

            if ($query !== []) {
                return redirect()->to('/sso/login?'.http_build_query($query));
            }

            return redirect()->route('login');
        }

        $user = $this->authUser();

        $target = $this->safeRedirectUrl($request->query('redirect'));

        if ($target !== null) {
            return $this->router->enterForTarget($request, $user, $target);
        }

        $isAdmin = $this->router->isAdmin($user);

        $viewData = [
            'tenants' => $this->router->activeMemberships($user),
            'isAdmin' => $isAdmin,
            'portalUser' => $user,
        ];

        if ($isAdmin) {
            $viewData = array_merge($viewData, $this->adminZoneData());
        }

        return view('dashboard', $viewData);
    }

    public function go(Request $request, string $tenant): View|RedirectResponse
    {
        $user = $this->authUser();

        // Pivot-scoped lookup doubles as the server-side membership check.
        $tenant = $user->tenants()
            ->where('status', 'active')
            ->where('tenants.id', $tenant)
            ->first();

        if (!$tenant) {
            return redirect()
                ->route('home')
                ->with('error', 'You do not have access to that application.');
        }

        $url = $tenant->effectiveAppUrl();

        if (!$url) {
            return redirect()
                ->route('home')
                ->with('error', 'That application is not available right now.');
        }

        if (!$this->tenants->hasTenantGroups($user->id, $tenant->id)) {
            return view('tenant-denial', [
                'tenantName' => $tenant->name,
                'tenantAppUrl' => $url,
            ]);
        }

        return $this->router->enterTenant($request, $user, $tenant, (string) $url, null, 'portal');
    }

    public function account(Request $request): View
    {
        return view('account', [
            'portalUser' => $this->authUser(),
            'editName' => $request->query('edit') === 'name',
        ]);
    }

    /**
     * Self-service name update (dashboard-account.md §5): the only editable
     * profile field on /account; email and status remain read-only.
     */
    public function updateName(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('portal.account', ['edit' => 'name'])
                ->withErrors($validator)
                ->withInput();
        }

        $user = $this->authUser();
        $oldName = $user->name;
        $name = trim($request->string('name')->toString());

        if ($name === '') {
            return redirect()
                ->route('portal.account', ['edit' => 'name'])
                ->withErrors(['name' => 'The name field is required.'])
                ->withInput();
        }

        $user->update(['name' => $name]);

        $this->audit->recordSafe(
            'auth.profile.name_update',
            'user',
            $user->id,
            ['from' => $oldName, 'to' => $name],
        );

        return redirect()->route('portal.account')->with('status', 'Name updated.');
    }

    /**
     * Change-password = emailed reset link (dashboard-account.md v1.3 D17).
     * Reuses the platform's change-request notification path so token TTL and
     * email template match the rest of the identity surface; possession of
     * the emailed signed token replaces current-password verification. No
     * navigation away — flash + stay. Using the link signs the user out of
     * every LOA application (WebResetController + refresh-token revocation).
     */
    public function emailResetLink(Request $request): RedirectResponse
    {
        $user = $this->authUser();

        $this->passwordResets->sendChangePasswordLink($user);

        $this->audit->recordSafe(
            'auth.profile.password_reset_request',
            'user',
            $user->id,
            ['email' => $user->email],
        );

        // Authenticated context: no anti-enumeration copy needed — the user
        // is signed in with this address.
        return back()->with('status', "Reset link sent to {$user->email}.");
    }

    private function authUser(): User
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        return $user;
    }

    /**
     * Assembles data for the platform-admin zone on the dashboard
     * (admin-dashboard-home.md §4). Wrapped in try/catch per H4: query
     * failures degrade to an empty zone, never break the apps grid.
     */
    private function adminZoneData(): array
    {
        try {
            return $this->buildAdminZoneData();
        } catch (\Throwable $e) {
            report($e);

            return ['adminZoneFailed' => true];
        }
    }

    private function buildAdminZoneData(): array
    {
        $stats = $this->buildStatCards();
        $attention = $this->buildAttentionQueue();
        $activity = $this->buildActivityFeed();

        return [
            'adminZoneFailed' => false,
            'adminStats' => $stats,
            'adminAttention' => $attention,
            'adminActivity' => $activity,
        ];
    }

    private function buildStatCards(): array
    {
        $userCounts = User::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'disabled' THEN 1 ELSE 0 END) as disabled
        ")->first();

        $tenantCounts = Tenant::selectRaw("
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status != 'active' THEN 1 ELSE 0 END) as inactive
        ")->first();

        $activeSessions = RefreshToken::whereNull('revoked_at')
            ->distinct('user_id')
            ->count('user_id');

        $memberships = DB::table('user_tenants')->count();

        return [
            'users_total' => (int) ($userCounts->total ?? 0),
            'users_pending' => (int) ($userCounts->pending ?? 0),
            'users_disabled' => (int) ($userCounts->disabled ?? 0),
            'tenants_active' => (int) ($tenantCounts->active ?? 0),
            'tenants_inactive' => (int) ($tenantCounts->inactive ?? 0),
            'active_sessions' => $activeSessions,
            'memberships' => $memberships,
        ];
    }

    private function buildAttentionQueue(): array
    {
        $items = [];

        // Priority 2: pending users
        $pendingCount = User::where('status', 'pending')->count();
        if ($pendingCount > 0) {
            $items[] = [
                'priority' => 2,
                'copy' => "{$pendingCount} user".($pendingCount !== 1 ? 's' : '')." awaiting activation",
                'url' => route('admin.users', ['status' => 'pending']),
            ];
        }

        // Priority 3: failed user-import rows (session-based)
        $importFailed = session('import_failed_rows', []);
        if (!empty($importFailed)) {
            $items[] = [
                'priority' => 3,
                'copy' => 'Last user import had failures',
                'url' => route('admin.users.import.failed'),
            ];
        }

        // Priority 4: failed tenant-member-import rows (session-based)
        $tenantImportFailed = session('tenant_member_import_failed_rows', []);
        if (!empty($tenantImportFailed)) {
            $items[] = [
                'priority' => 4,
                'copy' => 'Tenant member import has failures',
                'url' => '#', // No dedicated route; linked from tenant context
            ];
        }

        // Priority 5: active tenants with zero members (exclude platform tenant —
        // its members use platform-level groups with tenant_id = NULL)
        $emptyTenants = Tenant::where('status', 'active')
            ->where('slug', '!=', 'auth')
            ->whereDoesntHave('users')
            ->pluck('name', 'id');

        foreach ($emptyTenants as $tenantId => $tenantName) {
            $items[] = [
                'priority' => 5,
                'copy' => "{$tenantName} has no members",
                'url' => route('admin.tenants.show', $tenantId),
            ];
        }

        // Priority 6: dev_app_url configured in production
        if (app()->environment('production')) {
            $devUrlTenants = Tenant::where('status', 'active')
                ->whereNotNull('dev_app_url')
                ->whereColumn('dev_app_url', '!=', 'app_url')
                ->pluck('name', 'id');

            foreach ($devUrlTenants as $tenantId => $tenantName) {
                $items[] = [
                    'priority' => 6,
                    'copy' => "{$tenantName} has dev URL configured in production",
                    'url' => route('admin.tenants.edit', $tenantId),
                ];
            }
        }

        // Sort by priority, cap at 5
        usort($items, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        $hidden = array_slice($items, 5);
        $items = array_slice($items, 0, 5);

        // Build aggregate line for hidden items
        if (!empty($hidden)) {
            $categories = array_map(fn ($item) => $item['copy'], $hidden);
            $items[] = [
                'priority' => 99,
                'copy' => count($hidden).' more: '.implode(', ', $categories),
                'url' => null,
                'aggregate' => true,
            ];
        }

        return $items;
    }

    private function buildActivityFeed(): array
    {
        return AuditLog::where('action', '!=', 'auth.tenant_entry')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (AuditLog $log) => [
                'created_at' => $log->created_at,
                'actor_email' => $log->actor_email ?? 'system',
                'action' => $log->action,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'details' => $log->details,
                'url' => route('admin.audit-logs', ['action' => $log->action]),
            ])
            ->toArray();
    }
}
