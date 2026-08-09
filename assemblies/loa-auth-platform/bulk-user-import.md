# Bulk User Import Wizard

## Product Assembly Component Specification

**Version:** 1.0
**Status:** Final
**Layer:** Product Assembly (`loa-auth-platform`) — admin surface
**Audience:** Architects, Engineers, AI Development Agents

> Extends `admin-dashboard.md` (Final v3) with CSV-based bulk user import.
>
> This spec adds a multi-step wizard for uploading a CSV, validating rows, previewing changes, and processing an upsert import of users with tenant and group assignments.

---

## 1. Purpose

It answers:

> **"How do I import hundreds of users at once with their tenant and group assignments?"**

Four steps:

| Step | Description |
|------|-------------|
| **Upload** | Admin uploads a CSV file with required headers |
| **Preview** | System validates rows, shows status/remarks, supports filtering and row removal |
| **Confirm** | Summary of ready/error/removed rows; admin confirms import |
| **Process** | Execute upsert, show results, allow download of failed rows |

---

## 2. Ownership

### Owns

- CSV upload and parsing.
- Row-level validation (tenant exists, group exists, duplicate detection).
- Preview table with filtering, pagination, and row removal.
- Import execution (upsert users + tenant/group assignments).
- Failed row export as CSV.

### Does Not Own

- User CRUD (single-user create/edit owned by `admin-dashboard.md`).
- Tenant CRUD (owned by `admin-dashboard.md` §8).
- Group/permission management (owned by `group-permission-management.md`).
- JWT API surface (owned by `IdentityService`).

---

## 3. CSV Schema

### Required Headers

```
name,email,tenant_app,user_group
```

No additional columns are permitted. The parser must reject files with missing or extra columns.

### Field Rules

| Field | Required | Format | Notes |
|-------|----------|--------|-------|
| `name` | Yes | Non-empty string, max 255 chars | User display name |
| `email` | Yes | Valid email format, max 255 chars | Used as the natural key for upsert |
| `tenant_app` | Yes | Non-empty string | Must match an existing tenant `slug` |
| `user_group` | Yes | Non-empty string | Must match an existing group `name` within the target tenant |

### Example CSV

```csv
name,email,tenant_app,user_group
John Doe,john@test.com,loa,cert-admin
Jane Smith,jane@test.com,loa,cert-staff
```

---

## 4. Routes

### 4.1 Web Routes

| Method | URI | Action | Route Name |
|--------|-----|--------|------------|
| `GET` | `/admin/users/import` | show upload form | `admin.users.import` |
| `POST` | `/admin/users/import/preview` | parse CSV + validate + show preview | `admin.users.import.preview` |
| `POST` | `/admin/users/import/process` | execute import | `admin.users.import.process` |
| `GET` | `/admin/users/import/failed` | download failed rows CSV | `admin.users.import.failed` |

All routes require `auth` (web guard) + `web.admin` + `users.manage`.

### 4.2 API Routes

| Method | URI | Action |
|--------|-----|--------|
| `POST` | `/api/v1/admin/users/import/preview` | parse CSV + validate (dry-run) |
| `POST` | `/api/v1/admin/users/import/process` | execute import |
| `GET` | `/api/v1/admin/users/import/failed` | download failed rows CSV |

---

## 5. Controller

New controller: `UserImportController`

```
App\Http\Controllers\UserImportController
```

**Dependencies:**
- `IdentityService` — for user creation (`register()`).
- `ActivationService` — for generating activation tokens.
- `TenantService` — for tenant lookup and membership.
- `AuthorizationService` — for group existence and membership.
- `Mail` facade — for sending activation emails.

**Methods:**

| Method | Description |
|--------|-------------|
| `showForm()` | Render the CSV upload form |
| `preview(Request $request)` | Parse CSV, validate rows, store in session, render preview |
| `process(Request $request)` | Execute upsert for all "ready" rows, render results |
| `downloadFailed()` | Generate and download CSV of failed rows |

---

## 6. Upload Step (Step 1)

### 6.1 Form

- File input: `<input type="file" accept=".csv">`.
- Upload button.
- No other fields.

### 6.2 Validation

1. **File required.** Return `422` if no file uploaded.
2. **File type.** Must be `.csv` (validate MIME type + extension).
3. **File size.** Max 5 MB.
4. **Header check.** Parse first row, verify exact match: `name,email,tenant_app,user_group`.
   - Missing required columns → reject with error listing missing columns.
   - Extra columns → reject with error listing extra columns.
   - Wrong column order → reject (order must be exact).

### 6.3 Parse

- Use `League\Csv\Reader` (already in Laravel's dependency tree via `illuminate/mail`) or PHP's native `fgetcsv`.
- Skip header row.
- Trim whitespace from all fields.
- Preserve original row numbers (for error reporting).

---

## 7. Preview Step (Step 2)

### 7.1 Storage

Parsed rows are stored in the session (`session('import_rows')`) as an array of objects. The session key is cleared after import or on new upload.

### 7.2 Row Validation

Each row is validated against these rules:

| Rule | Check | Error |
|------|-------|-------|
| **Email format** | Valid email | `email is invalid` |
| **Duplicate in CSV** | Same email appears in another row | `Duplicate email found in uploaded file` |
| **Tenant exists** | `tenant_app` matches a `Tenant::where('slug', $tenant_app)` | `tenant_app does not exist` |
| **Group exists** | `user_group` matches a `UserGroup::where('name', $user_group)` within the target tenant | `user_group does not exist` |

### 7.3 Row Status

Each row gets a status:

| Status | Meaning | Blocking? |
|--------|---------|-----------|
| `ready` | All validations pass; row will be processed | No |
| `ready_existing` | Email exists in DB; user will be reused, tenant/group updated | No |
| `error` | One or more validation failures | Yes |

### 7.4 Duplicate Email Handling

**Within CSV (Case 1):**
- First occurrence: `ready` or `ready_existing`.
- Second+ occurrence: `error` — "Duplicate email found in uploaded file".

**In database (Case 2):**
- Email exists in `users` table: `ready_existing`.
- Status = `ready`, Remarks = "Existing user — will update tenant and group assignment".
- This is NOT an error. The user will be reused.

### 7.5 Preview Table

| Column | Source |
|--------|--------|
| `name` | CSV value |
| `email` | CSV value |
| `tenant_app` | CSV value |
| `user_group` | CSV value |
| `status` | `ready` / `ready_existing` / `error` |
| `remarks` | Validation messages (semicolon-separated) |
| `actions` | Remove button (client-side row removal) |

### 7.6 Filtering

| Filter | Shows |
|--------|-------|
| All | All imported rows |
| OK | Rows with `ready` or `ready_existing` status |
| With Errors | Rows with `error` status |
| To Resolve | Rows with `error` status (alias for "With Errors") |

### 7.7 Pagination

- Default: 25 rows per page.
- Configurable via page-size selector (25, 50, 100).
- Preserve filter state across pages (query string).

### 7.8 Row Removal

- Remove button per row (client-side via JavaScript).
- Removed rows are excluded from processing.
- Row removal is tracked in a hidden form field (array of removed row indices).

---

## 8. Confirm Step (Step 3)

### 8.1 Summary

Before processing, display:

```
Total Rows:     500
Ready:          470
Existing User:   20
Errors:          10
Removed:          5
```

### 8.2 Confirm Button

- Only enabled if `Ready + Existing User > 0`.
- Confirmation checkbox: "I understand this will create/update users and assign them to tenants and groups."
- If all rows are errors or removed, show "Nothing to import" message.

---

## 9. Process Step (Step 4)

### 9.1 Execution

For each row with status `ready` or `ready_existing`:

**New User (`ready`):**
1. Create user via `IdentityService::register($email, '', $name)`.
   - `IdentityService::register()` creates with `status: active`.
2. Override status to `pending` (per `user-account-activation.md`).
3. Generate activation token via `ActivationService::createActivation($user)`.
4. Send activation email via `Mail::send('emails.activate-account', ['user' => $user, 'token' => $rawToken], ...)`.
5. Resolve tenant by slug: `TenantService::getTenantBySlug($tenant_app)`.
6. Resolve group by name within tenant: `UserGroup::where('name', $user_group)->where('tenant_id', $tenantId)->firstOrFail()`.
7. Add user to tenant: `TenantService::addUserToTenant($userId, $tenantId)`.
8. Add user to group: `AuthorizationService::addToGroup($userId, $groupId)`.

**Existing User (`ready_existing`):**
1. Find user by email: `User::where('email', $email)->firstOrFail()`.
2. Resolve tenant and group (same as above).
3. Add user to tenant (if not already a member): `TenantService::addMember($tenantId, $userId)`.
4. Add user to group (if not already a member): `AuthorizationService::addToGroup($userId, $groupId)`.

### 9.2 Batch Processing

- Process rows in batches of 50 to avoid timeout.
- Each batch runs in a separate DB transaction.
- If a row fails within a batch, it is marked as failed and processing continues with the next row.
- Do NOT wrap the entire import in a single transaction.

### 9.3 Result Tracking

Track results per row:

| Result | Meaning |
|--------|---------|
| `success` | User created/existing + tenant/group assigned |
| `failed` | Exception during processing (store error message) |

### 9.4 Results Display

After processing:

```
Successful:  450
Failed:       50
```

Results table shows:
- Same columns as preview (name, email, tenant_app, user_group).
- `status` column now shows `success` or `failed`.
- `remarks` column shows error message for failed rows.
- Download Failed button (visible if any rows failed).

### 9.5 UI Refresh

After import:
- Do NOT reload the entire page.
- Only refresh the results component via AJAX.
- Clear the session import data.

---

## 10. Failed Row Export

### 10.1 Download

- `GET /admin/users/import/failed` returns a CSV download.
- Filename: `user-import-failed-{date}.csv`.
- Only available if there are failed rows in the session.

### 10.2 CSV Format

```csv
name,email,tenant_app,user_group,REMARKS
John Doe,john@test.com,InvalidApp,Admin,"tenant_app does not exist"
```

- Preserves original uploaded values.
- `REMARKS` column contains the validation/processing error.
- Same column order as the input CSV, plus `REMARKS`.

---

## 11. Validation Rules (Laravel)

### 11.1 File Upload Validation

```php
$validator = Validator::make($request->all(), [
    'file' => 'required|file|mimes:csv,txt|max:5120',
]);
```

### 11.2 CSV Header Validation

After parsing, verify:

```php
$requiredHeaders = ['name', 'email', 'tenant_app', 'user_group'];
$actualHeaders = array_map('trim', $headers);
$missing = array_diff($requiredHeaders, $actualHeaders);
$extra = array_diff($actualHeaders, $requiredHeaders);

if (!empty($missing) || !empty($extra) || $actualHeaders !== $requiredHeaders) {
    // Reject with error
}
```

### 11.3 Row Validation

```php
foreach ($rows as $index => $row) {
    $rules = [
        'name' => 'required|string|max:255',
        'email' => 'required|email|max:255',
        'tenant_app' => 'required|string',
        'user_group' => 'required|string',
    ];
    // Plus business rules: tenant exists, group exists, no duplicate in CSV
}
```

---

## 12. Invariants

1. CSV input is **strictly** four columns: `name, email, tenant_app, user_group`.
2. Import is an **upsert** — existing users (by email) are reused, not duplicated.
3. Tenant and group must exist before import; the import does not create tenants or groups.
4. Row removal is client-side only; removed rows are not sent to the server.
5. Session data is cleared after import or on new upload.
6. Failed rows are downloadable as CSV with original values + remarks.
7. Batch processing (50 rows per transaction) prevents timeout on large imports.
8. New users are created with `status: pending` and receive an activation email (per `user-account-activation.md`).
9. Existing users (already in DB) are reused — no duplicate creation, no activation email.

---

## 13. Security Checklist

- [ ] All routes behind `auth` (web guard) + `web.admin` + `users.manage`
- [ ] CSRF on every POST (web routes)
- [ ] File upload: validate MIME type, max size (5 MB)
- [ ] No SQL injection — all queries use Eloquent
- [ ] Preview never writes to DB (session-only)
- [ ] Batch processing with transaction isolation
- [ ] New users created with `status: pending` (not `active`)
- [ ] Activation email sent via `ActivationService` + `Mail` facade
- [ ] Session import data cleared after processing

---

## 14. Anti-Patterns

| Pattern | Why It's Wrong | Correct Approach |
|---------|----------------|------------------|
| Single transaction for entire import | Timeout on large files | Batch processing (50 rows per transaction) |
| Creating users without activation email | Users cannot log in | Send activation email via existing Mail facade |
| Accepting extra CSV columns | Unexpected data injection | Strict header validation, reject extra columns |
| Storing import data in client state | Data loss on refresh | Server-side session storage |
| Processing errors as blocking | One bad row blocks entire import | Continue processing, report failed rows |

---

## 15. Implementation Inventory

| Layer | Item | Status |
|-------|------|--------|
| Spec | `bulk-user-import.md` | **Final v1.0** |
| Controller | `UserImportController` | To implement |
| Routes | Web + API routes for import flow | To add |
| Model | No new models — uses existing `User`, `Tenant`, `UserGroup` | Existing |
| Migration | None required | — |
| Admin UI | Upload form, preview table, results table, failed download | To implement |
| Tests | Controller tests for upload/preview/process/download | To write |

---

## 16. Dependency References

| Spec | Role |
|------|------|
| `admin-dashboard.md` (Final v3) | Route group, admin UI patterns, user creation logic (§9) |
| `group-permission-management.md` | Group/permission administration |
| `kernels/identity/tenancy.md` | Tenant entity, membership model |
| `kernels/identity/entities/user.md` | User entity |
| `kernels/identity/entities/user-group.md` | Group entity |
| `kernels/identity/README.md` | `IdentityService::register()` contract |
| `PasswordResetNotificationService` | Sends reset links to new users |
