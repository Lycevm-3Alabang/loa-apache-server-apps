# BFF Layer — Dedicated Backend-for-Frontend

## Product Assembly Component Specification

**Version:** 0.1
**Status:** Draft (Future — not developed now)
**Layer:** Product Assembly (`loa-cert-platform`) — future standalone service
**Audience:** Architects, Engineers, AI Development Agents

> A dedicated Backend-for-Frontend service that aggregates cert-api and
> auth-platform into a single frontend-facing API.  Replaces the thin
> auth proxy (`auth-proxy.md`) when multiple tenant apps need unified
> access to cross-platform data.

---

## 1. Purpose

Answers:

> **"How do multiple tenant frontends (cert, consult, future) access a
> unified API without duplicating proxy logic in each tenant app's backend?"**

The thin auth proxy (`auth-proxy.md`) embeds auth-platform proxying inside
cert-api.  This works for one tenant app, but if consult or future apps
need the same user/group/membership data, each would duplicate the proxy
logic.  A dedicated BFF eliminates this duplication.

---

## 2. Ownership

### Owns

- Frontend-facing API surface (single entry point).
- Cross-service aggregation (cert-api + auth-platform).
- Response shaping for frontend consumption.
- BFF-specific auth (session management, token exchange).

### Does Not Own

- Cert domain logic — owned by cert-api.
- Auth domain logic — owned by auth-platform.
- User entity — owned by auth-platform.
- Certificate entity — owned by cert-api.
- Frontend UI — owned by each tenant app.

---

## 3. Relationship to Existing Specs

| Spec | Relationship |
|------|--------------|
| `auth-proxy.md` (cert-platform) | Current thin proxy — BFF replaces this when scaling to multiple tenant apps |
| `tenant-app-api.md` (auth-platform) | Upstream tenant member API consumed by BFF |
| `api-endpoints.md` (cert-platform) | Cert-api endpoints aggregated by BFF |
| `consult-readiness.md` (consult-platform) | Future consumer of BFF |
| `FRONTEND-INTEGRATION.md` (cert-platform) | Current integration — BFF becomes the new integration target |

---

## 4. When to Build

### Trigger Conditions

Build the BFF when **any** of the following is true:

1. **Consult frontend needs user/group management** — same endpoints cert needs.
2. **A third tenant app arrives** — would otherwise duplicate proxy logic again.
3. **Frontend needs aggregated data** — e.g., dashboard combining cert stats + user counts + membership info in one call.
4. **Response shaping needed** — frontends need different data shapes than upstream APIs provide.

### Current Status

| Trigger | Status |
|---------|--------|
| Consult frontend needs user mgmt | Not yet |
| Third tenant app | Not yet |
| Aggregated data needed | Not yet |
| Response shaping needed | Not yet |

**Decision: Thin proxy (`auth-proxy.md`) is sufficient for now.**

---

## 5. Architecture

### Current (Thin Proxy)

```
Frontend (e-cert)
  → cert-api (/api/v1/*)
    → auth-platform (proxy for users/groups/members)
    → cert-api DB (certs, events, templates)
```

### Future (Dedicated BFF)

```
Frontend (e-cert)
  → BFF (/bff/*)
    → cert-api (/api/v1/*)
    → auth-platform (/api/v1/*)
    → consult-api (/api/v1/*)          [future]
    → ... other services
```

### BFF Characteristics

- **Thin:** No business logic, no database.  Routes + aggregates + reshapes.
- **Stateless:** No sessions of its own.  Uses JWT from frontend.
- **Per-tenant routing:** BFF knows which downstream service owns which data.
- **Response shaping:** Can merge, filter, or transform upstream responses.

---

## 6. Design Decisions

| # | Decision | Choice | Rationale |
|---|----------|--------|-----------|
| D1 | Separate service vs embedded | **Separate service** | Keeps cert-api clean; avoids coupling cert deployment with frontend needs |
| D2 | Framework | **Node.js / lightweight HTTP** or **Laravel** | Depends on team familiarity; Laravel matches existing assemblies |
| D3 | Auth model | **JWT pass-through** — forward caller's token to downstream services | BFF doesn't own auth; downstream services validate tokens |
| D4 | API key management | **BFF stores API keys** for each downstream service | Server-side secret; never exposed to browser |
| D5 | Response aggregation | **Per-endpoint aggregation** — each BFF endpoint knows which upstream(s) to call | Explicit, debuggable; no generic aggregation engine |
| D6 | Deployment | **Same host as cert-api** initially (different port) | Simplifies ops; can split later |

---

## 7. Endpoint Surface (Future)

### 7.1 Users & Groups (from auth-platform)

| Method | BFF Route | Upstream | Description |
|--------|-----------|----------|-------------|
| `GET` | `/bff/users` | auth `GET /api/v1/users` | List users |
| `PATCH` | `/bff/users/{id}/status` | auth `PATCH /api/v1/users/{id}/status` | Enable/disable |
| `GET` | `/bff/groups` | auth `GET /api/v1/groups` | List groups |

### 7.2 Members (from auth-platform, API key)

| Method | BFF Route | Upstream | Description |
|--------|-----------|----------|-------------|
| `GET` | `/bff/members` | auth `GET /api/v1/tenant/members` | List members |
| `POST` | `/bff/members` | auth `POST /api/v1/tenant/members` | Add member |
| `DELETE` | `/bff/members/{id}` | auth `DELETE /api/v1/tenant/members/{id}` | Revoke |
| `POST` | `/bff/members/invite` | auth `POST /api/v1/tenant/members/invite` | Invite |

### 7.3 Aggregated Endpoints (new value-add)

| Method | BFF Route | Upstreams | Description |
|--------|-----------|-----------|-------------|
| `GET` | `/bff/dashboard` | cert `/dashboard/stats` + auth `/users` (count) | Combined dashboard |
| `GET` | `/bff/users/{id}/activity` | auth `/users/{id}` + cert `/attendees/lookup?email=` | User activity summary |
| `GET` | `/bff/members-with-certs` | auth `/tenant/members` + cert `/certificates` | Members with their cert counts |

### 7.4 Cert Domain (pass-through to cert-api)

| Method | BFF Route | Upstream | Description |
|--------|-----------|----------|-------------|
| `GET/POST/PATCH/DELETE` | `/bff/certs/*` | cert `/api/v1/certificates/*` | Pass-through |
| `GET/POST/PATCH/DELETE` | `/bff/events/*` | cert `/api/v1/events/*` | Pass-through |
| `GET/POST/PATCH/DELETE` | `/bff/templates/*` | cert `/api/v1/templates/*` | Pass-through |

---

## 8. Migration Path

### Phase 1: Current State (Thin Proxy)

- Cert-api has `AuthProxyController` proxying to auth-platform.
- Frontend calls cert-api only.
- No BFF.

### Phase 2: BFF Extraction

1. Create BFF service with routes mirroring cert-api's `/api/v1/*` and the proxy's `/api/v1/service/*`.
2. BFF calls cert-api and auth-platform as upstream services.
3. Frontend rewires from cert-api to BFF.
4. Remove `AuthProxyController` from cert-api (BFF replaces it).

### Phase 3: Multi-Tenant BFF

1. Consult frontend rewires to BFF.
2. BFF adds consult-api upstream.
3. Each frontend gets a BFF "profile" (which upstreams, which aggregated endpoints).

---

## 9. Risks & Open Questions

| ID | Question | Status |
|----|----------|--------|
| Q1 | New deployment pipeline needed? | Open — BFF is a new service |
| Q2 | Which framework? Node.js or Laravel? | Open — depends on team |
| Q3 | How to handle BFF-specific auth? | Open — JWT pass-through vs token exchange |
| Q4 | Monitoring and observability? | Open — needs logging, tracing |
| Q5 | Cost vs benefit for 2 tenant apps? | Open — thin proxy may suffice longer than expected |

---

## 10. Change Log

| Version | Date | Change |
|---------|------|--------|
| 0.1 Draft | 2026-08-29 | Initial speculative spec — dedicated BFF for multi-tenant frontends |
