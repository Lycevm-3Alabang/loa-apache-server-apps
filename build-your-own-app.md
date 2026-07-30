# Build Your Own App

## How to Use This Template

This template is a reference map. It tells you **where things belong** and **what depends on what**.

Follow these steps to assemble your own application.

---

## Step 1: Identify Your Industry

Look at `domains/` to find your industry pack.

| Industry | Directory | Domains |
|---|---|---|
| Automotive | `domains/automotive/` | Vehicle, Pricing, Tax, Labor, etc. |
| Education | `domains/education/` | Course, Student, Instructor |
| Events | `domains/events/` | Event, Attendee, Certificate |

Don't see your industry? Create a new pack: `domains/{your-industry}/`

---

## Step 2: Identify Your Business Contexts

Look at `business-contexts/` to find the capabilities you need.

| Context | What It Does |
|---|---|
| CRM | Customer lifecycle (leads, customers, communications) |
| Commercial | Quotation lifecycle (quotes, approvals, discounts) |
| Workshop | Work orders (jobs, repairs, scheduling) |
| Fleet | Fleet management (vehicles, assignments) |
| Inventory | Stock management (parts, reservations) |
| Procurement | Purchasing (purchase orders, vendors) |
| Finance | Invoicing (invoices, payments) |
| Certificate | Certificate generation (new — add as needed) |

Don't see what you need? Create a new context: `business-contexts/{your-context}/`

---

## Step 3: Check Dependencies

Before composing, verify dependency rules in `dependency-rules.md`.

**Key rules:**
- Contexts cannot reference other Contexts
- Contexts can reference Domains and Kernels
- Domains can reference Kernels only
- Kernels cannot reference anything above them

**For each context you selected, check what it needs:**

```
CRM Context needs:
├── Party Kernel ✓
├── Organization Kernel ✓
└── Identity Kernel ✓

Commercial Context needs:
├── CRM Context ← VIOLATION! Contexts can't reference each other
├── Vehicle Domain ✓
├── Pricing Domain ✓
└── Tax Domain ✓
```

If a context needs another context, you must:
1. Use events for cross-context communication, OR
2. Split the context differently

---

## Step 4: Assemble Your App

Create an Assembly that declares which blocks to include.

**Example: Automotive Quotation App**

```
assemblies/automotive-quotation-app/
├── README.md
├── blocks-included.md
└── dependency-graph.md
```

**blocks-included.md:**
```markdown
# Blocks Included

## Kernels
- Identity
- Party
- Organization

## Domains
- Vehicle
- Pricing
- Tax

## Contexts
- CRM
- Commercial
```

**dependency-graph.md:**
```markdown
# Dependency Graph

CRM → Party, Organization, Identity
Commercial → Vehicle, Pricing, Tax, Party, Organization
```

---

## Step 5: Verify No Violations

Before finalizing, verify:

| Check | Status |
|---|---|
| No circular dependencies | ✅ |
| No context-to-context references | ✅ |
| All kernel dependencies satisfied | ✅ |
| All domain dependencies satisfied | ✅ |
| No upward dependencies | ✅ |

---

## Example: Build an Education Platform

**Scenario:** Online learning platform with courses, students, and certificates.

### Step 1: Industry → `domains/education/`
- Course Domain
- Student Domain
- Instructor Domain

### Step 2: Business Contexts
- CRM (reuse — student inquiries)
- Commercial (reuse — course invoicing)
- Certificate (new — education-specific)

### Step 3: Dependencies
```
CRM → Party, Organization, Identity
Commercial → Party, Organization
Certificate → Party, Organization, Document
```
All valid. No context-to-context references.

### Step 4: Assembly
```
assemblies/education-platform/
├── Kernels: Identity, Party, Organization, Document
├── Domains: Course, Student, Instructor
└── Contexts: CRM, Commercial, Certificate
```

### Step 5: Verification
All checks pass. Ready to build.

---

## Quick Reference: Where to Find What

| You Need | Look In |
|---|---|
| Authentication | `kernels/identity.md` |
| Customer/supplier model | `kernels/party.md` |
| Multi-tenant support | `kernels/organization.md` |
| Vehicle data | `domains/automotive/vehicle.md` |
| Pricing rules | `domains/automotive/pricing.md` |
| Customer management | `business-contexts/crm/` |
| Quotation creation | `business-contexts/commercial/` |
| Work order management | `business-contexts/workshop/` |
| Dependency rules | `dependency-rules.md` |
| Architecture overview | `AI-GUIDE.md` |

---

## Guardrails

| Rule | What It Prevents |
|---|---|
| Contexts don't reference other Contexts | Circular business logic |
| Domains don't reference Contexts | Upward dependencies |
| Kernels don't reference anything | Unstable foundation |
| Every concept has one owner | Duplicate data models |
| Cross-context via events only | Tight coupling |
| Reference by ID, not join | Database coupling |

---

## Further Reading

- `AI-GUIDE.md` — Full architecture guide
- `dependency-rules.md` — Dependency matrix
- `glossary.md` — Architectural terms
- `principles.md` — Design principles
- `examples/` — Worked examples with diagrams
