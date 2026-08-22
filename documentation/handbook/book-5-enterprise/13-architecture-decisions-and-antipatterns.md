# Book 5 Chapter 13 — Architecture Decisions and Anti-Patterns

## 1. Framework capability is not architecture by itself

The fact that SPP provides middleware, events, LiveComponent, queues, AI, or multiple application contexts does not mean every application should use them all.

## 2. Decision questions

For a proposed feature ask:

```text
What problem does it solve?
Which boundary owns it?
What is the simplest SPP mechanism that solves it?
What happens when it fails?
What does it cost operationally?
How will it be tested?
```

## 3. Common anti-patterns

### Fat controller

Business rules, persistence, notification, and authorization all live in one controller.

**Better:** separate request coordination from application/domain behavior.

### Event abuse

Events hide synchronous dependencies that actually require a direct return value.

**Better:** use a direct service call when the caller depends on the result.

### Middleware abuse

Domain decisions are buried in request middleware.

**Better:** use middleware for request/cross-cutting concerns and services for business rules.

### Everything is reactive

Every button becomes a LiveComponent or browser signal without a real need.

**Better:** choose the simplest UI paradigm that satisfies the interaction requirement.

### Global mutable state

A shared singleton holds operation-specific mutable state.

**Better:** understand lifetime before sharing an object.

## 4. ADR lab

Write an architecture decision record for one Task Desk design choice, including:

- context;
- alternatives;
- chosen solution;
- consequences;
- evidence.

## Checkpoint

> **Good SPP architecture is selective: use framework capabilities because the problem calls for them, not because the framework can provide them.**
