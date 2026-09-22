# Mail Sending
## Platform Service Specification

**Version:** 1.0
**Status:** Final
**Layer:** Platform Service
**Audience:** Engineers, AI Development Agents

---

# 1. Purpose

The Mail Sending Service provides reusable outbound-email capabilities for the platform.

It answers:

> **"How do we send an email with optional attachments over SMTP?"**

It does not own recipients, templates, triggers, or send history. Those are business decisions owned by the calling context.

---

# 2. Responsibilities

The Mail Sending Service is responsible for:

- sending a single email to explicit recipients
- plain-text and HTML bodies
- file attachments (PDF, images) with MIME types
- surfacing SMTP success/failure per send
- working identically on local Mailpit and cPanel SMTP

---

# 3. What the Mail Sending Service Owns

- SMTP transport configuration shape (`MAIL_*` env contract)
- message assembly (to/subject/body/attachments → send)
- per-send outcome (sent vs error message)

The service owns these concepts completely.

---

# 4. What the Mail Sending Service Does NOT Own

- who receives mail and why (business decision — caller passes explicit recipients)
- template content and rendering (callers own Blade templates: auth `PasswordResetMail`, cert issuance mail)
- token issuance (auth `IdentityService`; cert issuance flow)
- send history/audit (callers own their logs: auth audit trail, cert `certificate_emails` with `status`/`error_message`)
- retries, queues, scheduling (caller or queue infrastructure; see cert `queue-infrastructure-spec.md`)

These belong to Business Contexts, assemblies, or other Platform Services.

---

# 5. Public Contracts

The service exposes a single operation:

```
send(to: string|string[], subject: string, body: Body, attachments: Attachment[]): Outcome
```

- `Body` = `{ text?: string, html?: string }` (at least one present).
- `Attachment` = `{ filename: string, mime: string, bytes: base64 }`.
- `Outcome` = `{ sent: true } | { sent: false, error: string }` — never throws for SMTP failure; the caller records it.

Current mirrored implementations (unchanged until this spec is Final):

- auth `PasswordResetNotificationService::sendForgotPasswordLink/sendChangePasswordLink` — token from `IdentityService`, then `Mail::to()->send(new PasswordResetMail)`.
- cert issuance mail + `CertificateEmail` send-log (`sent_to/subject/sent_at/sent_by/status/error_message`).

---

# 6. Business Rules

The service follows these rules:

- sending is synchronous by default; queuing is the caller's decision.
- every send returns an outcome; SMTP errors are returned, never swallowed.
- the service never reads a database and never decides recipients.
- secrets come from env only (`MAIL_*`); nothing secret is logged.

---

# 7. Implementation Constraints

The service MUST work on cPanel shared hosting:

- Laravel `Mail` facade + SMTP driver only (Mailpit locally, cPanel SMTP in prod).
- No external binaries or shell execution.
- No new Composer dependencies beyond what the assemblies already ship.

---

# 8. Anti-Patterns

The following are violations:

## Business Logic in Service

```
MailService
decides who gets a reset link
```

The service sends to explicit recipients only. Trigger logic belongs to the calling context.

---

## Database Access

```
MailService
queries CertificateEmail / users tables
```

The service is stateless. History lives with the caller (cert `certificate_emails`, auth audit).

---

# 9. Consumers

- LOA Auth Platform (password reset / change-password links).
- LOA Cert Platform (issuance mail with PDF attachment).
- LOA Consult Platform (future: evaluation reminders, consultation notifications — none today).

---

# 10. Guiding Principle

The Mail Sending Service is a pure delivery mechanism. It moves bytes to inboxes.

It does not know about passwords, certificates, or evaluations.

Those responsibilities belong to the calling contexts.
