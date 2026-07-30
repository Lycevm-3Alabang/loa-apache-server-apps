\# Activity

\## Aggregate Specification



\*\*Version:\*\* 1.0

\*\*Status:\*\* Approved

\*\*Layer:\*\* Business Context

\*\*Business Context:\*\* CRM

\*\*Aggregate:\*\* Activity

\*\*Audience:\*\* Architects, Engineers, AI Development Agents



\---



\# 1. Purpose



The Activity aggregate represents work planned or performed by the business to develop, maintain, or strengthen customer relationships.



Activities organize operational work such as calls, meetings, emails, demonstrations, site visits, and follow-up tasks.



The Activity aggregate answers:



> \*\*"What work needs to be done, or has been completed, for this customer relationship?"\*\*



It does not own customer interactions, communications, quotations, or workflow execution.



\---



\# 2. Responsibilities



The Activity aggregate is responsible for:



\- activity planning

\- activity scheduling

\- activity assignment

\- activity execution

\- activity completion

\- activity history

\- activity events



\---



\# 3. What the Activity Aggregate Owns



Examples include:



\- Activity

\- Activity Number

\- Activity Type

\- Activity Status

\- Priority

\- Assigned User

\- Due Date

\- Completion Date

\- Outcome

\- Internal Notes



These concepts belong exclusively to the Activity aggregate.



\---



\# 4. What the Activity Aggregate Does NOT Own



The Activity aggregate does not own:



\- Customers

\- Leads

\- Prospects

\- Opportunities

\- Communications

\- Customer Interactions

\- Quotations

\- Calendar Infrastructure



Those belong to Platform Kernels, Platform Services, or other Business Contexts.



\---



\# 5. Ownership



The Activity aggregate owns:



\- activity lifecycle

\- assignment

\- scheduling metadata

\- completion history

\- business validation

\- domain events



The Activity aggregate is the consistency boundary for all CRM activities.



\---



\# 6. Aggregate Structure



```

Activity

│

├── Assignment

├── Schedule

├── Outcome

├── Notes

└── References

```



All child entities exist only within the lifetime of an Activity.



\---



\# 7. Relationships



The Activity aggregate references:



```

Lead



Prospect



Opportunity



Party



Customer Interaction



Communication



Document

```



These references provide business context only.



Ownership remains with their respective Platform Kernels and Business Contexts.



\---



\# 8. Business Rules



Examples include:



\- Every activity has a responsible owner.

\- Every activity has a type.

\- Every activity has a status.

\- Completed activities cannot return to Pending.

\- Cancelled activities cannot be completed.

\- Activities may generate follow-up activities.



\---



\# 9. Lifecycle



Typical lifecycle:



```

Planned



↓



Assigned



↓



In Progress



↓



Completed

&#x20;       │

&#x20;       ├── Cancelled

&#x20;       └── Expired

```



Completed activities become part of the permanent customer history.



\---



\# 10. Domain Events



Examples include:



```

ActivityCreated



ActivityAssigned



ActivityStarted



ActivityCompleted



ActivityCancelled



ActivityExpired

```



\---



\# 11. Public Contracts



The Activity aggregate should expose stable contracts for:



\- creating activities

\- assigning activities

\- updating activity status

\- completing activities

\- retrieving activity history

\- publishing activity events



\---



\# 12. Consumers



Activity information may be consumed by:



\- CRM

\- Reporting

\- Executive Dashboards

\- Customer Portal



Consumers interact through published contracts.



\---



\# 13. Aggregate Invariants



The following invariants must always hold:



\- Every activity has an owner.

\- Every activity has a valid status.

\- Completed activities cannot be modified except through administrative correction.

\- Activity history is preserved.

\- Every lifecycle transition must be valid.



These invariants are enforced by the aggregate root.



\---



\# 14. Anti-Patterns



The following are architectural violations.



\## Customer Ownership



```

Activity



owns Customer

```



Customer belongs to the Party Kernel.



\---



\## Calendar Ownership



```

Activity



implements Calendar

```



Scheduling infrastructure belongs to Platform Services.



\---



\## Communication Ownership



```

Activity



stores Email Messages

```



Communications belong to the Communication aggregate.



\---



\## Commercial Ownership



```

Activity



creates Quotations

```



Commercial owns quotations.



\---



\# 15. Future Evolution



The Activity aggregate may evolve to support:



\- recurring activities

\- SLA monitoring

\- workload balancing

\- AI task recommendations

\- productivity analytics

\- mobile field activities

\- geo-location tracking

\- collaborative activities



Future additions should continue to represent CRM work without assuming ownership of scheduling infrastructure or commercial transactions.



\---



\# 16. Guiding Principle



The Activity aggregate is the canonical representation of CRM work performed by the business.



It owns:



\- planned work

\- assigned work

\- completed work

\- activity outcomes

\- activity history



It references, but never owns:



\- customers

\- communications

\- quotations

\- scheduling infrastructure



Those responsibilities belong to Platform Kernels, Platform Services, and other Business Contexts.

