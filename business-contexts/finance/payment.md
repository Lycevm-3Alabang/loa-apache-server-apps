# Payment
## Entity Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Business Context
**Business Context:** Finance
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Payment entity represents a financial transaction within the Finance Business Context.

It captures the amount paid, payment method, payment date, and which invoice(s) it applies to.

The Payment answers:

> **"How much was paid, when, and for which invoice?"**

---

# 2. Responsibilities

The Payment entity is responsible for:

- recording payments
- tracking payment methods
- applying payments to invoices
- calculating remaining balances
- generating payment receipts

---

# 3. What the Payment Entity Owns

Examples include:

- Payment Reference
- Payment Date
- Payment Amount
- Payment Method
- Applied Invoices
- Receipt Number

These concepts belong exclusively to the Payment entity.

---

# 4. What the Payment Entity Does NOT Own

The Payment entity does not own:

- Invoice data
- Customer data
- Bank account details
- Payment gateway configuration

Those belong to other components.

---

# 5. Business Rules

Examples include:

- Every payment has a unique reference.
- Payments are applied to one or more invoices.
- Partial payments reduce invoice balance.
- Overpayments create credit balance.
- Payment method must be validated.
- Receipt is generated after payment confirmation.

---

# 6. Lifecycle

Typical lifecycle:

```
Initiated

↓

Processed

↓

Confirmed

        ├── Failed
        └── Refunded
```

---

# 7. Anti-Patterns

The following are architectural violations.

## Invoice Ownership

```
Payment

creates Invoices
```

Invoices belong to the Finance Context.

---

## Customer Ownership

```
Payment

manages Customer data
```

Customer data belongs to the CRM Context.

---

# 8. Guiding Principle

The Payment entity is the canonical representation of a financial transaction.

It owns:

- payment state
- payment method
- applied invoices

It references, but never owns:

- invoices
- customers
