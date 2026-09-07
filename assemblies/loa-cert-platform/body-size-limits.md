# LOA Cert Platform — Body Size Limits Configuration
## Product Assembly Component Specification

**Version:** 2.0
**Status:** Final
**Layer:** Product Assembly (`loa-cert-platform`) + Frontend (`e-cert.vercel.app`)
**Audience:** Engineers, DevOps, AI Agents

---

# 1. Purpose

Answers:

> **"How does the Cert Platform handle large JSON request bodies (e.g., templates with base64-encoded images) without hitting HTTP 413 errors?"**

This spec documents the body size limit configuration across every layer of the request pipeline, for both local development (Docker) and production (cPanel + Vercel).

---

# 2. Problem Statement

When users edit certificate or email templates with embedded images (base64 data URLs in HTML/CSS), the PATCH request to `PATCH /api/v1/templates/{id)` can exceed the default body size limit at one or more layers, resulting in:

```
HTTP 413 Content Too Large
```

The request pipeline has **four layers** that enforce body size limits:

| # | Layer | Default Limit | Config Location |
|---|-------|---------------|-----------------|
| 1 | **Vercel Edge / Serverless** | ~4.5 MB (Vercel default) | `next.config.ts` rewrite body size / `vercel.json` |
| 2 | Web server (Nginx / Apache) | 1 MB | `docker/nginx/default.conf` / `public/.htaccess` |
| 3 | PHP runtime (`post_max_size`) | 8 MB | `php.ini` / `user.ini` / Dockerfile |
| 4 | Laravel application | None (relies on PHP) | `bootstrap/app.php` exception handler |

A 413 occurs when **any** layer rejects the request before it reaches the next.

> **Root cause:** The Vercel proxy (`e-cert.vercel.app` → `cert-api.lyceumalabang.edu.ph`) is the most common source of 413 errors because Vercel serverless functions default to a ~4.5 MB body size limit for proxied requests.

---

# 3. Scope

## 3.1 In Scope

| Layer | Environment | Config File |
|-------|-------------|-------------|
| Vercel rewrite body size | Production (Vercel) | `next.config.ts` (`bodySize` in rewrite config) |
| Vercel function body size | Production (Vercel) | `vercel.json` (`functions.*.bodySize`) |
| Nginx `client_max_body_size` | Docker (local dev) | `docker/nginx/default.conf` |
| Apache `LimitRequestBody` | cPanel (production) | `public/.htaccess` |
| PHP `post_max_size` | Docker (local dev) | `docker/php/Dockerfile` (via `uploads.ini`) |
| PHP `upload_max_filesize` | Docker (local dev) | `docker/php/Dockerfile` (via `uploads.ini`) |
| PHP `post_max_size` | cPanel (production) | cPanel MultiPHP INI Editor or `.user.ini` |
| Laravel 413 exception handler | Both | `bootstrap/app.php` |

## 3.2 Out of Scope

- Frontend image compression (separate concern — optional secondary fix).
- Auth Platform body size limits (auth has no template editor).
- PDF binary streaming endpoints (use `Content-Type: application/pdf`, not JSON).

---

# 4. Target Limits

All layers are aligned to **10 MB**:

| Setting | Value | Rationale |
|---------|-------|-----------|
| Vercel rewrite body size | `10mb` | Allows proxied JSON payloads up to 10 MB |
| Vercel function body size | `10mb` | Matches rewrite limit |
| Nginx `client_max_body_size` | `10m` | Allows JSON payloads up to 10 MB |
| Apache `LimitRequestBody` | `10485760` (10 MB in bytes) | Same limit for Apache |
| PHP `post_max_size` | `10M` | PHP must accept what the web server passes |
| PHP `upload_max_filesize` | `10M` | Aligns with `post_max_size` for multipart |

> 10 MB is chosen to comfortably accommodate templates with multiple embedded base64 images (a typical base64-encoded PNG at 2000px width is ~500 KB–2 MB).

---

# 5. Configuration by Layer

## 5.1 Vercel Frontend (Production) — PRIMARY FIX

The e-cert frontend (`e-cert.vercel.app`) is a Next.js app deployed on Vercel. It proxies API requests to the cert-api backend via `next.config.ts` rewrites. **This is the most common source of 413 errors** because Vercel serverless functions have a lower default body size limit than the backend.

### 5.1.1 `next.config.ts` — Rewrite body size

```typescript
// next.config.ts
import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  async rewrites() {
    return [
      {
        source: "/api/v1/:path*",
        destination: `${process.env.NEXT_PUBLIC_CERT_API_URL}/api/v1/:path*`,
        // Increase body size for template editor (base64 images in HTML/CSS)
        body: undefined, // Default passthrough — body limit set in vercel.json
      },
    ];
  },
};

export default nextConfig;
```

> **Note:** Next.js rewrites pass the request body through to the destination. The body size limit is controlled by the Vercel function configuration, not the rewrite itself.

### 5.1.2 `vercel.json` — Function body size limit

```json
{
  "functions": {
    "app/api/**/*.js": {
      "bodySize": "10mb"
    }
  }
}
```

- **Alternative:** Set `MAX_BODY_SIZE=10mb` in Vercel Project Settings → Environment Variables, and reference it in the API route handler:

```typescript
// app/api/v1/[...path]/route.ts (if using API routes instead of rewrites)
export const config = {
  api: {
    bodyParser: {
      sizeLimit: "10mb",
    },
  },
};
```

- Apply the `vercel.json` or API route config to the **frontend repo** (not this backend repo).
- Rebuild and redeploy the Vercel frontend after applying.

### 5.1.3 cPanel Backend — `public/.htaccess`

```apache
# Allow large JSON payloads for template editor (base64 images in HTML/CSS)
<IfModule mod_core.c>
    LimitRequestBody 10485760
</IfModule>
```

- The backend (cert-api) must also accept 10 MB payloads — this is already committed.
- See §5.3 for cPanel caveats.

## 5.2 Nginx (Docker local dev)

**File:** `docker/nginx/default.conf`

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php;

    client_max_body_size 10m;

    # ... rest of config
}
```

- Applies to: Docker local development only.
- Default without this setting: 1 MB (nginx default).
- Rebuild required: `docker compose up -d --build`.

## 5.3 Apache (cPanel production)

**File:** `public/.htaccess`

```apache
# Allow large JSON payloads for template editor (base64 images in HTML/CSS)
<IfModule mod_core.c>
    LimitRequestBody 10485760
</IfModule>
```

- Applies to: cPanel production (Apache).
- Default without this setting: unlimited (Apache default is 0 = no limit), **but** some cPanel shared hosting providers override this in the server config with a lower limit (commonly 1–10 MB).
- Must be placed **after** the `RewriteRule` directives (not inside `<IfModule mod_rewrite.c>`).
- `mod_core` is always loaded; the `<IfModule>` guard is defensive.
- **cPanel caveat:** If the hosting provider has `LimitRequestBody` set in the server-level config and has disabled `.htaccess` overrides (`AllowOverride` excludes it), this directive is ignored. In that case, contact the hosting provider to increase the server-level limit.

## 5.4 PHP (Docker local dev)

**File:** `docker/php/Dockerfile`

```dockerfile
# Increase PHP body/upload limits for template editor (base64 images in HTML/CSS)
RUN echo "post_max_size = 10M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "upload_max_filesize = 10M" >> /usr/local/etc/php/conf.d/uploads.ini
```

- Creates `/usr/local/etc/php/conf.d/uploads.ini` with both settings.
- PHP reads `.ini` files from `conf.d/` on startup; no restart needed beyond container rebuild.
- Rebuild required: `docker compose up -d --build`.

## 5.5 PHP (cPanel production)

**File:** `public/.user.ini` (committed, included in dist zip)

```ini
; PHP body/upload limits for template editor (base64 images in HTML/CSS)
post_max_size = 10M
upload_max_filesize = 10M
```

- Applies to: cPanel production (PHP-FPM reads `.user.ini` on request).
- Included in the dist zip via `generate-dist.ps1` — no manual config needed.
- **cPanel caveat:** Some shared hosts disable `.user.ini` overrides. If the settings don't take effect, use cPanel MultiPHP INI Editor instead:
  - cPanel → Software → MultiPHP INI Editor → select PHP version → set `post_max_size = 10M` and `upload_max_filesize = 10M`.

**Verification after deploy:**

```bash
php -i | grep post_max_size
# Should show: post_max_size => 10M => 10485760
```

## 5.6 Laravel Exception Handler (Both environments)

**File:** `bootstrap/app.php`

```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        if ($e->getStatusCode() === 413) {
            return response()->json([
                'status' => 'error',
                'message' => 'Request body exceeds size limit of 10MB. Please compress images or reduce template content size.',
            ], 413);
        }
    });
})
```

- Catches any `HttpException` with status 413 and returns a structured JSON response matching the Cert Platform error envelope (§3.4 of `api-endpoints.md`).
- Applies to both Docker and cPanel (same codebase).
- If Nginx/Apache rejects the request before it reaches Laravel, the frontend receives the raw error. The handler covers the case where PHP passes through a partial/large body that Laravel then rejects.

---

# 6. Coverage Matrix

| Layer | Docker (local dev) | cPanel + Vercel (production) |
|-------|-------------------|------------------------------|
| Vercel proxy body limit | N/A (direct to Docker) | ⚠️ **Manual** — `vercel.json` or API route config in frontend repo |
| Web server body limit | ✅ `docker/nginx/default.conf` | ✅ `public/.htaccess` (`LimitRequestBody`) |
| PHP upload limits | ✅ `docker/php/Dockerfile` | ✅ `public/.user.ini` (committed, in dist zip) |
| Laravel 413 handler | ✅ `bootstrap/app.php` | ✅ `bootstrap/app.php` |
| **Result** | **Fully covered** | **Requires manual Vercel config only** |

---

# 7. Request Flow

```
Frontend PATCH /api/v1/templates/{id}
  │  { name?, description?, html_content, css_content?, type?, visibility? }
  │  (html_content may contain base64 data URLs)
  ▼
┌─────────────────────────────────────────┐
│ Vercel Edge / Serverless Function       │  bodySize = 10mb (vercel.json)
│ Proxies request to cert-api backend     │  Default: ~4.5 MB
│ 413 if exceeded                         │
└─────────┬───────────────────────────────┘
          │ OK
          ▼
┌─────────────────────────────────────────┐
│ Nginx / Apache                          │  client_max_body_size = 10m
│ Rejects if body > 10 MB                 │  LimitRequestBody = 10485760
│ 413 if exceeded                         │
└─────────┬───────────────────────────────┘
          │ OK
          ▼
┌─────────────────────────────────────────┐
│ PHP-FPM                                 │  post_max_size = 10M
│ Parses request body                     │  upload_max_filesize = 10M
│ 413 / silent truncation                 │
│ if exceeded                             │
└─────────┬───────────────────────────────┘
          │ OK
          ▼
┌─────────────────────────────────────────┐
│ Laravel                                 │  bootstrap/app.php exception handler
│ CertificateTemplateController::update() │  catches HttpException 413
│ Validates + persists                    │  returns JSON error envelope
└─────────┬───────────────────────────────┘
          │ OK
          ▼
    200 { data: CertificateTemplate }
```

---

# 8. Verification Steps

After applying all configuration changes:

| # | Step | Expected |
|---|------|----------|
| 1 | Edit a template with embedded images | Form loads, images render |
| 2 | Click "Save Changes" | No 413 error |
| 3 | Verify response is 200 | `{ "data": { ... } }` |
| 4 | Verify template content is preserved | HTML/CSS with images intact |
| 5 | Test with small template (< 1 MB) | Still works (regression check) |
| 6 | Test with large template (5–8 MB) | Saves successfully |
| 7 | Check cPanel PHP settings | `post_max_size = 10M` |
| 8 | Check Vercel function logs | No 413 errors in function invocations |

---

# 9. Troubleshooting

| Symptom | Likely Cause | Fix |
|---------|--------------|-----|
| 413 from Vercel proxy | `vercel.json` body size not configured | Add `bodySize: "10mb"` to `vercel.json` in frontend repo |
| 413 from Nginx (Docker) | Missing `client_max_body_size` | Add to `docker/nginx/default.conf`, rebuild |
| 413 from Apache (cPanel) | `LimitRequestBody` not taking effect | Check `AllowOverride` or contact hosting provider |
| 413 from PHP | `post_max_size` still at default | Set via MultiPHP INI Editor or `.user.ini` |
| Request succeeds but body is empty | PHP `post_max_size` hit mid-parse | Increase `post_max_size` |
| 413 returns HTML instead of JSON | Request rejected before reaching Laravel | Ensure all layers are aligned; Laravel handler only covers requests that reach PHP |

---

# 10. Related Specs

| Spec | Relationship |
|------|--------------|
| `api-endpoints.md` §5.3 | Template CRUD endpoints (including PATCH update) |
| `DEPLOY.md` | Deployment guide (cPanel + Docker) |
| `FRONTEND-INTEGRATION.md` §Vercel Rewrite | Frontend rewrite config (`next.config.ts`) |
| `business-contexts/certificate/entities/template.md` | Template entity aggregate spec |
| `bff-layer.md` | Future BFF layer (may need same body size config) |

---

# 11. Change Log

| Version | Date | Change |
|---------|------|--------|
| 1.0 | 2026-09-07 | Initial spec — body size limit configuration for template editor |
| 2.0 | 2026-09-07 | Added Vercel frontend layer (§5.1), updated request flow, coverage matrix |
