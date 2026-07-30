# Example: Automotive Quotation App

## Scenario

An automotive service and repair store needs a digital quotation system.

The store wants to:
- Create quotations for customers
- Look up customer history
- Reference vehicle information
- Apply pricing and taxes
- Get approvals before sending to customer

---

## What Blocks Are Needed

```
┌─────────────────────────────────────────────────────────────┐
│                   ASSEMBLY: Quotation MVP                   │
│                    (Deployable App)                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│   UI: React App                                             │
│   ├── QuotationForm                                         │
│   ├── CustomerSearch                                        │
│   └── VehicleLookup                                         │
│                                                             │
│   API: Backend Endpoints                                    │
│   ├── POST /quotations                                      │
│   ├── GET /customers                                        │
│   └── GET /vehicles                                         │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│   BUSINESS CONTEXTS (compose these)                         │
│   ├── Commercial Context ──────────────────────────────┐    │
│   │   └── Quotation, QuotationItem, Approval          │    │
│   └── CRM Context ──────────────────────────────────┐ │    │
│       └── Customer, Lead, Communication              │ │    │
│                                                     │ │    │
├─────────────────────────────────────────────────────┼─┼────┤
│                                                     │ │    │
│   DOMAINS (reference these)                         │ │    │
│   ├── Vehicle Domain ──────────────────────────┐   │ │    │
│   │   └── Vehicle, VehicleSpec                  │   │ │    │
│   ├── Pricing Domain ────────────────────────┐ │   │ │    │
│   │   └── Price, PriceRule                    │ │   │ │    │
│   └── Tax Domain ──────────────────────────┐ │ │   │ │    │
│       └── TaxRate, TaxRule                  │ │ │   │ │    │
│                                             │ │ │   │ │    │
├─────────────────────────────────────────────┼─┼─┼───┼─┼────┤
│                                             │ │ │   │ │    │
│   KERNELS (always included)                 │ │ │   │ │    │
│   ├── Identity ─────────────────────────┐   │ │ │   │ │    │
│   │   └── User, Auth                    │   │ │ │   │ │    │
│   ├── Party ─────────────────────────┐  │   │ │ │   │ │    │
│   │   └── Party, PartyRole           │  │   │ │ │   │ │    │
│   └── Organization ───────────────┐  │  │   │ │ │   │ │    │
│       └── Organization, Tenant    │  │  │   │ │ │   │ │    │
│                                   │  │  │   │ │ │   │ │    │
└───────────────────────────────────┼──┼──┼───┼─┼─┼───┼─┼────┘
                                    │  │  │   │ │ │   │ │
         ┌──────────────────────────┘  │  │   │ │ │   │ │
         │    ┌────────────────────────┘  │   │ │ │   │ │
         │    │    ┌──────────────────────┘   │ │ │   │ │
         │    │    │    ┌─────────────────────┘ │ │   │ │
         │    │    │    │    ┌──────────────────┘ │   │ │
         │    │    │    │    │    ┌───────────────┘   │ │
         │    │    │    │    │    │    ┌──────────────┘ │
         │    │    │    │    │    │    │    ┌───────────┘
         ▼    ▼    ▼    ▼    ▼    ▼    ▼    ▼
         Dependency Flow (Kernels → Domains → Contexts → Assembly)
```

---

## Package References

| Layer | Package | Purpose |
|---|---|---|
| Kernel | `Automotive.Kernel.Identity` | User authentication |
| Kernel | `Automotive.Kernel.Party` | Customer/supplier management |
| Kernel | `Automotive.Kernel.Organization` | Multi-tenant support |
| Domain | `Automotive.Domain.Vehicle` | Vehicle data |
| Domain | `Automotive.Domain.Pricing` | Price calculation |
| Domain | `Automotive.Domain.Tax` | Tax calculation |
| Context | `Automotive.Context.CRM` | Customer lifecycle |
| Context | `Automotive.Context.Commercial` | Quotation lifecycle |

---

## Architecture Diagram (Mermaid)

```mermaid
graph TB
    subgraph "Assembly: Quotation MVP"
        UI["UI: React App"]
        API["API: Backend"]
    end

    subgraph "Business Contexts"
        CRM["CRM Context<br/>Customer, Lead"]
        COMM["Commercial Context<br/>Quotation, Approval"]
    end

    subgraph "Domains"
        VEH["Vehicle Domain<br/>Vehicle, VehicleSpec"]
        PRICE["Pricing Domain<br/>Price, PriceRule"]
        TAX["Tax Domain<br/>TaxRate, TaxRule"]
    end

    subgraph "Kernels"
        ID["Identity<br/>User, Auth"]
        PARTY["Party<br/>Party, PartyRole"]
        ORG["Organization<br/>Tenant"]
    end

    UI --> API
    API --> CRM
    API --> COMM
    COMM --> VEH
    COMM --> PRICE
    COMM --> TAX
    CRM --> PARTY
    CRM --> ORG
    COMM --> PARTY
    COMM --> ORG
    API --> ID
    VEH --> ORG
    PRICE --> ORG
    TAX --> ORG

    style ID fill:#4CAF50,color:#fff
    style PARTY fill:#4CAF50,color:#fff
    style ORG fill:#4CAF50,color:#fff
    style VEH fill:#2196F3,color:#fff
    style PRICE fill:#2196F3,color:#fff
    style TAX fill:#2196F3,color:#fff
    style CRM fill:#FF9800,color:#fff
    style COMM fill:#FF9800,color:#fff
    style UI fill:#9C27B0,color:#fff
    style API fill:#9C27B0,color:#fff
```

---

## Data Flow

```mermaid
sequenceDiagram
    participant C as Customer
    participant A as API
    participant I as Identity
    participant CRM as CRM Context
    participant V as Vehicle Domain
    participant P as Pricing Domain
    participant T as Tax Domain
    participant COMM as Commercial Context

    C->>A: Request quote
    A->>I: Validate token
    I-->>A: Authenticated
    A->>CRM: Lookup customer
    CRM-->>A: Customer data
    A->>V: Lookup vehicle
    V-->>A: Vehicle data
    A->>COMM: Create quotation
    COMM->>P: Calculate prices
    P-->>COMM: Prices
    COMM->>T: Calculate taxes
    T-->>COMM: Taxes
    COMM-->>A: Quotation created
    A-->>C: Return quotation
```

---

## Guardrails

- Quotation references Customer by ID (no join to CRM tables)
- Quotation references Vehicle by ID (no join to Vehicle tables)
- Pricing rules are read-only for Commercial Context
- Tax rules are read-only for Commercial Context
- No circular dependencies between contexts
