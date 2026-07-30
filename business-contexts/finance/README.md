# Finance
## Business Context Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Business Context
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Finance Business Context defines the canonical representation of financial operations within the Automotive Business Platform.

It owns invoicing, payments, accounts receivable, accounts payable, financial reporting, reconciliation, and financial workflow management.

The Finance Business Context answers:

> **"How do we manage money and financial obligations?"**

---

# 2. Responsibilities

The Finance Business Context is responsible for:

- invoicing
- payments
- accounts receivable
- accounts payable
- financial reporting
- reconciliation
- financial workflows
- tax integration

---

# 3. What the Finance Business Context Owns

Examples include:

- Invoice
- Payment
- Account Receivable
- Account Payable
- Financial Report
- Reconciliation
- Credit Note
- Debit Note

These concepts belong exclusively to the Finance Business Context.

---

# 4. What the Finance Business Context Does NOT Own

The Finance Business Context does not own:

- Quotations
- Products
- Services
- Customers
- Suppliers
- Inventory
- Work Orders
- Vehicles
- Tax Rules

Those belong to other Business Contexts, Domains, or Platform Kernels.

---

# 5. Ownership

The Finance Business Context owns:

- invoices
- payments
- accounts
- reports
- workflows
- validation
- business rules

---

# 6. Core Concepts

The primary aggregate is:

```
Invoice
```

Supporting concepts may include:

```
Payment

Account Receivable

Account Payable

Financial Report

Credit Note

Debit Note
```

---

# 7. Relationships

The Finance Business Context consumes information from:

```
Commercial → Quotations

Workshop → Work Orders

Inventory → Parts Sales

Service → Service Fees

Tax → Tax Rules

Customer → Customer Data
```

---

# 8. Business Rules

Examples include:

- Every invoice has a unique identity.
- Every invoice belongs to one customer.
- Payments are recorded against invoices.
- Invoice amounts must reconcile with payment amounts.
- Credit notes reduce invoice balances.
- Financial reports reflect point-in-time snapshots.
- Tax amounts are calculated and applied to invoices.
- Payment methods must be validated.

---

# 9. Lifecycle

Typical lifecycle:

```
Draft

↓

Pending Approval

↓

Approved

↓

Invoiced

↓

Paid

        ├── Partially Paid
        └── Overdue
```

---

# 10. Domain Events

Examples include:

```
InvoiceCreated

InvoiceApproved

InvoiceSent

PaymentReceived

PaymentApplied

CreditNoteIssued

AccountReceivableAging
```

---

# 11. Public Contracts

The Finance Business Context should expose stable contracts for:

- creating invoices
- applying payments
- generating financial reports
- managing accounts
- retrieving invoice status

---

# 12. Consumers

The Finance Business Context information may be consumed by:

- Reporting
- Accounting
- Management
- Tax Authorities
- Auditors

Consumers interact through published contracts.

---

# 13. Anti-Patterns

The following are architectural violations.

## Quotation Ownership

```
Finance

creates Quotations
```

Quotations belong to the Commercial Business Context.

---

## Customer Ownership

```
Finance

manages Customer profiles
```

Customer data belongs to the CRM Business Context.

---

## Inventory Ownership

```
Finance

tracks Stock
```

Stock management belongs to the Inventory Business Context.

---

## Service Ownership

```
Finance

defines Services
```

Service definitions belong to the Service Domain.

---

# 14. Future Evolution

The Finance Business Context may evolve to support:

- automated payment processing
- digital invoicing
- real-time reporting
- multi-currency support
- installment plans
- credit management
- financial forecasting
- AI-assisted reconciliation

---

# 15. Guiding Principle

The Finance Business Context is the canonical source of truth for all financial operations.

It owns financial data and financial workflows.

It does not own commercial decisions, inventory decisions, or service definitions.

Those decisions belong to their respective Business Contexts.
