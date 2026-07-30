# Example: Education Platform

## Scenario

An education provider needs a platform to manage:
- Course catalog
- Student enrollments
- Instructor assignments
- Certificates for completed courses

The platform also needs CRM for student inquiries and invoicing for course fees.

---

## What Blocks Are Needed

```
┌─────────────────────────────────────────────────────────────┐
│                  ASSEMBLY: Education Platform                │
├─────────────────────────────────────────────────────────────┤
│   UI: Web App (courses, enrollments, certificates)          │
│   API: REST + GraphQL                                        │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│   BUSINESS CONTEXTS                                         │
│   ├── CRM Context ──────────────┐ (reused from automotive)  │
│   │   └── Customer, Lead        │                           │
│   ├── Commercial Context ─────┐ │ (reused — invoicing)      │
│   │   └── Quotation, Invoice  │ │                           │
│   └── Certificate Context ──┐ │ │ (new — education-specific)│
│       └── Certificate, Grade │ │ │                           │
│                              │ │ │                           │
├──────────────────────────────┼─┼─┼───────────────────────────┤
│                                                             │
│   DOMAINS (education pack)                                  │
│   ├── Course ───────────────┐                               │
│   │   └── Course, Module    │                               │
│   ├── Student ────────────┐ │                               │
│   │   └── Student, Enroll │ │                               │
│   └── Instructor ───────┐ │ │                               │
│       └── Instructor     │ │ │                               │
│                          │ │ │                               │
├──────────────────────────┼─┼─┼───────────────────────────────┤
│                                                             │
│   KERNELS (same as automotive)                              │
│   ├── Identity ─────────┐                                   │
│   │   └── User, Auth    │                                   │
│   ├── Party ──────────┐ │                                   │
│   │   └── Party       │ │                                   │
│   └── Organization ─┐ │ │                                   │
│       └── Tenant     │ │ │                                   │
│                      │ │ │                                   │
└──────────────────────┼─┼─┼───────────────────────────────────┘
                       ▼ ▼ ▼
```

---

## Package References

| Layer | Package | Purpose |
|---|---|---|
| Kernel | `Automotive.Kernel.Identity` | User authentication |
| Kernel | `Automotive.Kernel.Party` | Student/instructor management |
| Kernel | `Automotive.Kernel.Organization` | Multi-tenant support |
| Domain | `Education.Domain.Course` | Course catalog |
| Domain | `Education.Domain.Student` | Student data |
| Domain | `Education.Domain.Instructor` | Instructor data |
| Context | `Automotive.Context.CRM` | Student inquiries |
| Context | `Automotive.Context.Commercial` | Course invoicing |
| Context | `Education.Context.Certificate` | Certificate generation |

---

## New Domain Pack: Education

This example introduces a new Industry Pack: `domains/education/`

```
domains/
├── automotive/        ← existing
└── education/         ← NEW
    ├── course.md
    ├── student.md
    └── instructor.md
```

The Education domains define industry-specific concepts while reusing the same Kernels and Business Contexts as automotive.

---

## Architecture Diagram (Mermaid)

```mermaid
graph TB
    subgraph "Assembly: Education Platform"
        UI["UI: Web App"]
        API["API: REST + GraphQL"]
    end

    subgraph "Business Contexts"
        CRM["CRM Context<br/>Customer, Lead"]
        COMM["Commercial Context<br/>Invoice"]
        CERT["Certificate Context<br/>Certificate, Grade"]
    end

    subgraph "Domains: Education Pack"
        COURSE["Course Domain<br/>Course, Module"]
        STUDENT["Student Domain<br/>Student, Enroll"]
        INST["Instructor Domain<br/>Instructor"]
    end

    subgraph "Kernels"
        ID["Identity<br/>User, Auth"]
        PARTY["Party<br/>Party"]
        ORG["Organization<br/>Tenant"]
    end

    UI --> API
    API --> CRM
    API --> COMM
    API --> CERT
    CERT --> COURSE
    CERT --> STUDENT
    CRM --> PARTY
    CRM --> ORG
    COMM --> PARTY
    COMM --> ORG
    CERT --> PARTY
    CERT --> ORG
    API --> ID
    COURSE --> ORG
    STUDENT --> ORG
    INST --> ORG

    style ID fill:#4CAF50,color:#fff
    style PARTY fill:#4CAF50,color:#fff
    style ORG fill:#4CAF50,color:#fff
    style COURSE fill:#2196F3,color:#fff
    style STUDENT fill:#2196F3,color:#fff
    style INST fill:#2196F3,color:#fff
    style CRM fill:#FF9800,color:#fff
    style COMM fill:#FF9800,color:#fff
    style CERT fill:#FF9800,color:#fff
    style UI fill:#9C27B0,color:#fff
    style API fill:#9C27B0,color:#fff
```

---

## Data Flow

```mermaid
sequenceDiagram
    participant S as Student
    participant A as API
    participant I as Identity
    participant CRM as CRM Context
    participant C as Course Domain
    participant INST as Instructor Domain
    participant STU as Student Domain
    participant COMM as Commercial Context
    participant CERT as Certificate Context

    S->>A: Browse courses
    A->>I: Validate token
    I-->>A: Authenticated
    A->>C: Get courses
    C-->>A: Course list
    A-->>S: Display courses

    S->>A: Select course
    A->>CRM: Create inquiry
    CRM-->>A: Inquiry created
    A->>INST: Assign instructor
    INST-->>A: Instructor assigned
    A->>STU: Process enrollment
    STU-->>A: Enrollment confirmed
    A->>COMM: Generate invoice
    COMM-->>A: Invoice created
    A-->>S: Enrollment complete

    Note over S,CERT: Course completed

    S->>A: Complete course
    A->>CERT: Generate certificate
    CERT-->>A: Certificate ready
    A-->>S: Download certificate
```

---

## Guardrails

- Certificate references Student by PartyId (no join)
- Course invoicing uses same Commercial Context as automotive
- Education domains are independent of automotive domains
- Same Kernels work across industries
