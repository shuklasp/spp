# 61 — SPP Learning Roadmap

## Purpose

This chapter defines the order in which to learn SPP without turning the handbook into a disconnected catalogue of features. The route moves from framework fundamentals to the SPP runtime, then to persistent applications, platform capabilities, reactive interfaces, integrations, and enterprise architecture.

The objective is **competence at architectural boundaries**, not completion of a list of APIs.

## The learning progression

```text
Framework basics
      ↓
SPP mental model
      ↓
Application + context + request lifecycle
      ↓
Middleware + events + DI + modules
      ↓
Pages + rendering + forms + validation
      ↓
Entities + SPPDB/XDB + cache + security
      ↓
Parikshak + API + workflow + jobs + reporting
      ↓
LiveComponent + SPP Live + SPPUX
      ↓
AI + storage/content promotion + external apps
      ↓
Multi-application + failure isolation + enterprise architecture
```

The sequence is deliberate: each stage introduces boundaries that later stages depend on.

## Stage 0 — Learn what a framework is

Start with [50 — Frameworks 101](50-frameworks-101-and-how-spp-builds-on-them.md).

Learn:

- what application frameworks solve;
- inversion of control and dependency injection;
- routing, middleware, events, rendering, persistence, and configuration as framework concerns;
- why an application framework is a runtime model rather than merely a collection of helper classes.

### Checkpoint

You should be able to explain the difference between:

- plain application code;
- an MVC framework;
- an event/middleware runtime;
- a broader application platform.

## Stage 1 — Build the SPP mental model

Read [65 — The SPP Mental Model](65-spp-mental-model.md) and [51 — Framework Concept to SPP Feature Map](51-framework-concept-to-spp-feature-map.md).

Trace a request conceptually as:

```text
request
  → application/context
  → middleware
  → route/page/API/live entry point
  → application behavior
  → services/data
  → response
```

Then learn the major alternatives rather than assuming there is one universal SPP path. [66 — Same Problem, Multiple SPP Solutions](66-same-problem-multiple-spp-solutions.md) is the decision-oriented companion.

### Checkpoint

You should be able to answer **where a responsibility belongs** before choosing a class or API.

## Stage 2 — Understand the runtime kernel

Study these together:

- Middleware and `Pipeline`;
- `SPPEvent` and event handling;
- Registry and dependency management;
- application contexts and `Scheduler`;
- modules, manifests, and scaffolding.

The corresponding deep references are Chapters 56–59.

Do not memorize method names first. Trace one real execution path from its entry point through the runtime mechanism and back to the application behavior.

### Checkpoint

You should be able to distinguish:

- middleware from events;
- dependency resolution from object construction;
- application context selection from route selection;
- module registration from application business logic.

## Stage 3 — Build the first persistent application

Use one evolving **Task Desk** rather than a sequence of unrelated toy projects.

Add, in order:

1. pages and routing;
2. templates and rendering;
3. forms and validation;
4. services and dependency injection;
5. entities and persistence;
6. authentication and authorization;
7. cache, logging, and operational diagnostics.

Use [40 — Data and Persistence](40-data-entities-sppdb-and-xdb.md) and the core security/data references as source maps rather than treating their terminology as interchangeable.

### Checkpoint

You should be able to trace a user action from presentation to domain behavior to persistence, including the security boundary.

## Stage 4 — Make the application testable and operable

Introduce:

- **Parikshak** as the primary SPP testing engine;
- deliberate failure exercises;
- API exposure and dispatch;
- workflow and approvals;
- queues, Cron, and workers;
- reporting and diagnostics.

The learning loop is:

**Learn → Build → Test with Parikshak → Deliberately break → Diagnose → Trace source → Learn when not to use it.**

A test is valuable here not only when it passes, but when it gives you evidence about a boundary and helps you diagnose a deliberately introduced failure.

### Checkpoint

You should be able to answer both:

> Does it work?

and

> Where does it fail when I make one dependency, configuration value, event, route, or storage operation wrong?

## Stage 5 — Learn reactive application architecture

Only after the server-side lifecycle is clear, add:

1. LiveComponent;
2. LiveAction and live service responses;
3. SPP Live transports;
4. SPPUX browser runtime and bridge.

Use Chapters 45–47 for the deep progression.

The important distinction is architectural: a reactive UI does not remove the server/application boundary. It changes how state, actions, rendering, transport, and browser behavior cross that boundary.

### Checkpoint

You should be able to explain when a normal page, LiveComponent, or SPPUX is the better mechanism for the same user-facing requirement.

## Stage 6 — Learn integration and platform breadth

Then add the platform branches:

- SPPAI;
- storage and content promotion;
- polyglot/IPC integration;
- external non-SPP applications;
- language/integration services;
- Maker and SPPDocs/OpenAPI tooling.

At this stage, pay particular attention to **evidence status**. A facade, manifest, adapter, or generated document proves that an interface exists; it does not by itself prove the stronger operational or security guarantee someone might infer from its name.

### Checkpoint

For every important integration, identify:

- entry boundary;
- trust boundary;
- protocol or transport actually implemented;
- failure behavior;
- authentication/authorization mechanism;
- observability;
- source and test evidence.

## Stage 7 — Enterprise architecture

Finish with:

- multiple application contexts;
- deployment topology;
- failure isolation;
- external application composition;
- content promotion and revision concerns;
- enterprise case studies and anti-patterns.

Use Chapters 48, 49, 67, 69, and the architecture-audit chapters as integration material.

Do not begin here. Enterprise diagrams are much easier to understand after the reader can trace the corresponding single-application execution paths.

## Two valid tracks

### Beginner track

Follow the full order:

```text
50 → 65 → 51 → 56–59 → presentation/data/security → 20/Parikshak
→ API/workflow/jobs/reporting → 45–47 → 44/41/48 → 49/69
```

### Experienced-framework track

If you already understand MVC, DI, middleware, routing, persistence, and HTTP APIs, begin with:

1. Chapter 65 for the SPP mental model;
2. Chapter 51 for the concept-to-feature map;
3. Chapters 56–59 for the runtime kernel;
4. Chapter 66 for architectural alternatives;
5. then select the subsystem branches needed by your project.

Do not skip the source-tracing and failure-lab stages merely because the concepts are familiar. SPP-specific behavior is determined by its implementation.

## Completion criteria

A stage is complete when you can:

- explain the concept without relying on SPP terminology;
- implement the smallest useful version in the Task Desk;
- test it with Parikshak where applicable;
- deliberately break one relevant boundary;
- diagnose the failure;
- trace the behavior to source;
- explain at least one case where you would **not** use the mechanism.

Reading the chapter alone is not a completion criterion.

## Relationship to the handbook

This roadmap answers **what to learn next**. Chapter 60 defines the broader handbook completion plan; Chapter 62 turns the roadmap into a continuous build curriculum; Chapters 63–64 define the evidence and documentation quality controls that keep the curriculum trustworthy.
