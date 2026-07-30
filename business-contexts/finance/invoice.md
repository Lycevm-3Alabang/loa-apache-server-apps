# Invoice
## Entity Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Business Context
**Business Context:** Finance
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Invoice entity represents a financial request for payment within the Finance Business Context.

It captures the amount owed, payment terms, line items, and payment status.

The Invoice answers:

> **"How much does the customer owe and when is it due?"**

---

# 2. Responsibilities

The Invoice entity is responsible for:

- tracking amounts owed
- managing payment terms
- recording line items
- tracking payment status
- calculating totals
- applying taxes

---

# 3. What the Invoice Entity Owns

Examples include:

- Invoice Number
- Invoice Date
- Due Date
- Total Amount
- Tax Amount
- Line Items
- Payment Status
- Payment Terms

These concepts belong exclusively to the Invoice entity.

---

# 4. What the Invoice Entity Does NOT Own

The Invoice entity does not own:

- Customer data (CRM Context)
- Quotation data (Commercial Context)
- Work order data (Workshop Context)
- Tax rules (Tax Domain)

Those belong to other components.

---

# 5. Ownership

The Invoice entity owns:

- invoice state
- line items
- totals
- payment status
- validation

---

# 6. Business Rules

Examples include:

- Every invoice has a unique invoice number.
- Every invoice belongs to one customer (by CustomerId).
- Invoice total is calculated from line items plus taxes.
- Payment status tracks partial and full payments.
- Overdue invoices are flagged after due date.
- Credit notes reduce invoice balance.

---

# 7. Lifecycle

Typical lifecycle:

```
Draft

↓

Issued

↓

Partially Paid

↓

Paid

        ├── Overdue
        └── Cancelled
```

---

# 8. Public Contracts

The Invoice entity should expose stable contracts for:

- creating invoices
- adding line items
- issuing invoices
- recording payments
- generating credit notes
- retrieving invoice status

---

# 9. Anti-Patterns

The following are architectural violations.

## Customer Ownership

```
Invoice

manages Customer data
```

Customer data belongs to the CRM Context.

---

## Quotation Ownership

```
Invoice

creates Quotations
```

Quotations belong to the Commercial Context.

---

## Tax Ownership

```

Invoice

calculates Tax
```

Tax calculation belongs to the Tax Domain.

---

# 10. Guiding Principle

The Invoice entity is the canonical representation of a financial request for payment.

It owns:

- invoice state
- line items
- totals
- payment status

It references, but never owns:

- customers
- quotations
- tax rules
