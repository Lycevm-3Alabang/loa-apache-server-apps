# Certificate Template
## Aggregate Specification

**Version:** 1.0
**Status:** Draft
**Layer:** Business Context
**Business Context:** Certificate
**Aggregate:** Certificate Template
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Certificate Template aggregate defines the visual layout and content of certificates.

It owns template structure, placeholder variables, HTML/CSS content, and template types.

The Certificate Template aggregate answers:

> **"How should a certificate look?"**

It does not own certificates, events, users, or PDF rendering.

---

# 2. Responsibilities

The Certificate Template aggregate is responsible for:

- template creation
- template management
- placeholder variable definition
- HTML/CSS content storage
- template type classification
- canvas dimension control
- template events

---

# 3. What the Certificate Template Aggregate Owns

Examples include:

- Certificate Template
- Template Name
- Template Description
- Template Type (certificate, email, auth)
- HTML Content
- CSS Content
- Auth Process
- Canvas Width
- Canvas Height
- Created At
- Updated At

The Certificate Template aggregate owns these concepts completely.

---

# 4. What the Certificate Template Aggregate Does NOT Own

The Certificate Template aggregate does not own:

- Users
- Organizations
- Certificates
- Events
- Event Attendees
- PDF Rendering
- QR Code Generation
- Email Delivery

These belong to Platform Kernels, other aggregates, or Platform Services.

---

# 5. Ownership

The Certificate Template aggregate owns:

- aggregate state
- business invariants
- structure
- validation
- domain events

The Certificate Template aggregate is the consistency boundary for template management.

---

# 6. Aggregate Structure

```
Certificate Template
│
├── HTML Content
├── CSS Content
├── Placeholder Variables
├── Canvas Dimensions
└── Template Type
```

---

# 7. Supported Placeholder Variables

Templates support the following placeholders:

```
{{recipient_name}}       - Name of the certificate recipient
{{certificate_number}}   - Unique certificate number
{{issued_date}}          - Date of issuance
{{event_name}}           - Name of the event
{{event_date}}           - Date of the event
{{event_location}}       - Location of the event
{{organization_name}}    - Name of the organization
{{qr_code}}              - QR code image (rendered at generation time)
```

---

# 8. Template Types

| Type | Purpose |
|------|---------|
| certificate | Visual layout for the certificate PDF |
| email | HTML content for notification emails |
| auth | HTML content for authentication flows (login, register, reset) |

---

# 9. Business Rules

Examples include:

- Every template belongs to an organization.
- Template names are unique within an organization.
- Template type must be one of: certificate, email, auth.
- Auth process templates are unique per process.
- HTML content is required for certificate templates.
- Canvas dimensions default to 1123x794 (landscape A4).
- Templates can be updated without affecting issued certificates.
- Deleted templates do not affect existing certificates.

---

# 10. Lifecycle

Typical lifecycle:

```
Draft

↓

Active

↓

Archived
```

---

# 11. Domain Events

Examples include:

```
TemplateCreated

TemplateUpdated

TemplateDeleted

TemplateArchived
```

---

# 12. Public Contracts

The Certificate Template aggregate should expose stable contracts for:

- creating templates
- updating templates
- deleting templates
- retrieving templates
- validating template structure
- rendering template with data

---

# 13. Anti-Patterns

The following are architectural violations.

## Certificate Ownership

```
Template

issues Certificates
```

Certificate issuance belongs to the Certificate aggregate.

---

## PDF Ownership

```

Template

defines PDF rendering engine
```

PDF rendering is a Platform Service. Templates define content only.

---

## Event Ownership

```

Template

manages Events
```

Event management belongs to the Event aggregate.

---

# 14. Guiding Principle

The Certificate Template aggregate is the canonical representation of certificate visual design.

It defines:

- how certificates look
- what placeholders are available
- what content appears on certificates
- what email content is sent

It does not define:

- what certificates are issued
- what events exist
- how PDFs are rendered
- how emails are delivered

Those responsibilities remain with other aggregates, Domains, and Platform Services.
