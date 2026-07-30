# party.md

# Automotive Business Platform
## Platform Kernel Specification – Party

**Version:** 1.0
**Status:** Approved
**Layer:** Platform Kernel
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Party Kernel establishes the canonical representation of every person or organization that interacts with the Automotive Business Platform.

It answers one architectural question:

> **Who does the business interact with?**

The Party Kernel provides a unified identity for individuals and organizations while allowing Business Contexts to assign business-specific roles.

It is the authoritative source for all parties within the platform.

---

# 2. Scope

The Party Kernel is responsible for:

- People
- Organizations (as business parties)
- Contact information
- Communication methods
- Business relationships
- Party lifecycle

The Party Kernel is not responsible for:

- Authentication
- Authorization
- Quotations
- Job Orders
- Inventory
- Pricing
- Financial transactions

---

# 3. Responsibilities

The Party Kernel owns:

- Canonical party records
- Contact information
- Communication channels
- Party classifications
- Party relationships

Business Contexts extend parties through business roles rather than creating new entities.

---

# 4. Core Concepts

## Party

A Party represents any individual or organization with which the business interacts.

Every Party has exactly one PartyId.

---

## Person

Represents an individual.

Examples

- Customer
- Employee
- Technician
- Driver
- Sales Representative

---

## Organization Party

Represents an external organization.

Examples

- Fleet Company
- Supplier
- Insurance Company
- Manufacturer
- Dealer
- Government Agency
- Finance Company

---

## Contact

Represents communication information.

Examples

- Mobile Number
- Telephone
- Email
- Website

---

## Address

Represents physical or mailing locations.

Examples

- Home
- Billing
- Shipping
- Registered Office

---

## Party Relationship

Represents relationships between parties.

Examples

```
Customer

↓

Fleet Company
```

```
Supplier

↓

Manufacturer
```

```
Employee

↓

Organization
```

Relationships are reusable across Business Contexts.

---

# 5. Owns

The Party Kernel owns:

- Party
- Person
- Organization Party
- Contact
- Address
- Party Relationship

---

# 6. Does Not Own

The Party Kernel never owns:

- Customer Account
- CRM Opportunities
- Quotations
- Vehicles
- Employees
- Payroll
- Suppliers Catalog
- Inventory
- Job Orders
- User Accounts

Those belong to Business Contexts or other Platform Kernels.

---

# 7. Party Roles

Business roles are assigned by Business Contexts.

Examples include:

Commercial

```
Customer
```

Procurement

```
Supplier
```

Service

```
Vehicle Owner
```

Fleet

```
Fleet Operator
```

Finance

```
Billing Customer
```

A single Party may have multiple roles simultaneously.

Example

```
ABC Logistics

Customer

Supplier

Fleet Client
```

No duplicate Party should be created.

---

# 8. Public Contracts

Examples

```
CreateParty()

UpdateParty()

DeactivateParty()

MergeParty()

AddContact()

RemoveContact()

AddAddress()

ResolveParty()
```

---

# 9. Published Events

Examples

```
PartyCreated

PartyUpdated

PartyMerged

ContactAdded

AddressChanged

PartyDeactivated
```

---

# 10. Dependencies

The Party Kernel may reference:

- Identity (optional)
- Organization (optional)

Examples

```
Party

↓

Identity
```

Optional.

A Party may never log in.

Example

Walk-in Customer.

---

```
Party

↓

Organization
```

Optional.

Example

Supplier belongs to an Organization.

---

The Party Kernel must never depend on:

- Core Business Domains
- Platform Services
- Business Contexts

---

# 11. Data Ownership

The Party Kernel owns:

- PartyId
- Party Type
- Display Name
- Legal Name
- Contact Information
- Addresses
- Communication Preferences
- Party Status

The Party Kernel does not own:

- Credit Limit
- Customer Pricing
- Loyalty Points
- Fleet Contracts
- Purchase History
- Quotations
- Vehicle Ownership History

Those belong to Business Contexts.

---

# 12. Example Usage

Commercial

```
Quotation

CustomerId
```

CRM

```
Opportunity

PartyId
```

Procurement

```
PurchaseOrder

SupplierId
```

Service

```
Vehicle

OwnerPartyId
```

Every context references the same Party.

---

# 13. Architectural Constraints

The Party Kernel must satisfy the following constraints.

1. Every Party has exactly one PartyId.
2. Party records must never be duplicated.
3. Business roles are assigned outside the Party Kernel.
4. Contact information belongs to the Party Kernel.
5. Party relationships are reusable.
6. A Party may exist without an Identity.
7. Party records should be mergeable.
8. Historical references must remain valid.

---

# 14. Future Considerations

The Party Kernel should support:

- Multiple contacts
- Multiple addresses
- Preferred communication channels
- Customer groups
- Household relationships
- Corporate hierarchies
- External identifiers
- Master Data Management (MDM)

These capabilities should extend the Party Kernel without affecting Business Contexts.

---

# 15. Guiding Principle

The Party Kernel answers one question:

> **Who does the business interact with?**

It does not determine:

- What business role they currently have.
- What transactions they perform.
- Which Business Context uses them.

Business Contexts assign roles.

The Party Kernel owns the canonical representation.