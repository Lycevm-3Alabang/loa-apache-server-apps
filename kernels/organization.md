# organization.md

# Automotive Business Platform
## Platform Kernel Specification – Organization

**Version:** 1.0  
**Status:** Approved  
**Layer:** Platform Kernel  
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Organization Kernel establishes the canonical representation of business organizations within the Automotive Business Platform.

It answers a single architectural question:

> **Which legal or operational entity owns, operates, or is responsible for this data?**

The Organization Kernel enables the platform to support:

- Single workshops
- Multi-branch businesses
- Franchise networks
- Enterprise groups
- SaaS multi-tenancy

without requiring changes to Business Contexts.

---

# 2. Scope

The Organization Kernel is responsible for:

- Organizations
- Business units
- Branches
- Operational hierarchy
- Tenant boundaries
- Ownership boundaries

The Organization Kernel is **not** responsible for:

- Employees
- Customers
- Suppliers
- Authentication
- Permissions
- Business workflows

---

# 3. Responsibilities

The Organization Kernel owns the organizational structure of the platform.

It defines:

- who owns data
- where work occurs
- organizational hierarchy
- tenant isolation
- branch relationships

Business Contexts consume these concepts but never redefine them.

---

# 4. Owns

The Organization Kernel owns the following aggregates.

## Organization

Represents a legal business entity.

Examples

- ABC Automotive Inc.
- XYZ Fleet Services
- QuickFix Auto Group

---

## Business Unit

Represents a logical division within an organization.

Examples

- Retail Operations
- Fleet Services
- Parts Distribution

Business Units are optional.

---

## Branch

Represents a physical operating location.

Examples

- Makati Branch
- Cebu Branch
- Warehouse A

A Branch belongs to exactly one Organization.

---

## Tenant

Represents an isolated deployment boundary.

A Tenant may contain:

- one Organization
- multiple Organizations (future support)
- multiple Branches

The Tenant model supports both SaaS and self-hosted deployments.

---

# 5. Does Not Own

The Organization Kernel never owns:

- Customers
- Employees
- Suppliers
- Vehicles
- Inventory
- Quotations
- Job Orders
- Permissions
- Roles

Those belong elsewhere.

---

# 6. Relationships

Typical relationships include:

```
Tenant
    │
    ▼
Organization
    │
    ▼
Business Unit
    │
    ▼
Branch
```

Business Contexts reference these entities by identifier only.

Example

```
OrganizationId

BranchId

BusinessUnitId
```

---

# 7. Public Contracts

The Organization Kernel exposes contracts such as:

```
CreateOrganization()

CreateBranch()

MoveBranch()

DeactivateBranch()

AssignBusinessUnit()

ResolveOrganization()

ResolveBranch()
```

Implementations are platform-specific.

Contracts remain stable.

---

# 8. Published Events

Examples

```
OrganizationCreated

OrganizationUpdated

BranchCreated

BranchClosed

BusinessUnitCreated

TenantProvisioned
```

These events may be consumed by higher architectural layers.

---

# 9. Dependencies

The Organization Kernel depends only on Platform Kernel concepts when necessary.

It must never depend on:

- Core Business Domains
- Platform Services
- Business Contexts

---

# 10. Data Ownership

The Organization Kernel owns:

- OrganizationId
- BranchId
- BusinessUnitId
- TenantId
- Organization Name
- Branch Name
- Organization Status
- Organizational Hierarchy

The Organization Kernel does **not** own:

- Employees
- Customer Records
- Supplier Records
- Inventory
- Financial Data
- Business Transactions

---

# 11. Multi-Tenant Model

Every business record should be attributable to an organizational boundary.

Typical ownership:

```
Tenant
    │
Organization
    │
Branch
    │
Business Context Data
```

Example

```
Quotation

OrganizationId

BranchId
```

The Commercial Context owns the Quotation.

The Organization Kernel owns the Organization and Branch.

---

# 12. Extension Points

Future organizational capabilities may include:

- Regional structures
- Franchise ownership
- Subsidiaries
- Holding companies
- Shared service organizations
- Cross-organization reporting

These extensions should preserve the existing ownership model.

---

# 13. Architectural Constraints

The Organization Kernel must satisfy the following constraints.

1. Every Organization has a globally unique OrganizationId.
2. Every Branch belongs to one Organization.
3. Business Contexts reference organizations but never redefine them.
4. Organizational hierarchy is independent of business workflows.
5. Tenant boundaries must remain explicit.
6. Organizational ownership must be immutable for historical transactions.

---

# 14. Example Usage

Commercial Context

```
Quotation

OrganizationId

BranchId
```

Inventory Context

```
Warehouse

BranchId
```

Service Context

```
JobOrder

BranchId
```

The Business Context owns the transaction.

The Organization Kernel owns the organizational structure.

---

# 15. Guiding Principle

The Organization Kernel answers one question:

> **Which business entity owns or is responsible for this information?**

It does not answer:

- Who performed the work?
- Who is the customer?
- What business process is occurring?
- Who can access the data?

Those responsibilities belong to other architectural components.