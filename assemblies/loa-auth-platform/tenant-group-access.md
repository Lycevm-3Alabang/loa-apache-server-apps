# Tenant App Group-Based Access Control

## Product Assembly Component Specification

**Version:** 1.0
**Status:** Final
**Layer:** Product Assembly (`loa-auth-platform`) — web auth surface
**Audience:** Architects, Engineers, AI Development Agents
**Depends on:** `unified-auth-flow.md` Final v1.0 (§5 smart-routing helper, D1), `redirect-interstitial.md` Final v1.0

> Requires tenant-scoped group membership for access to tenant apps. A user
> with only platform-admin groups (`loa-auth-admin`) and no tenant groups
> (`cert-admin`, `cert-staff`, `cert-user`) is shown a dedicated denial page
> instead of entering the app.

---

## 0. Locked decisions

| # | Decision | Choice |
|---|---|---|
| D1 | Access requirement | **At least one tenant-scoped group** — pivot row alone is insufficient |
| D2 | Denial UX | **Dedicated denial page** — not a dashboard redirect with flash |
| D3 | Denial message | `"You don't have access to this application. Contact your administrator."` |
| D4 | Platform admin bypass | **None** — `loa-auth-admin` does NOT grant implicit tenant access (D1 from unified-auth-flow.md) |
| D5 | Affected entry paths | **Both** — SSO login (`enterForTarget`) and dashboard tile click (`go`) |

---

## 1. Purpose

Answers:

> **"How do we prevent a platform admin from entering a tenant app when they
> have no tenant-scoped groups — and what do they see instead?"**

---

## 2. Problem being removed

| Today | Consequence |
|---|---|
| `isMember()` only checks `user_tenants` pivot row | User with `loa-auth-admin` + pivot row but no `cert-*` groups can enter cert app |
| No group-based gate on tenant entry | Platform admins implicitly gain access to all tenant apps they're pivoted into |

---

## 3. New method: `hasTenantGroups()`

**File:** `app/Services/TenantService.php`

```php
public function hasTenantGroups(string $userId, string $tenantId): bool
{
    return \DB::table('user_groups')
        ->join('groups', 'groups.id', '=', 'user_groups.group_id')
        ->where('user_groups.user_id', $userId)
        ->where('groups.tenant_id', $tenantId)
        ->exists();
}
```

Checks if the user has **at least one group** scoped to the given tenant.

---

## 4. Access control updates

### 4.1 SSO login path

**File:** `app/Services/PortalRouter.php` — `enterForTarget()`

Before:
```php
if ($intentTenant && $this->tenants->isMember($user->id, $intentTenant->id)) {
    return $this->enterTenant(...);
}
```

After:
```php
if (
    $intentTenant
    && $this->tenants->isMember($user->id, $intentTenant->id)
    && $this->tenants->hasTenantGroups($user->id, $intentTenant->id)
) {
    return $this->enterTenant(...);
}
```

### 4.2 Dashboard tile path

**File:** `app/Http/Controllers/PortalController.php` — `go()`

After the pivot query passes and before calling `enterTenant()`, add:

```php
if (!$this->tenants->hasTenantGroups($user->id, $tenant->id)) {
    return view('tenant-denial', [
        'tenantName' => $tenant->name,
        'tenantAppUrl' => $tenant->effectiveAppUrl(),
    ]);
}
```

Same check in `enterForTarget()` — return the denial view instead of dashboard redirect:

```php
if (!$this->tenants->hasTenantGroups($user->id, $intentTenant->id)) {
    return view('tenant-denial', [
        'tenantName' => $intentTenant->name,
        'tenantAppUrl' => $intentTenant->effectiveAppUrl(),
    ]);
}
```

**Note:** `enterForTarget()` currently returns `RedirectResponse`. The denial path returns `View` (which Laravel renders as 200). The method signature already allows `View|RedirectResponse`.

---

## 5. Denial page

**File:** `resources/views/tenant-denial.blade.php`

```blade
@extends('layouts.auth')

@section('title', 'Access Denied | LOA Platform')
@section('eyebrow', 'Access Denied')
@section('heading', 'You don\'t have access')
@section('intro', 'You don\'t have access to this application. Contact your administrator.')

@section('content')
    <div style="text-align:center;padding:1.5rem 0;">
        <div style="margin-bottom:1.5rem;">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none"
                 stroke="var(--red-600)" stroke-width="1.5"
                 stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="15" y1="9" x2="9" y2="15"/>
                <line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
        </div>
        <p style="color:var(--text-secondary);font-size:0.9375rem;line-height:1.6;margin:0 0 0.5rem;">
            Application:<br>
            <strong style="color:var(--text);">{{ $tenantName }}</strong>
        </p>
        <a href="{{ route('home') }}" class="button"
           style="display:inline-flex;width:auto;text-decoration:none;">
            Back to Dashboard
        </a>
    </div>
@endsection
```

---

## 6. Edge cases

| Case | Behavior |
|---|---|
| User not in tenant at all (no pivot) | Existing behavior: dashboard redirect + flash error |
| User has pivot but no groups | **New:** dedicated denial page |
| User has pivot + at least one group | Normal entry into tenant app |
| Platform admin with tenant groups | Normal entry (groups grant access, not admin role) |
| Platform admin without tenant groups | **New:** dedicated denial page |

---

## 7. Security invariants

1. Group check is server-side — never trusting client-side JWT claims for entry decisions.
2. `isMember()` (pivot check) is preserved for admin API operations (add/remove member).
3. `hasTenantGroups()` queries `user_groups` + `groups` tables — tenant-scoped groups only.
4. Denial page shows tenant name but no tokens or sensitive data.
5. Platform admin role (`loa-auth-admin`) has no implicit bypass (D1 from unified-auth-flow.md).

---

## 8. Testing checklist

- [ ] User with pivot + cert groups → enters cert app normally
- [ ] User with pivot + NO cert groups → sees denial page
- [ ] User with no pivot → dashboard redirect + flash (unchanged)
- [ ] Platform admin with cert groups → enters cert app normally
- [ ] Platform admin with NO cert groups → sees denial page
- [ ] Dashboard tile click → same group check applies
- [ ] Denial page shows tenant name
- [ ] "Back to Dashboard" link works
- [ ] Admin API add/remove member still works (isMember unchanged)
- [ ] `hasTenantGroups()` returns false for empty group set

---

## 9. Doc control

| Version | Date | Change |
|---|---|---|
| 1.0 Final | 2026-08-29 | Initial Final: group-based tenant access control + denial page |
