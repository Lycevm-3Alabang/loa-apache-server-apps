# CORS Service

## Platform Service Specification

**Version:** 1.0
**Status:** Draft
**Layer:** Platform Service
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The CORS Service provides reusable browser access-control capabilities for LOA API applications.

It answers one technical question:

> **Which origins may call LOA APIs from a browser?**

The CORS Service owns cross-origin access rules, preflight handling, and allowed-header/method policy. It does not own authentication, authorization, or business logic.

---

# 2. Responsibilities

The CORS Service is responsible for:

- cross-origin access control for LOA API subdomains
- preflight request handling
- allowed origins configuration
- allowed methods configuration
- allowed headers configuration
- credentialed request policy

---

# 3. What the CORS Service Owns

Examples include:

- Allowed Origin Policy
- Preflight Response Policy
- Allowed Methods Policy
- Allowed Headers Policy

These concepts belong exclusively to the CORS Service.

---

# 4. What the CORS Service Does NOT Own

The CORS Service does not own:

- Authentication
- Authorization
- Token validation
- Business Entities
- Business Workflows

Those belong to Platform Kernels or Business Contexts.

---

# 5. Ownership

The CORS Service owns:

- cross-origin policy
- preflight handling
- origin allowlist
- method allowlist
- header allowlist

---

# 6. Relationships

The CORS Service is consumed by:

- LOA Auth Platform (auth.loa.edu.ph)
- LOA Consult Platform (consult.loa.edu.ph)
- LOA Cert Platform (cert.loa.edu.ph)

It must never depend on:

- Industry Domains
- Business Contexts
- Product Assemblies

---

# 7. Business Rules

- Only configured LOA origins may call the API from a browser.
- Credentialed requests (cookies) are not used; Bearer tokens are passed in the `Authorization` header.
- Preflight responses are cached for at most 1 hour.
- The `Authorization`, `Content-Type`, and `Accept` headers are always permitted.
- Configuration is deployable per environment via environment variable.
- CORS applies to API paths only, never to public/static assets.
- CORS is not a security boundary. It only controls browser enforcement; server-side authorization is mandatory.

---

# 8. Allowed Origins

The default production origin allowlist:

```
https://auth.loa.edu.ph
https://consult.loa.edu.ph
https://cert.loa.edu.ph
```

Additional origins (e.g., local development, admin consoles) are configured per environment.

---

# 9. Anti-Patterns

The following are architectural violations.

## CORS as a Security Boundary

```
CORS Service

relies on browser enforcement for access control
```

CORS only manages browser behavior. Server-side authorization is always required.

---

## Wildcard Origins in Production

```
CORS Service

allows "*"
```

Production APIs must enumerate the allowed LOA origins.

---

## Credentials with Token Auth

```
CORS Service

supports_credentials = true
```

LOA uses Bearer tokens in headers, not cookies. Credentialed CORS is not needed.

---

## Authentication Logic

```
CORS Service

validates JWTs
```

Token validation belongs to the Identity Kernel.

---

# 10. Guiding Principle

The CORS Service answers one question:

> **Which origins may call LOA APIs from a browser?**

It does not determine who may call or what they may do.

Those responsibilities belong to the Identity Kernel and Business Contexts.
