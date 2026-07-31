# Department Domain

## Education Domain Specification

**Version:** 1.0
**Status:** Draft
**Layer:** Industry Domain
**Audience:** Architects, Engineers, AI Development Agents

---

## 1. Purpose

The Department Domain represents academic units and organizational structure within an educational institution.

It answers:

> **"Which academic unit does this belong to?"**

It does not own user authentication, consultation logic, evaluation, or certificate generation.

---

## 2. Responsibilities

### Owns

- Department definitions (academic units)
- Department hierarchy (parent-child)
- Course offerings within departments
- Department membership (users belonging to departments)

### Does Not Own

- User authentication (Identity Kernel)
- Consultation workflows
- Evaluation logic
- Certificate generation
- Any business-specific logic

---

## 3. Core Concepts

### Department

An academic unit within the institution.

**Attributes:**
- id (UUID)
- name (string, unique)
- code (string, unique)
- parent_id (UUID, nullable — for hierarchy)
- description (string, nullable)
- created_at
- updated_at

**Standard Departments:**
- College of Computer Studies (CCS)
- College of Education (COED)
- College of Business (CBA)
- College of Engineering (COE)
- Office of the Registrar (OAR)
- Office of Student Affairs (OSA)

**Invariants:**
- Name must be unique
- Code must be unique (short identifier)
- Parent must exist if provided
- No circular hierarchy
- Maximum depth: 3 levels

---

### Course

Academic offerings within a department.

**Attributes:**
- id (UUID)
- department_id (UUID, FK)
- code (string, unique)
- name (string)
- description (string, nullable)
- created_at
- updated_at

**Invariants:**
- Code must be unique
- Must belong to a department

---

### DepartmentMember

Users belonging to a department.

**Attributes:**
- user_id (UUID, FK)
- department_id (UUID, FK)
- role (string — e.g., "head", "member")
- created_at

**Invariants:**
- User can belong to multiple departments
- One role per department

---

## 4. Business Rules

### Department Hierarchy

1. A department can have one parent
2. A department can have many children
3. Maximum depth: 3 levels
4. No circular references

### Membership

1. A user can belong to multiple departments
2. Each membership has a role (head, member)
3. A department can have one head

---

## 5. Domain Events

- DepartmentCreated
- DepartmentUpdated
- DepartmentDeleted
- CourseCreated
- CourseUpdated
- DepartmentMemberAdded
- DepartmentMemberRemoved

---

## 6. Public Contracts

### DepartmentService

```
getDepartment(id) → Department
getDepartmentByCode(code) → Department
createDepartment(data) → Department
updateDepartment(id, data) → Department
deleteDepartment(id) → void
getHierarchy(departmentId) → Department[]
getMembers(departmentId) → User[]
addMember(departmentId, userId, role) → void
removeMember(departmentId, userId) → void
```

### CourseService

```
getCourse(id) → Course
getCourseByCode(code) → Course
getCoursesByDepartment(departmentId) → Course[]
createCourse(data) → Course
updateCourse(id, data) → Course
deleteCourse(id) → void
```

---

## 7. Anti-Patterns

### Business Logic in Department

```
Department Domain
  schedules appointments
```

Appointment scheduling belongs to Consultation Business Context.

### Direct Database Access

```
Consult App
  SELECT * FROM departments
```

Cross-app data access is via API only.

---

## 8. Guiding Principle

The Department Domain is the single source of truth for:

- **What** departments exist
- **Who** belongs to each department
- **How** departments relate to each other

It does not define:

- What users do after authentication
- Business workflows
- Domain-specific logic
