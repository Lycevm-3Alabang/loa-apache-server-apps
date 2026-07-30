\# Work Order

\## Aggregate Specification



\*\*Version:\*\* 1.0

\*\*Status:\*\* Approved

\*\*Layer:\*\* Business Context

\*\*Business Context:\*\* Workshop

\*\*Aggregate:\*\* Work Order

\*\*Audience:\*\* Architects, Engineers, AI Development Agents



\---



\# 1. Purpose



The Work Order aggregate represents the authorization and coordination of work to be performed on a vehicle or asset.



It is the central aggregate of the Workshop Business Context and owns the operational execution of approved work.



The Work Order aggregate answers:



> \*\*"What work should be performed, by whom, and what is its current operational state?"\*\*



It does not own quotations, vehicles, service definitions, inventory, or billing.



\---



\# 2. Responsibilities



The Work Order aggregate is responsible for:



\- work authorization

\- operational planning

\- technician coordination

\- work execution

\- operational progress

\- work completion

\- work history

\- workshop events



\---



\# 3. What the Work Order Aggregate Owns



Examples include:



\- Work Order

\- Work Order Number

\- Work Status

\- Planned Start

\- Planned Completion

\- Actual Start

\- Actual Completion

\- Priority

\- Operational Notes

\- Completion Summary



These concepts belong exclusively to the Work Order aggregate.



\---



\# 4. What the Work Order Aggregate Does NOT Own



The Work Order aggregate does not own:



\- Customers

\- Vehicles

\- Quotations

\- Services

\- Maintenance Plans

\- Inspection Definitions

\- Warranty Policies

\- Inventory

\- Invoices

\- Payments



Those belong to Platform Kernels, Industry Domains, or other Business Contexts.



\---



\# 5. Ownership



The Work Order aggregate owns:



\- aggregate state

\- operational lifecycle

\- business validation

\- work progress

\- technician assignments

\- domain events



The Work Order aggregate is the consistency boundary for operational execution.



\---



\# 6. Aggregate Structure



```

Work Order

│

├── Job

├── Technician Assignment

├── Progress Updates

├── Notes

├── Attachments

└── Completion Record

```



All child entities exist only within the lifetime of a Work Order.



\---



\# 7. Relationships



The Work Order aggregate references:



```

Vehicle



Commercial



Service



Inspection



Maintenance



Warranty



Document



Scheduling

```



These references provide business context.



Ownership remains with their respective Platform Kernels, Industry Domains, and Business Contexts.



\---



\# 8. Business Rules



Examples include:



\- Every work order references one vehicle.

\- Every work order contains one or more jobs.

\- Every work order has an operational status.

\- Every work order has an assigned owner.

\- Work cannot begin until the work order is released.

\- Completed work orders become read-only.

\- Cancelled work orders cannot resume execution.



\---



\# 9. Lifecycle



Typical lifecycle:



```

Created



↓



Planned



↓



Released



↓



Assigned



↓



In Progress



↓



Completed

&#x20;       │

&#x20;       ├── Cancelled

&#x20;       └── Closed

```



\---



\# 10. Domain Events



Examples include:



```

WorkOrderCreated



WorkOrderReleased



TechnicianAssigned



WorkStarted



WorkPaused



WorkCompleted



WorkOrderClosed



WorkOrderCancelled

```



\---



\# 11. Public Contracts



The Work Order aggregate should expose stable contracts for:



\- creating work orders

\- releasing work orders

\- assigning technicians

\- updating progress

\- completing work

\- closing work orders

\- retrieving work orders

\- publishing work order events



\---



\# 12. Consumers



Work Order information may be consumed by:



\- Inventory

\- Accounting

\- Reporting

\- Customer Portal

\- Fleet

\- Warranty



Consumers interact through published contracts.



\---



\# 13. Aggregate Invariants



The following invariants must always hold:



\- Every work order references one vehicle.

\- Every work order has at least one job.

\- Every work order has an owner.

\- Completed work orders are immutable.

\- Closed work orders cannot be modified.

\- Every lifecycle transition must be valid.



These invariants are enforced by the aggregate root.



\---



\# 14. Anti-Patterns



The following are architectural violations.



\## Vehicle Ownership



```

Work Order



owns Vehicle

```



Vehicle belongs to the Vehicle Domain.



\---



\## Service Ownership



```

Work Order



defines Service

```



Service belongs to the Service Domain.



\---



\## Inventory Ownership



```

Work Order



owns Stock

```



Inventory belongs to the Inventory Business Context.



\---



\## Financial Ownership



```

Work Order



creates Invoice

```



Accounting owns invoicing.



\---



\# 15. Future Evolution



The Work Order aggregate may evolve to support:



\- multi-stage work orders

\- subcontracted work

\- digital job cards

\- technician mobile workflows

\- IoT diagnostics

\- predictive maintenance execution

\- remote approvals

\- AI-assisted work planning



Future additions should continue to represent operational execution without assuming ownership of commercial, inventory, or financial concepts.



\---



\# 16. Guiding Principle



The Work Order aggregate is the canonical representation of operational work.



It owns:



\- operational execution

\- work lifecycle

\- technician coordination

\- progress tracking

\- completion records



It references, but never owns:



\- vehicles

\- quotations

\- service definitions

\- inventory

\- invoices



Those responsibilities belong to Platform Kernels, Industry Domains, and other Business Contexts.

