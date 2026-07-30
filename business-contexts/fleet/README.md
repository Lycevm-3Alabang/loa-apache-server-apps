# Fleet
## Business Context Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Business Context
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Fleet Business Context defines the canonical representation of fleet management within the Automotive Business Platform.

It owns fleet vehicles, fleet assignments, fleet maintenance, fleet tracking, and fleet operations.

The Fleet Business Context answers:

> **"How do we manage and track a fleet of vehicles?"**

---

# 2. Responsibilities

The Fleet Business Context is responsible for:

- fleet vehicles
- fleet assignments
- fleet maintenance scheduling
- fleet tracking
- fleet operations
- fleet reporting
- fleet lifecycle management

---

# 3. What the Fleet Business Context Owns

Examples include:

- Fleet Vehicle
- Fleet Assignment
- Fleet Maintenance Schedule
- Fleet Tracking Record
- Fleet Operation
- Fleet Report
- Fleet Configuration

These concepts belong exclusively to the Fleet Business Context.

---

# 4. What the Fleet Business Context Does NOT Own

The Fleet Business Context does not own:

- Individual vehicle specifications
- Work orders
- Invoices
- Customer data
- Parts inventory
- Pricing rules

Those belong to other Business Contexts or Domains.

---

# 5. Ownership

The Fleet Business Context owns:

- fleet vehicles
- assignments
- maintenance schedules
- tracking records
- operations
- validation
- business rules

---

# 6. Core Concepts

The primary aggregate is:

```
Fleet Vehicle
```

Supporting concepts may include:

```
Fleet Assignment

Fleet Maintenance Schedule

Fleet Tracking Record

Fleet Operation

Fleet Report
```

---

# 7. Relationships

The Fleet Business Context consumes information from:

```
Vehicle Domain → Vehicle specifications

Workshop Context → Work orders for fleet vehicles

Inventory Context → Parts for fleet maintenance

Commercial Context → Quotations for fleet services

Finance Context → Invoicing for fleet operations
```

---

# 8. Business Rules

Examples include:

- Every fleet vehicle has a unique identity.
- Fleet vehicles are assigned to specific operations.
- Maintenance schedules are based on mileage or time.
- Tracking records capture vehicle location and status.
- Fleet operations generate work orders.
- Fleet reports summarize operational metrics.

---

# 9. Lifecycle

Typical lifecycle:

```
Registered

↓

Assigned

↓

Active

↓

Maintenance Required

↓

Maintenance Completed

        ├── Decommissioned
        └── Reassigned
```

---

# 10. Domain Events

Examples include:

```
FleetVehicleRegistered

FleetVehicleAssigned

FleetVehicleTrackingUpdated

FleetMaintenanceScheduled

FleetMaintenanceCompleted

FleetVehicleDecommissioned
```

---

# 11. Public Contracts

The Fleet Business Context should expose stable contracts for:

- registering fleet vehicles
- assigning fleet vehicles
- tracking fleet vehicles
- scheduling maintenance
- generating fleet reports

---

# 12. Consumers

The Fleet Business Context information may be consumed by:

- Workshop
- Inventory
- Finance
- Reporting
- Management

Consumers interact through published contracts.

---

# 13. Anti-Patterns

The following are architectural violations.

## Workshop Ownership

```
Fleet

manages Work Orders
```

Work orders belong to the Workshop Business Context.

---

## Inventory Ownership

```
Fleet

manages Stock
```

Stock management belongs to the Inventory Business Context.

---

## Vehicle Ownership

```
Fleet

defines Vehicle specifications
```

Vehicle specifications belong to the Vehicle Domain.

---

## Finance Ownership

```
Fleet

creates Invoices
```

Invoicing belongs to the Finance Business Context.

---

# 14. Future Evolution

The Fleet Business Context may evolve to support:

- real-time GPS tracking
- fuel management
- driver management
- compliance tracking
- predictive maintenance
- fleet analytics
- telematics integration
- AI fleet optimization

---

# 15. Guiding Principle

The Fleet Business Context is the canonical source of truth for all fleet management operations.

It owns fleet data and fleet workflows.

It does not own vehicle specifications, work orders, invoicing, or inventory.

Those responsibilities belong to their respective Domains and Business Contexts.
