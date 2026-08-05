# Database Seeder Spec

## Purpose

Seed the auth database with a master admin group, all permissions, and an initial admin user on first deployment.

## Status

**Final**

---

## Overview

After running migrations, `php artisan db:seed` creates:

1. **`loa-auth-admin` group** — platform administrator group
2. **All permissions** — every permission key with `granted: true`
3. **Master admin user** — credentials from `.env`

---

## Env Variables

```env
ADMIN_EMAIL=admin@lyceumalabang.edu.ph
ADMIN_PASSWORD=Admin123!
ADMIN_NAME=Super Admin
```

---

## Behavior

### Idempotent

Seeder checks for existing data before inserting:

- If `loa-auth-admin` group exists → skip group creation
- If admin user (by email) exists → skip user creation
- If permissions exist → skip permission creation

Safe to run multiple times.

### Group

| Field | Value |
|-------|-------|
| name | `loa-auth-admin` |
| description | `Platform administrator` |

### Permissions

All permissions from the identity kernel are created with `granted: true` for the `loa-auth-admin` group.

Known permission keys:

| Key | Description |
|-----|-------------|
| `users.view` | View user list and details |
| `users.manage` | Enable/disable users, manage status |
| `groups.view` | View groups |
| `groups.manage` | Create, edit, delete groups |
| `permissions.view` | View permissions |
| `permissions.manage` | Assign permissions to groups |
| `auth.verify` | Validate tokens (internal) |

### Admin User

| Field | Source |
|-------|--------|
| email | `env('ADMIN_EMAIL')` |
| password | `Hash::make(env('ADMIN_PASSWORD'))` |
| name | `env('ADMIN_NAME')` |
| status | `active` |

User is added to the `loa-auth-admin` group.

---

## Deployment Workflow

### Local Dev

```bash
php artisan migrate
php artisan db:seed
```

### cPanel (Deployed)

1. Upload code to cPanel
2. Run migrations via SSH/cPanel terminal:
   ```bash
   php artisan migrate --force
   ```
3. Seed via SSH/cPanel terminal:
   ```bash
   php artisan db:seed --force
   ```
4. Or set up a one-time cron job in cPanel:
   ```
   * * * * * cd /path/to/app && php artisan db:seed --force >> /dev/null 2>&1; crontab -r
   ```

### Notes

- `--force` required in production to confirm destructive action
- Seeder is idempotent — safe to re-run
- No web-triggered seeding (security)

---

## File Locations

| File | Purpose |
|------|---------|
| `database/seeders/DatabaseSeeder.php` | Main seeder, calls AdminSeeder |
| `database/seeders/AdminSeeder.php` | Creates group, permissions, admin user |
| `.env` | `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `ADMIN_NAME` |

---

## Anti-Patterns

- Do NOT seed via HTTP route (security risk)
- Do NOT hardcode credentials in seeder code
- Do NOT skip idempotency checks
- Do NOT seed business data — only platform admin setup
