# 59. SPP Runtime — One Picture

This page is the map to return to whenever the framework feels too large.

```mermaid
flowchart TD
    A[Browser / API client / External system]
    --> B[Scheduler selects application context]
    B --> C[SPP App runtime]

    C --> D[MiddlewareKernel / Pipeline]
    D --> E[Routing / pages / API dispatch]

    E --> F[Controller / Page / API / LiveComponent]
    F --> G[Application Services]

    G --> H[Registry / Dependency Injection]
    G --> I[Events]
    G --> J[Modules]
    G --> K[SPPDB / Entity / XDB]
    G --> L[Workflow]
    G --> M[Queue / Cron]
    G --> N[SPPAI]

    F --> O[SPPView / BladeOne / Drishyam]
    F --> P[LiveComponent]
    P --> Q[SPP Live]
    Q --> R[Browser runtime]
    R --> S[SPPUX]

    K --> T[Storage]
    T --> U[Migration / Transfer / Promotion]

    G --> V[SPPAPI]
    V --> W[External clients]

    G --> X[Polyglot / IPC bridges]
    X --> Y[Non-SPP applications]

    Z[Parikshak] -. verifies .-> C
    AA[Logging / Audit / Observability] -. observes .-> C
```

## How to read this diagram

Start at the top.

A request or external interaction enters SPP. The Scheduler identifies the application context. The application runtime then applies shared infrastructure before handing control to the application-specific behavior.

The middle of the diagram is the important application layer:

```text
routing
→ controller/page/API/live action
→ service
→ framework subsystems
```

The bottom and side branches explain why SPP becomes more than a conventional MVC framework:

- reactive server-side components;
- a separate live transport architecture;
- a browser-side reactive runtime;
- persistent data/XDB;
- workflows;
- asynchronous work;
- AI integration;
- multiple application contexts;
- polyglot/external systems;
- offline-to-live promotion;
- testing and observability.

## The beginner mental model

If the whole diagram is too much, remember only this:

```text
Request
  ↓
SPP figures out which application should handle it
  ↓
SPP runs shared framework infrastructure
  ↓
Your application code runs
  ↓
SPP helps your code reach data, views, APIs, events, workflows, etc.
  ↓
A response or external effect is produced
```

Everything else in the handbook expands one box in this picture.
