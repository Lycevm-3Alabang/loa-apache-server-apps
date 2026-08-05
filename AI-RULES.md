# AI-RULES.md

# Automotive Business Platform
## AI Development Rules

**Version:** 1.0
**Status:** Approved
**Audience:** AI Coding Agents, Engineers

---

# ⛔ RULE 0: Specs Before Code — MANDATORY

**The AI agent MUST check for the spec before writing ANY application/implementation code.**

| Situation | Required Action |
|-----------|-----------------|
| No spec exists | Write the spec FIRST, or ask the user. Do NOT write implementation code. |
| Spec is Draft | Complete the spec FIRST. Do NOT write implementation code. |
| Spec is Final | Read it completely, then code exactly to it. |
| Concept owned elsewhere | Reference by contract/ID. Do NOT duplicate. |

**Violating this rule is a failure.** "I didn't see the spec" is not an excuse — searching for the spec is part of the task.

The spec is the source of truth. The code must match the spec, never the reverse.

---

## ⚠️ CRITICAL CLARIFICATION: Editing a Spec Is NOT "Code"

Rule 0 restricts **implementation code** (app code, migrations, routes, blade views, config, etc.). It does **NOT** restrict the user's ability to author or maintain specs.

**If the user explicitly asks to create, edit, complete, or promote a spec (e.g., change a Draft spec to Final), DO IT.** This is a spec-authoring action, not implementation.

- **Correct:** User says "update this Draft spec to Final" → edit the spec, mark it Final, save. Do NOT refuse.
- **Correct:** User says "update this spec to v2.0" → edit the version/status header. Do NOT refuse.
- **Correct:** User asks to implement a spec that is now Final → read the spec, then implement.
- **WRONG:** User asks to finalize a Draft spec, and the agent refuses citing Rule 0. **Refusing to edit a spec the user asked to edit is a failure.** Rule 0 forbids *coding against* a Draft spec — it never forbids the user from finalizing the spec.

**Determining intent:** If the user's request is about the spec *document itself* (status, version, content, wording, promotion Draft→Final), treat it as spec authorship and comply. If the request is about *implementing application code* for that spec, then the Draft/Final gate applies.

**Never use Rule 0 to hard-block a request the user made explicitly.** When in doubt, ask — do not refuse.

---


# 1. Naming Conventions

## General Rules

- Use PascalCase for class names, interface names, and public properties.
- Use camelCase for local variables and private fields.
- Use kebab-case for file names in markdown documentation.
- Use SCREAMING_SNAKE_CASE for constants.

## Domain Entities

- Use singular nouns for entity names: `Vehicle`, `WorkOrder`, `StockItem`.
- Use plural nouns for collection names: `vehicles`, `workOrders`.
- Prefix interfaces with `I`: `IRepository`, `IService`.

## Events

- Use past tense for event names: `VehicleCreated`, `WorkOrderCompleted`.
- Events describe what happened, not what should happen.

## Files

- One class per file.
- File name matches class name.
- Markdown files use kebab-case: `repair-activity.md`.

---

# 2. Folder Structure

## Architecture Layers

```
application-template/
├── kernels/                    # Platform Kernels
├── domains/                    # Industry Domains
│   └── automotive/             # Automotive Domain Pack
├── business-contexts/          # Business Contexts
├── assemblies/                 # Product Assemblies
├── services/                   # Platform Services
├── AI-GUIDE.md                 # Architecture guide
├── AI-RULES.md                 # This file
└── README.md                   # Project overview
```

## Within Each Layer

Each business context, domain, or kernel should follow:

```
component/
├── README.md                   # Specification
├── entities/                   # Entity definitions
├── events/                     # Domain events
├── contracts/                  # Public contracts
└── rules/                      # Business rules
```

---

# 3. Dependency Injection Rules

## Constructor Injection

- Use constructor injection for all dependencies.
- Never use service locator pattern.
- Never use static methods for business logic.

```csharp
public class VehicleService
{
    private readonly IVehicleRepository _repository;

    public VehicleService(IVehicleRepository repository)
    {
        _repository = repository;
    }
}
```

## Dependency Direction

See `dependency-rules.md` for the full dependency matrix.

**Quick rule:** Dependencies always point downward. Higher layers consume lower layers. Lower layers never depend on higher layers.

```
Assemblies → Business Contexts → Domains → Services → Kernels
```

## Interface Segregation

- Prefer small, focused interfaces.
- Never expose operations a consumer doesn't need.
- Split large interfaces into smaller ones.

---

# 4. MediatR/CQRS Usage

## When to Adopt

MediatR and CQRS patterns are recommended for:

- Request/Response operations
- Command/Query separation
- Event-driven architectures
- Decoupling handlers from controllers

## Command Pattern

```csharp
public record CreateWorkOrderCommand(
    Guid VehicleId,
    string Description
) : IRequest<Guid>;
```

## Query Pattern

```csharp
public record GetWorkOrderQuery(
    Guid WorkOrderId
) : IRequest<WorkOrderDto>;
```

## Handler Pattern

```csharp
public class CreateWorkOrderHandler
    : IRequestHandler<CreateWorkOrderCommand, Guid>
{
    public async Task<Guid> Handle(
        CreateWorkOrderCommand request,
        CancellationToken cancellationToken)
    {
        // Implementation
    }
}
```

## When NOT to Use

- Simple CRUD operations
- Direct database access
- Utility functions

---

# 5. Entity Design Guidelines

## Entity Rules

- Every entity has a unique identity.
- Entities are mutable.
- Entities encapsulate business rules.
- Entities do not expose public setters.

## Value Object Rules

- Value objects are immutable.
- Value objects have no identity.
- Value objects are compared by value.
- Value objects can be nested.

## Aggregate Rules

- Aggregates enforce consistency boundaries.
- Aggregates reference other aggregates by identity only.
- Aggregates publish events on state changes.
- One entity is the aggregate root.

## Entity Example

```csharp
public class Vehicle
{
    public Guid Id { get; private set; }
    public string Make { get; private set; }
    public string Model { get; private set; }
    public int Year { get; private set; }

    private Vehicle() { }

    public static Vehicle Create(string make, string model, int year)
    {
        return new Vehicle
        {
            Id = Guid.NewGuid(),
            Make = make,
            Model = model,
            Year = year
        };
    }
}
```

---

# 6. Event Naming Conventions

## Event Names

- Use past tense: `Created`, `Updated`, `Deleted`, `Completed`.
- Events describe what happened.
- Events are immutable.
- Events contain all necessary data.

## Event Structure

```csharp
public record WorkOrderCompletedEvent(
    Guid WorkOrderId,
    Guid VehicleId,
    DateTime CompletedAt
);
```

## Event Rules

- One event per significant state change.
- Events are named in past tense.
- Events contain enough data for consumers.
- Events are serialized to JSON.

---

# 7. Testing Expectations

## Unit Tests

- Test one behavior per test.
- Use descriptive test names.
- Arrange/Act/Assert pattern.
- Mock external dependencies.

```csharp
[Fact]
public void CreateWorkOrder_ShouldReturnValidId()
{
    // Arrange
    var vehicleId = Guid.NewGuid();

    // Act
    var workOrder = WorkOrder.Create(vehicleId, "Oil change");

    // Assert
    Assert.NotEqual(Guid.Empty, workOrder.Id);
}
```

## Integration Tests

- Test complete workflows.
- Use real databases where possible.
- Test event publishing.
- Test contract compliance.

## Architecture Tests

- Verify dependency direction.
- Verify layer boundaries.
- Verify naming conventions.

---

# 8. Error Handling

## Exception Types

- Use domain-specific exceptions.
- Never throw generic exceptions.
- Include meaningful error messages.

```csharp
public class WorkOrderNotFoundException
    : Exception
{
    public WorkOrderNotFoundException(Guid id)
        : base($"Work order {id} not found")
    {
    }
}
```

## Result Pattern

Consider using Result pattern for operations that can fail:

```csharp
public record Result<T>(
    bool IsSuccess,
    T? Value,
    string? Error
);
```

## Error Handling Rules

- Log all exceptions.
- Never swallow exceptions silently.
- Return meaningful error messages to consumers.
- Use structured logging.

---

# 9. Logging

## Log Levels

- `Trace`: Detailed diagnostic information.
- `Debug`: Debugging information.
- `Information`: General information.
- `Warning`: Potentially harmful situations.
- `Error`: Error events.
- `Critical`: Fatal events.

## Structured Logging

```csharp
_logger.LogInformation(
    "Work order {WorkOrderId} created for vehicle {VehicleId}",
    workOrder.Id,
    vehicleId);
```

## What to Log

- Business events
- Error conditions
- Performance metrics
- Security events

## What NOT to Log

- Passwords
- Credit card numbers
- Personal identification numbers
- Sensitive business data

---

# 10. API Design

## RESTful Conventions

- Use nouns for resources.
- Use HTTP verbs for operations.
- Return appropriate status codes.

## Status Codes

- `200 OK`: Successful operation.
- `201 Created`: Resource created.
- `204 No Content`: Successful deletion.
- `400 Bad Request`: Invalid input.
- `401 Unauthorized`: Authentication required.
- `403 Forbidden`: Insufficient permissions.
- `404 Not Found`: Resource not found.
- `500 Internal Server Error`: Server error.

## API Versioning

- Version APIs in URL: `/api/v1/vehicles`.
- Support multiple versions during transition.
- Deprecate old versions gracefully.

---

# 11. Database Migration Practices

## Migration Rules

- One migration per change.
- Migrations must be reversible.
- Test migrations before applying.
- Back up data before production migrations.

## Naming Conventions

- Use descriptive migration names.
- Include timestamp: `20240101_AddVehicleTable`.
- One change per migration.

## Data Migrations

- Separate schema changes from data changes.
- Test data migrations thoroughly.
- Have rollback plan.

## Production Migrations

- Schedule downtime for migrations.
- Notify stakeholders.
- Monitor after migration.

---

# 12. Architecture Validation

## Rules to Validate

1. Dependencies point downward.
2. No circular dependencies.
3. Entities have unique identities.
4. Events use past tense.
5. Business logic stays in correct layer.
6. Assemblies contain no business logic.

## Validation Tools

- Architecture tests
- Static analysis
- Code reviews
- AI-assisted validation

---

# 13. AI Agent Guidelines

## ⛔ Mandatory Spec Check

Before generating or modifying **implementation code**:

1. Search `kernels/`, `domains/`, `business-contexts/`, `services/`, `assemblies/` for the relevant spec
2. Read the ENTIRE spec
3. If the spec is missing or Draft → STOP. Do not write implementation code. Write/complete the spec first, or ask the user.
4. Only then write code that matches the spec

**Exception — the user asked you to edit the spec itself.** If the user explicitly requests a spec change (create, complete, or promote Draft → Final), that request is the instruction. Comply with the spec edit. This rule only gates *implementation code*, never spec authorship the user requested. Finalizing a Draft spec is exactly the action that unblocks later implementation.

## ⛔ No Auto-Pilot — Always Ask

**The AI agent MUST NOT act autonomously. Every significant action requires explicit user confirmation.**

This is a strict behavioral rule, not a suggestion. Violations are treated as failures.

### What Requires User Confirmation

Before taking ANY of the following actions, the AI agent MUST ask and receive an explicit "yes" or specific instruction from the user:

- Writing, modifying, or deleting code files
- Creating, modifying, or deleting spec files
- Running database migrations
- Running Docker commands (up, down, rebuild, exec)
- Installing or updating packages (composer, npm)
- Updating PROJECT.md, PROJECT_UPDATES.md, or any tracker file
- Making architectural decisions (framework upgrades, library swaps)
- Running tests
- Committing or pushing changes
- Any action that changes the state of the repository or running services

### The Rule

**No auto-piloting. No assumption-based action. No "I'll just do this real quick."**

If unsure whether an action requires confirmation → **ask anyway**.

If the user says "do X" and you think Y is also needed → **ask about Y, don't just do it**.

If you've already started and realize you should have asked → **stop, report what you did, ask for confirmation on remaining work**.

## When Generating Code

1. **Check the spec exists and is Final before writing code**
2. Check existing code patterns first.
3. Follow naming conventions.
4. Respect layer boundaries.
5. Never duplicate business logic.
6. Use existing entities before creating new ones.

## When Modifying Code

1. **Re-read the spec before changing code**
2. Understand existing architecture.
3. Preserve layer boundaries.
4. Update related documentation.
5. Add tests for new behavior.
6. Verify dependency direction.

## When Reviewing Code

1. **Verify the code matches the spec**
2. Check layer compliance.
3. Verify naming conventions.
4. Validate dependency direction.
5. Ensure test coverage.
6. Review documentation.

---

# 14. Documentation Requirements

## Required Documentation

- Entity specifications
- Event definitions
- API contracts
- Business rules
- Architecture decisions

## Documentation Standards

- Use markdown format.
- Follow existing templates.
- Keep documentation current.
- Include examples.

---

# 15. Guiding Principle

These rules exist to ensure architectural integrity.

AI agents should follow these rules to maintain consistency.

When uncertain, preserve the architecture over convenience.

The architecture is the source of truth.
