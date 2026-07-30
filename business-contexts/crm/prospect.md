\# Prospect

\## Aggregate Specification



\*\*Version:\*\* 1.0

\*\*Status:\*\* Approved

\*\*Layer:\*\* Business Context

\*\*Business Context:\*\* CRM

\*\*Aggregate:\*\* Prospect

\*\*Audience:\*\* Architects, Engineers, AI Development Agents



\---



\# 1. Purpose



The Prospect aggregate represents a qualified potential customer who has demonstrated sufficient interest and meets the business's qualification criteria.



A Prospect is considered commercially viable and is actively nurtured toward a sales opportunity.



The Prospect aggregate answers:



> \*\*"Is this qualified customer worth pursuing?"\*\*



It does not own quotations, commercial transactions, pricing, or workshop operations.



\---



\# 2. Responsibilities



The Prospect aggregate is responsible for:



\- prospect qualification

\- prospect ownership

\- prospect lifecycle

\- prospect engagement

\- relationship development

\- opportunity readiness

\- prospect events



\---



\# 3. What the Prospect Aggregate Owns



Examples include:



\- Prospect

\- Prospect Number

\- Prospect Status

\- Qualification Result

\- Assigned Sales Representative

\- Expected Purchase Timeframe

\- Estimated Value

\- Probability of Conversion

\- Prospect Notes



These concepts belong exclusively to the Prospect aggregate.



\---



\# 4. What the Prospect Aggregate Does NOT Own



The Prospect aggregate does not own:



\- Customers

\- Opportunities

\- Quotations

\- Pricing

\- Vehicles

\- Services

\- Inventory

\- Payments



Those belong to Platform Kernels, Industry Domains, or other Business Contexts.



\---



\# 5. Ownership



The Prospect aggregate owns:



\- aggregate state

\- lifecycle

\- business validation

\- relationship progression

\- conversion rules

\- domain events



The Prospect aggregate is the consistency boundary for all prospect operations.



\---



\# 6. Aggregate Structure



```

Prospect

│

├── Qualification

├── Assigned Representative

├── Business Needs

├── Notes

└── Attachments

```



All child entities exist only within the lifetime of a Prospect.



\---



\# 7. Relationships



The Prospect aggregate references:



```

Party



Organization



Vehicle



Document

```



These references provide business context only.



Ownership remains with their respective Platform Kernels and Industry Domains.



\---



\# 8. Business Rules



Examples include:



\- Every prospect originates from a qualified lead.

\- Every prospect has an assigned owner.

\- A prospect may reference multiple vehicles of interest.

\- A prospect may contain multiple identified business needs.

\- A prospect may become an opportunity.

\- Converted prospects become read-only.

\- Closed prospects remain historically available.



\---



\# 9. Lifecycle



Typical lifecycle:



```

Qualified



↓



Engaged



↓



Nurturing



↓



Converted

&#x20;       │

&#x20;       ├── Lost

&#x20;       └── Closed

```



Converted prospects initiate the Opportunity lifecycle.



\---



\# 10. Domain Events



Examples include:



```

ProspectCreated



ProspectAssigned



ProspectUpdated



ProspectNurtured



ProspectConverted



ProspectClosed

```



\---



\# 11. Public Contracts



The Prospect aggregate should expose stable contracts for:



\- creating prospects

\- updating prospects

\- assigning ownership

\- recording engagement

\- converting prospects

\- closing prospects

\- retrieving prospects

\- publishing prospect events



\---



\# 12. Consumers



Prospect information may be consumed by:



\- CRM

\- Commercial

\- Marketing

\- Reporting



Consumers interact through published contracts.



\---



\# 13. Aggregate Invariants



The following invariants must always hold:



\- Every prospect originates from a qualified lead.

\- Every prospect has an owner.

\- Converted prospects cannot be converted again.

\- Lost prospects cannot become active.

\- Every lifecycle transition must be valid.

\- Historical engagement records are preserved.



These invariants are enforced by the aggregate root.



\---



\# 14. Anti-Patterns



The following are architectural violations.



\## Customer Ownership



```

Prospect



owns Customer

```



Customer belongs to the Party Kernel.



\---



\## Opportunity Ownership



```

Prospect



owns Opportunity

```



Opportunity is a separate aggregate.



\---



\## Commercial Ownership



```

Prospect



creates Quotations

```



Commercial owns quotations.



\---



\## Pricing Ownership



```

Prospect



defines Pricing Rules

```



Pricing belongs to the Pricing Domain.



\---



\# 15. Future Evolution



The Prospect aggregate may evolve to support:



\- AI qualification recommendations

\- buying intent scoring

\- engagement analytics

\- opportunity prediction

\- customer segmentation

\- automated nurturing

\- account-based selling

\- relationship health scoring



Future additions should continue to represent qualified sales prospects without assuming ownership of commercial transactions.



\---



\# 16. Guiding Principle



The Prospect aggregate is the canonical representation of a qualified potential customer.



It owns:



\- qualification outcome

\- relationship progression

\- engagement history

\- conversion readiness

\- prospect lifecycle



It references, but never owns:



\- customers

\- opportunities

\- quotations

\- pricing

\- commercial transactions



Those responsibilities belong to Platform Kernels, Industry Domains, and other Business Contexts.

