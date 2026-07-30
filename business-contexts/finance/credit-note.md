# Credit Note
## Entity Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Business Context
**Business Context:** Finance
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Credit Note entity represents a reduction in the amount owed by a customer within the Finance Business Context.

It captures the reason for the credit, the amount, and which invoice(s) it applies to.

The Credit Note answers:

> **"Why is the customer's balance being reduced?"**

---

# 2. Responsibilities

The Credit Note entity is responsible for:

- recording credit amounts
- tracking credit reasons
- applying credits to invoices
- updating customer balances
- generating credit receipts

---

# 3. What the Credit Note Entity Owns

Examples include:

- Credit Note Number
- Credit Date
- Credit Amount
- Reason
- Applied Invoice
- Credit Status

These concepts belong exclusively to the Credit Note entity.

---

# 4. Business Rules

Examples include:

- Every credit note has a unique number.
- Credit notes reference one or more invoices.
- Credit amount cannot exceed invoice balance.
- Credit notes are recorded as negative amounts.
- Credit status tracks application.

---

# 5. Lifecycle

Typical lifecycle:

```
Draft

↓

Issued

↓

Applied

↓

Closed
```

---

# 6. Anti-Patterns

The following are architectural violations.

## Invoice Ownership

```
Credit Note

creates Invoices
```

Credit notes reduce existing invoice balances.

---

## Payment Ownership

```
Credit Note

processes Payments
```

Payments belong to the Payment entity.

---

# 7. Guiding Principle

The Credit Note entity is the canonical representation of a balance reduction.

It owns:

- credit state
- credit amount
- reason

It references, but never owns:

- invoices
- payments
