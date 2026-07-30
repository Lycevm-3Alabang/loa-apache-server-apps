\# Service Visit

\## Aggregate Specification



\*\*Version:\*\* 1.0

\*\*Status:\*\* Approved

\*\*Layer:\*\* Business Context

\*\*Business Context:\*\* Workshop

\*\*Aggregate:\*\* Service Visit

\*\*Audience:\*\* Architects, Engineers, AI Development Agents



\---



\# 1. Purpose



The Service Visit aggregate represents a customer's physical or operational visit to a workshop for inspection, maintenance, repair, or other services.



It captures the arrival, reception, assessment, and completion of a workshop visit.



The Service Visit aggregate answers:



> \*\*"What happened during this workshop visit?"\*\*



It does not own work execution, vehicles, customers, pricing, or inventory.



\---



\# 2. Responsibilities



The Service Visit aggregate is responsible for:



\- workshop check-in

\- vehicle reception

\- visit tracking

\- customer requests

\- initial assessment

\- visit status

\- visit history

\- service visit events



\---



\# 3. What the Service Visit Aggregate Owns



Examples include:



\- Service Visit

\- Visit Number

\- Visit Date

\- Visit Type

\- Visit Status

\- Arrival Time

\- Reception Notes

\- Customer Concerns

\- Initial Assessment

\- Completion Summary



These concepts belong exclusively to the Service Visit aggregate.



\---



\# 4. What the Service Visit Aggregate Does NOT Own



The Service Visit aggregate does not own:



\- Customer

\- Vehicle

\- Work Order

\- Job

\- Service Definition

\- Maintenance Plan

\- Inspection Definition

\- Inventory

\- Invoice

\- Payment



Those belong to Platform Kernels, Industry Domains, or other Business Contexts.



\---



\# 5. Ownership



The Service Visit aggregate owns:



\- visit lifecycle

\- reception process

\- customer requests

\- initial assessment

\- visit history

\- business validation

\- domain events



The Service Visit aggregate is the consistency boundary for workshop visits.



\---



\# 6. Aggregate Structure



```

Service Visit

│

├── Reception

├── Customer Request

├── Initial Assessment

├── Vehicle Condition

├── Notes

└── References

```



All child entities exist only within the lifetime of a Service Visit.



\---



\# 7. Relationships



The Service Visit aggregate references:



```

Party



Vehicle



Work Order



Inspection



Document



Activity

```



These references provide business context.



Ownership remains with their respective Platform Kernels, Domains, and Business Contexts.



\---



\# 8. Business Rules



Examples include:



\- Every service visit references one vehicle.

\- Every service visit has a visit date.

\- Every service visit has a status.

\- A service visit may create one or more work orders.

\- Vehicle condition should be recorded before work begins.

\- Completed service visits become historical records.

\- Cancelled visits cannot proceed to execution.



\---



\# 9. Lifecycle



Typical lifecycle:



```

Scheduled



↓



Arrived



↓



Checked In



↓



Assessment



↓



Work Started



↓



Completed

&#x20;       │

&#x20;       ├── Cancelled

&#x20;       └── No Show

```



\---



\# 10. Domain Events



Examples include:



```

ServiceVisitCreated



VehicleCheckedIn



AssessmentStarted



AssessmentCompleted



ServiceVisitCompleted



ServiceVisitCancelled

```



\---



\# 11. Public Contracts



The Service Visit aggregate should expose stable contracts for:



\- creating service visits

\- checking in vehicles

\- recording customer concerns

\- recording assessments

\- linking work orders

\- completing visits

\- publishing visit events



\---



\# 12. Consumers



Service Visit information may be consumed by:



\- Workshop

\- CRM

\- Customer Portal

\- Reporting

\- Warranty



Consumers interact through published contracts.



\---



\# 13. Aggregate Invariants



The following invariants must always hold:



\- Every service visit has a vehicle reference.

\- Every service visit has a valid status.

\- Completed visits cannot be reopened.

\- Visit history is preserved.

\- Every lifecycle transition must be valid.



These invariants are enforced by the aggregate root.



\---



\# 14. Anti-Patterns



\## Customer Ownership



```

Service Visit



owns Customer

```



Customer belongs to the Party Kernel.



\---



\## Vehicle Ownership



```

Service Visit



owns Vehicle

```



Vehicle belongs to the Vehicle Domain.



\---



\## Work Execution Ownership



```

Service Visit



performs Repairs

```



Work execution belongs to Work Order and Job.



\---



\## Financial Ownership



```

Service Visit



creates Invoice

```



Accounting owns financial documents.



\---



\# 15. Future Evolution



The Service Visit aggregate may evolve to support:



\- appointment check-in

\- customer waiting management

\- digital reception

\- vehicle inspection photos

\- customer approvals

\- mobile service visits

\- remote diagnostics

\- self-service check-in



Future additions should continue to represent the workshop visit experience without assuming ownership of operational execution or financial processing.



\---



\# 16. Guiding Principle



The Service Visit aggregate is the canonical representation of a workshop visit.



It owns:



\- arrival

\- reception

\- customer requests

\- initial assessment

\- visit history



It references, but never owns:



\- customers

\- vehicles

\- work orders

\- services

\- inventory

\- invoices



Those responsibilities belong to Platform Kernels, Industry Domains, and other Business Contexts.

