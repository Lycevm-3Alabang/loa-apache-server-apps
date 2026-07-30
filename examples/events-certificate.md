# Example: Events Certificate API

## Scenario

An events company needs an API-only service that:
- Receives attendee data from external event management systems
- Generates certificates for event attendees
- Returns PDF certificates via API

No UI needed. External systems authenticate and call the API.

---

## What Blocks Are Needed

```
┌─────────────────────────────────────────────────────────────┐
│                ASSEMBLY: Events Certificate API              │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│   No UI — API only                                          │
│                                                             │
│   External App ──token──► Your API ──PDF──► External App    │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│   BUSINESS CONTEXTS                                         │
│   └── Certificate Context ──────────────────────────────────┐
│       └── Certificate, Template, Recipient                  │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│   DOMAINS (events pack)                                     │
│   └── Event ────────────────────────────────────────────────┐
│       └── Event, Attendee, Schedule                         │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│   SERVICES                                                  │
│   └── PDF Service ──────────────────────────────────────────┐
│       └── PDF generation, templates                         │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│   KERNELS                                                   │
│   ├── Identity ──────── (accepts external tokens) ──────────┤
│   └── Document ──────── (certificate = document) ──────────┤
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Package References

| Layer | Package | Purpose |
|---|---|---|
| Kernel | `Automotive.Kernel.Identity` | External token validation |
| Kernel | `Automotive.Kernel.Document` | Certificate as document |
| Domain | `Events.Domain.Event` | Event data |
| Context | `Events.Context.Certificate` | Certificate generation |
| Service | `Automotive.Service.PDF` | PDF rendering |

---

## Architecture Diagram (Mermaid)

```mermaid
graph TB
    subgraph "Assembly: Events Certificate API"
        API["API: Backend"]
    end

    subgraph "Business Contexts"
        CERT["Certificate Context<br/>Certificate, Template"]
    end

    subgraph "Domains: Events Pack"
        EVENT["Event Domain<br/>Event, Attendee"]
    end

    subgraph "Services"
        PDF["PDF Service<br/>PDF Generation"]
    end

    subgraph "Kernels"
        ID["Identity<br/>External Token Auth"]
        DOC["Document<br/>Certificate = Document"]
    end

    EXT["External App"] -->|"Bearer Token"| API
    API --> CERT
    CERT --> EVENT
    CERT --> PDF
    CERT --> DOC
    API --> ID

    style ID fill:#4CAF50,color:#fff
    style DOC fill:#4CAF50,color:#fff
    style EVENT fill:#2196F3,color:#fff
    style PDF fill:#607D8B,color:#fff
    style CERT fill:#FF9800,color:#fff
    style API fill:#9C27B0,color:#fff
    style EXT fill:#E91E63,color:#fff
```

---

## Auth Flow: External Authentication

```mermaid
sequenceDiagram
    participant EXT as External App
    participant A as API
    participant I as Identity Kernel
    participant CERT as Certificate Context
    participant PDF as PDF Service

    EXT->>A: POST /certificates<br/>Authorization: Bearer {token}
    A->>I: Validate external token
    I->>I: Check token validity
    alt Token valid
        I-->>A: Authenticated
        A->>CERT: Generate certificate
        CERT->>PDF: Render PDF
        PDF-->>CERT: PDF bytes
        CERT-->>A: Certificate created
        A-->>EXT: 200 { certificateId, pdfUrl }
    else Token invalid
        I-->>A: Unauthorized
        A-->>EXT: 401 Unauthorized
    end
```

---

## API Endpoints

```
POST /certificates
  Headers: Authorization: Bearer {external-token}
  Body: { eventId, attendeeName, courseName, completionDate }
  Returns: { certificateId, pdfUrl }

GET /certificates/{id}
  Headers: Authorization: Bearer {external-token}
  Returns: { certificateId, pdfUrl, issuedDate }

GET /certificates/{id}/pdf
  Headers: Authorization: Bearer {external-token}
  Returns: application/pdf
```

---

## Minimal Block Set

This is the simplest possible assembly:

| Block | Why Needed |
|---|---|
| Identity | External auth validation |
| Document | Certificate is a document |
| Event | Event/attendee data |
| Certificate | Certificate generation logic |
| PDF Service | PDF rendering |

No CRM. No Commercial. No invoicing. Just what's needed.

---

## Guardrails

- Identity kernel accepts external tokens (configured for this tenant)
- Certificate Context doesn't know about the external auth system
- PDF Service is called by Certificate Context, not by external app
- No database needed for external app's data (just your certificate records)
