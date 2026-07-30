# Reporting Service

## Platform Service Specification

**Version:** 1.0
**Status:** Approved
**Layer:** Platform Service
**Audience:** Architects, Engineers, AI Development Agents

---

# 1. Purpose

The Reporting Service provides reusable capabilities for querying, aggregating, and presenting operational information.

It answers one technical question:

> **How do we present operational information?**

The Reporting Service owns report generation, data aggregation, and presentation formats. It does not own business entities or determine what information should be reported.

---

# 2. Responsibilities

The Reporting Service is responsible for:

- report generation
- data aggregation
- query execution
- report formatting
- report scheduling
- report delivery
- report templates
- reporting events

---

# 3. What the Reporting Service Owns

Examples include:

- Report Definition
- Report Template
- Report Schedule
- Report Result
- Report Format
- Aggregation Rule

These concepts belong exclusively to the Reporting Service.

---

# 4. What the Reporting Service Does NOT Own

The Reporting Service does not own:

- Business Entities
- Business Workflows
- Business Rules
- Business Validation
- Business Transactions
- Business Calculations

Those belong to Platform Kernels, Industry Domains, or Business Contexts.

---

# 5. Ownership

The Reporting Service owns:

- report definitions
- report templates
- data aggregation
- report scheduling
- report formatting
- report delivery
- reporting events

---

# 6. Relationships

The Reporting Service may reference:

- Platform Kernels
- Industry Domains

It must never depend on:

- Business Contexts
- Product Assemblies

---

# 7. Business Rules

Examples include:

- Every report has a unique identifier.
- Report definitions are versioned.
- Scheduled reports execute according to policy.
- Report results are immutable.
- Report formats are configurable.
- Aggregation rules are defined per report.

---

# 8. Report Formats

The Reporting Service supports:

- Tabular Reports
- Summary Reports
- Dashboard Views
- Chart Visualizations
- Export Formats (PDF, CSV, Excel)
- Real-Time Dashboards

---

# 9. Anti-Patterns

The following are architectural violations.

## Business Logic

```
Reporting Service

calculates quotation totals
```

Business logic belongs to Business Contexts.

---

## Data Ownership

```
Reporting Service

owns business data
```

Business data is owned by Business Contexts and Industry Domains.

---

## Direct Dependency

```
Reporting Service

depends on Commercial
```

Platform Services must never depend on Business Contexts.

---

# 10. Guiding Principle

The Reporting Service answers one question:

> **How do we present operational information?**

It does not determine what information should be reported or what business decisions should be made.

Those responsibilities belong to Business Contexts.
