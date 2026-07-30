# Inventory
## Business Context Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Business Context
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Inventory Business Context defines the canonical representation of stock management within the Automotive Business Platform.

It owns parts inventory, stock levels, stock reservations, stock transfers, warehouse management, and inventory tracking.

The Inventory Business Context answers:

> **"How do we manage and track physical parts and stock?"**

---

# 2. Responsibilities

The Inventory Business Context is responsible for:

- parts inventory
- stock levels
- stock reservations
- stock transfers
- warehouse management
- inventory tracking
- stock adjustments
- supplier management

---

# 3. What the Inventory Business Context Owns

Examples include:

- Parts
- Stock Level
- Stock Reservation
- Stock Transfer
- Warehouse
- Inventory Adjustment
- Purchase Order
- Supplier

These concepts belong exclusively to the Inventory Business Context.

---

# 4. What the Inventory Business Context Does NOT Own

The Inventory Business Context does not own:

- Vehicles
- Services
- Quotations
- Work Orders
- Invoices
- Payments
- Customers
- Technicians

Those belong to other Business Contexts or Platform Kernels.

---

# 5. Ownership

The Inventory Business Context owns:

- parts
- stock levels
- reservations
- transfers
- warehouses
- validation
- business rules

---

# 6. Core Concepts

The primary aggregate is:

```
Stock Item
```

Supporting concepts may include:

```
Stock Level

Stock Reservation

Stock Transfer

Warehouse

Inventory Adjustment

Purchase Order

Supplier
```

---

# 7. Relationships

The Inventory Business Context consumes information from:

```
Workshop → Parts Consumption

Commercial → Parts Sales

Service → Parts Requirements

Quotation → Parts Requests
```

---

# 8. Business Rules

Examples include:

- Every part has a unique identity.
- Stock levels are tracked per warehouse.
- Reservations reduce available stock.
- Transfers move stock between warehouses.
- Adjustments must be authorized.
- Negative stock levels are not permitted.
- Suppliers must be validated before purchase.
- Minimum stock levels trigger reorder alerts.

---

# 9. Lifecycle

Typical lifecycle:

```
Ordered

↓

Received

↓

Available

↓

Reserved

↓

Dispatched

        ├── Returned
        └── Scrapped
```

---

# 10. Domain Events

Examples include:

```
StockReceived

StockReserved

StockDispatched

StockAdjusted

PurchaseOrderCreated

StockLevelLow

StockTransferred
```

---

# 11. Public Contracts

The Inventory Business Context should expose stable contracts for:

- querying stock levels
- reserving stock
- transferring stock
- adjusting stock
- creating purchase orders
- managing suppliers

---

# 12. Consumers

The Inventory Business Context information may be consumed by:

- Workshop
- Commercial
- Service
- Reporting
- Accounting

Consumers interact through published contracts.

---

# 13. Anti-Patterns

The following are architectural violations.

## Workshop Ownership

```
Inventory

manages Work Order parts
```

Work Order parts consumption belongs to the Workshop Business Context.

---

## Commercial Ownership

```
Inventory

determines selling prices
```

Pricing belongs to the Commercial Business Context.

---

## Finance Ownership

```
Inventory

creates Invoices
```

Invoicing belongs to the Finance Business Context.

---

## Customer Ownership

```
Inventory

manages Customer data
```

Customer data belongs to the CRM Business Context.

---

# 14. Future Evolution

The Inventory Business Context may evolve to support:

- barcode/RFID tracking
- automated reorder
- real-time stock updates
- multi-warehouse support
- batch tracking
- serial number management
- AI demand forecasting
- supplier relationship management

---

# 15. Guiding Principle

The Inventory Business Context is the canonical source of truth for all stock and parts management.

It owns inventory data and inventory workflows.

It does not own commercial decisions, service definitions, or financial transactions.

Those decisions belong to their respective Business Contexts.
