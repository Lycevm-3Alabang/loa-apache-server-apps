\# Opportunity

\## Aggregate Specification



\*\*Version:\*\* 1.0

\*\*Status:\*\* Approved

\*\*Layer:\*\* Business Context

\*\*Business Context:\*\* CRM

\*\*Aggregate:\*\* Opportunity

\*\*Audience:\*\* Architects, Engineers, AI Development Agents



\---



\# 1. Purpose



The Opportunity aggregate represents a qualified sales opportunity that has the potential to become a commercial transaction.



It is the final stage of the CRM Business Context before responsibility transitions to the Commercial Business Context.



The Opportunity aggregate answers:



> \*\*"What business opportunity are we actively pursuing?"\*\*



It does not own quotations, approvals, pricing, or commercial negotiations.



\---



\# 2. Responsibilities



The Opportunity aggregate is responsible for:



\- opportunity management

\- opportunity qualification

\- opportunity ownership

\- sales pipeline progression

\- probability assessment

\- revenue forecasting

\- opportunity lifecycle

\- opportunity events



\---



\# 3. What the Opportunity Aggregate Owns



Examples include:



\- Opportunity

\- Opportunity Number

\- Opportunity Stage

\- Sales Pipeline

\- Probability of Success

\- Estimated Revenue

\- Expected Close Date

\- Assigned Sales Representative

\- Opportunity Notes



The Opportunity aggregate owns these concepts completely.



\---



\# 4. What the Opportunity Aggregate Does NOT Own



The Opportunity aggregate does not own:



\- Customers

\- Quotations

\- Pricing Rules

\- Discounts

\- Vehicles

\- Services

\- Work Orders

\- Inventory

\- Invoices

\- Payments



These belong to Platform Kernels, Industry Domains, or other Business Contexts.



\---



\# 5. Ownership



The Opportunity aggregate owns:



\- aggregate state

\- lifecycle

\- pipeline progression

\- forecasting

\- business validation

\- domain events



The Opportunity aggregate is the consistency boundary for all opportunity operations.



\---



\# 6. Aggregate Structure



```

Opportunity

│

├── Sales Forecast

├── Probability

├── Business Needs

├── Notes

└── Attachments

```



All child entities exist only within the lifetime of an Opportunity.



\---



\# 7. Relationships



The Opportunity aggregate references:



```

Party



Organization



Vehicle



Document



Prospect

```



These references provide business context only.



Ownership remains with their respective Platform Kernels and Industry Domains.



\---



\# 8. Business Rules



Examples include:



\- Every opportunity originates from a prospect.

\- Every opportunity has an assigned owner.

\- Every opportunity belongs to one sales pipeline.

\- Probability must remain between 0% and 100%.

\- Opportunities may reference multiple vehicles.

\- Opportunities may generate one or more quotations.

\- Won opportunities initiate Commercial activities.

\- Lost opportunities remain available for reporting and analysis.



\---



\# 9. Lifecycle



Typical lifecycle:



```

Open



↓



Qualified



↓



Proposal



↓



Negotiation



↓



Won

&#x20;       │

&#x20;       ├── Lost

&#x20;       ├── Cancelled

&#x20;       └── Expired

```



Winning an opportunity may initiate quotation creation within the Commercial Business Context.



\---



\# 10. Domain Events



Examples include:



```

OpportunityCreated



OpportunityQualified



OpportunityUpdated



OpportunityAdvanced



OpportunityWon



OpportunityLost



OpportunityClosed

```



\---



\# 11. Public Contracts



The Opportunity aggregate should expose stable contracts for:



\- creating opportunities

\- updating opportunities

\- progressing opportunity stages

\- forecasting revenue

\- closing opportunities

\- retrieving opportunities

\- publishing opportunity events



\---



\# 12. Consumers



Opportunity information may be consumed by:



\- Commercial

\- Reporting

\- Marketing

\- Executive Dashboards



Consumers interact through published contracts.



\---



\# 13. Aggregate Invariants



The following invariants must always hold:



\- Every opportunity originates from a prospect.

\- Every opportunity has an owner.

\- Probability is always between 0% and 100%.

\- Won opportunities cannot return to an active sales stage.

\- Lost opportunities cannot become won.

\- Every lifecycle transition must be valid.



These invariants are enforced by the aggregate root.



\---



\# 14. Anti-Patterns



The following are architectural violations.



\## Customer Ownership



```

Opportunity



owns Customer

```



Customer belongs to the Party Kernel.



\---



\## Commercial Ownership



```

Opportunity



owns Quotation

```



Quotation belongs to the Commercial Business Context.



\---



\## Pricing Ownership



```

Opportunity



defines Pricing Rules

```



Pricing belongs to the Pricing Domain.



\---



\## Workshop Ownership



```

Opportunity



creates Work Orders

```



Workshop owns operational execution.



\---



\# 15. Future Evolution



The Opportunity aggregate may evolve to support:



\- AI opportunity scoring

\- predictive revenue forecasting

\- competitive analysis

\- opportunity collaboration

\- account planning

\- buying committee management

\- strategic opportunity tracking

\- win/loss analysis



Future additions should continue to represent sales opportunities without assuming ownership of commercial transactions.



\---



\# 16. Guiding Principle



The Opportunity aggregate is the canonical representation of a qualified sales opportunity.



It owns:



\- sales progression

\- opportunity forecasting

\- pipeline stage

\- probability

\- opportunity lifecycle



It references, but never owns:



\- customers

\- quotations

\- pricing

\- commercial negotiations

\- operational execution



Those responsibilities belong to Platform Kernels, Industry Domains, and other Business Contexts.

