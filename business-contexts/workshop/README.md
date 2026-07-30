\# Workshop Business Context

\## Business Context Specification



\*\*Version:\*\* 1.0

\*\*Status:\*\* Approved

\*\*Layer:\*\* Business Context

\*\*Business Capability:\*\* Workshop

\*\*Audience:\*\* Architects, Engineers, AI Development Agents



\---



\# 1. Purpose



The Workshop Business Context manages the planning, execution, monitoring, and completion of operational work.



It transforms approved commercial work into executable operational activities while coordinating technicians, resources, inspections, maintenance, and service delivery.



The Workshop Business Context answers:



> \*\*"How is approved work planned, performed, and completed?"\*\*



It does not own customers, quotations, pricing, inventory, or financial transactions.



\---



\# 2. Responsibilities



The Workshop Business Context is responsible for:



\- work order management

\- job planning

\- technician assignment

\- work execution

\- repair tracking

\- inspection execution

\- maintenance execution

\- warranty execution

\- workshop activities

\- operational events



\---



\# 3. What the Workshop Business Context Owns



Examples include:



\- Work Order

\- Job

\- Technician Assignment

\- Repair Activity

\- Service Visit

\- Workshop Schedule

\- Job Status

\- Work Progress

\- Completion Record



These concepts belong exclusively to the Workshop Business Context.



\---



\# 4. What the Workshop Business Context Does NOT Own



The Workshop Business Context does not own:



\- Customers

\- Organizations

\- Quotations

\- Vehicles

\- Service Definitions

\- Maintenance Definitions

\- Inspection Definitions

\- Warranty Policies

\- Inventory

\- Invoices

\- Payments



Those belong to Platform Kernels, Industry Domains, or other Business Contexts.



\---



\# 5. Ownership



The Workshop Business Context owns:



\- operational workflows

\- work execution

\- technician coordination

\- work progress

\- completion records

\- operational validation

\- domain events

\- public contracts



It references shared concepts without redefining them.



\---



\# 6. Core Aggregates



Primary aggregates include:



```

Work Order

```



Supporting aggregates include:



```

Job



Technician Assignment



Service Visit



Repair Activity

```



\---



\# 7. Relationships



The Workshop Business Context references:



```

Party



Vehicle



Service



Maintenance



Inspection



Warranty



Scheduling



Document



Workflow



Activity



Commercial

```



Workshop composes these concepts to execute approved work.



Ownership remains with their respective Platform Kernels, Industry Domains, and Business Contexts.



\---



\# 8. Business Rules



Examples include:



\- Every work order originates from an approved request.

\- Every work order references at least one vehicle.

\- Every work order contains one or more jobs.

\- Jobs may reference service definitions.

\- Jobs may require inspections.

\- Jobs may consume inventory.

\- Completed work orders become read-only.

\- Cancelled work orders cannot be resumed.



\---



\# 9. Lifecycle



Typical lifecycle:



```

Created



↓



Planned



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



WorkOrderPlanned



TechnicianAssigned



WorkStarted



WorkCompleted



WorkCancelled



WorkClosed

```



\---



\# 11. Public Contracts



The Workshop Business Context should expose stable contracts for:



\- creating work orders

\- assigning technicians

\- scheduling work

\- updating work progress

\- completing work

\- retrieving work history

\- publishing operational events



\---



\# 12. Consumers



Expected consumers include:



\- CRM

\- Commercial

\- Inventory

\- Accounting

\- Reporting

\- Customer Portal



The Workshop Business Context remains unaware of implementation details within these consumers.



\---



\# 13. Integrations



The Workshop Business Context composes information from:



```

Vehicle



Service



Inspection



Maintenance



Warranty



Scheduling



Commercial



&#x20;       │

&#x20;       ▼



Workshop



&#x20;       │

&#x20;       ▼



Operational Execution

```



Workshop never owns or duplicates these concepts.



\---



\# 14. Anti-Patterns



The following are architectural violations.



\## Customer Ownership



```

Workshop



owns Customer

```



Customer belongs to the Party Kernel.



\---



\## Vehicle Ownership



```

Workshop



defines Vehicle

```



Vehicle belongs to the Vehicle Domain.



\---



\## Service Ownership



```

Workshop



defines Service

```



Service belongs to the Service Domain.



\---



\## Inventory Ownership



```

Workshop



owns Stock

```



Inventory belongs to the Inventory Business Context.



\---



\## Financial Ownership



```

Workshop



creates Invoices

```



Accounting owns financial documents.



\---



\# 15. Future Evolution



The Workshop Business Context may evolve to support:



\- digital inspections

\- technician mobile applications

\- predictive maintenance

\- workshop capacity planning

\- AI job scheduling

\- remote diagnostics

\- IoT integration

\- technician performance analytics



Future additions should continue to represent operational execution rather than commercial or financial processes.



\---



\# 16. Guiding Principle



The Workshop Business Context is the canonical owner of operational work execution.



It defines:



\- how work is planned

\- how work is assigned

\- how work is executed

\- how work is completed

\- how operational progress is tracked



It does not define:



\- who the customer is

\- what services exist

\- how quotations are created

\- how pricing is calculated

\- how inventory is valued

\- how invoices are produced



Those responsibilities belong to Platform Kernels, Industry Domains, and other Business Contexts.

