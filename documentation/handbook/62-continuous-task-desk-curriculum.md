# 62 — Continuous Task Desk Curriculum

## Purpose

The handbook uses one evolving application—the **Task Desk**—as its practical spine. The project should grow in architectural capability rather than being discarded whenever a new chapter begins.

That matters because framework knowledge is mostly about **composition**: the reader needs to see how routing, middleware, events, services, persistence, security, testing, APIs, background work, and reactive UI cooperate inside one application.

## The rule: evolve, do not restart

```text
Plain PHP baseline
      ↓
MVC Task Desk
      ↓
SPP Task Desk
      ↓
Persistent + secured Task Desk
      ↓
Tested + API-enabled Task Desk
      ↓
Background-work Task Desk
      ↓
Reactive Task Desk
      ↓
Integrated / multi-application Task Desk
```

Each milestone keeps the previous behavior unless the lesson explicitly demonstrates a migration or replacement.

## Milestone 0 — Plain PHP baseline

Build a minimal Task Desk without framework abstractions.

The application should have:

- a task list;
- task creation;
- task completion;
- simple validation;
- a visible failure case.

The purpose is not to produce a production application. It establishes the baseline that later framework mechanisms will replace or organize.

### Learning questions

- Where does routing live?
- Where is validation performed?
- How are dependencies created?
- Where does persistence happen?
- What happens when an exception occurs?

## Milestone 1 — Recognize MVC

Refactor the same application into explicit controller, model/domain, and view responsibilities.

Then compare the result with SPP concepts rather than claiming that SPP is simply MVC with more features.

### Exercise

Move one responsibility at a time and record what becomes easier or harder.

## Milestone 2 — First SPP application

Move the Task Desk into SPP and trace:

```text
HTTP request
  → application/context
  → routing/page entry
  → controller/application behavior
  → response
```

Use the source map from Chapters 50, 51, and 65.

### Required output

The learner should be able to point to the SPP source responsible for the relevant runtime boundary instead of merely naming a framework feature.

## Milestone 3 — Middleware and events

Add one cross-cutting requirement such as request diagnostics, authorization, or request metadata.

Implement the requirement using middleware where it belongs. Then implement a genuinely event-oriented requirement using the event system.

### Failure lab

Deliberately:

- remove a middleware entry;
- alter ordering/priority where the example permits it;
- prevent an event from reaching later handlers.

Observe the difference between a pipeline boundary and an event boundary.

## Milestone 4 — Services, Registry, and modules

Move application behavior out of controllers and into reusable services.

Then investigate dependency resolution and module boundaries.

The learner should understand three distinct decisions:

1. direct construction;
2. container/Registry resolution;
3. module-provided behavior.

Do not use the container merely because it exists. Chapter 66 is the decision guide.

## Milestone 5 — Presentation, forms, and validation

Extend the same Task Desk with:

- reusable views/templates;
- form processing;
- validation;
- error presentation;
- one ordinary page flow.

Then deliberately submit invalid input and trace where it is rejected and how the error returns to the user.

## Milestone 6 — Entities and persistence

Introduce a real task entity and persistent storage.

Trace the stack:

```text
Controller / Live / Service
        ↓
Entity / domain behavior
        ↓
SPPDB abstraction
        ↓
SPPDB adapter / SPPXDB facade
        ↓
Concrete storage engine
        ↓
Physical storage
```

Keep the distinctions between SPPDB, SPPDBPool, and SPPXDB explicit. Do not infer transaction, durability, locking, or distributed-consistency guarantees from an API name alone.

## Milestone 7 — Identity and authorization

Add authenticated users and task ownership/authorization.

The exercise must distinguish:

- authentication: who is the caller;
- authorization: what the caller may do;
- application/session state;
- data-level policy.

### Failure lab

Test at least one unauthorized path and verify that the protection occurs at the intended boundary rather than only by hiding a UI control.

## Milestone 8 — Parikshak becomes the test loop

Turn the Task Desk into the practical test subject for the handbook.

For each new capability:

```text
Build
  ↓
Parikshak test
  ↓
Deliberate break
  ↓
Observe failure
  ↓
Diagnose
  ↓
Trace source
  ↓
Repair
```

The point is not to create one isolated testing chapter. Parikshak should become part of how the reader learns every later subsystem where a meaningful test can be written.

## Milestone 9 — API boundary

Expose a carefully chosen Task Desk operation through SPPAPI.

Compare:

- ordinary page request;
- API request;
- service call.

Trace request detection, authentication/exposure, dispatch, and response construction using the current SPPAPI source documentation.

### Failure lab

Test an invalid token or unauthorized operation and distinguish an API boundary failure from a domain failure.

## Milestone 10 — Workflow and approvals

Add a business process such as:

```text
Draft → Submitted → Approved → Completed
```

Introduce explicit state transitions and approval responsibility.

The learner should explain why workflow state is not the same thing as UI state, HTTP session state, or a queue job.

## Milestone 11 — Cron, queue, and background work

Add one operation that should not block the user request—for example, generating a report or processing a batch of tasks.

Compare three execution choices:

| Requirement | Candidate mechanism |
|---|---|
| immediate request work | normal application execution |
| scheduled work | Cron/scheduler |
| deferred/background work | queue/worker |

Then introduce a failure in the background path and inspect how the system reports it.

## Milestone 12 — Reporting and observability

Add an operational report to the same application.

Use this milestone to connect:

- application events;
- logs/diagnostics;
- bounded data queries;
- reporting output;
- failure diagnosis.

Do not equate “has a logging class” with a complete observability architecture. Record exactly what the current implementation exposes.

## Milestone 13 — LiveComponent

Turn one high-interaction Task Desk screen into a LiveComponent.

Keep an ordinary page for comparison.

Demonstrate:

- public state;
- action invocation;
- hydration/dehydration;
- rendering updates;
- validation or error state.

### Decision exercise

Build the same interaction once as a normal page and once as a LiveComponent. Explain the trade-off rather than declaring one universally better.

## Milestone 14 — SPP Live

Move from the component programming model to transport architecture.

Trace:

```text
Browser
  ↓
SPP Live transport
  ↓
Live request/action
  ↓
LiveComponent / LiveAction
  ↓
response/instructions
  ↓
Browser update
```

Then study fallback and transport behavior from source rather than relying on architectural labels.

## Milestone 15 — SPPUX

Introduce the browser runtime only after the reader understands server-side reactive execution.

Compare:

- server-rendered page;
- LiveComponent;
- SPPUX island/component behavior.

The exercise should identify what runs in the browser, what remains server-side, and what crosses the bridge.

## Milestone 16 — SPPAI

Add one AI-assisted Task Desk capability.

Keep the domain operation explicit—for example, classifying a task or producing structured suggestions.

Trace the facade/provider boundary and configuration-driven provider selection.

### Evidence rule

Do not infer authentication, reliability, recovery, or provider behavior from a generated AI manifest or provider name. Verify the current implementation and record limitations.

## Milestone 17 — Storage and content promotion

Add a controlled export/import or content-promotion exercise where the current repository implementation supports it.

The learner should distinguish:

- application source deployment;
- configuration deployment;
- database/content movement;
- revision/diff/audit concerns.

Do not describe the operation as atomic or rollback-safe unless source and test evidence demonstrate those properties.

## Milestone 18 — External application integration

Integrate one external application or service at a real boundary.

Document separately:

- composition boundary;
- trust boundary;
- transport/protocol;
- authentication;
- failure behavior;
- correlation/diagnostics;
- ownership of data.

“IPC” is a category, not a sufficient protocol description.

## Milestone 19 — Multi-application capstone

Split the Task Desk into multiple application contexts or cooperating applications where the current SPP architecture supports the exercise.

Study:

- context selection;
- routing boundaries;
- shared versus isolated state;
- external calls;
- failure isolation;
- deployment topology.

The final design must explain what happens when one application is unavailable.

## The curriculum's recurring evidence record

For every milestone, keep a small record:

```text
Feature:
Why it exists:
SPP mechanism:
Source landmark:
Parikshak test:
Deliberate failure:
Observed failure:
Repair:
When not to use it:
Evidence status:
```

This record becomes the learner's personal architecture notebook and provides material for the handbook's evidence model in Chapter 63.

## What counts as completion?

A Task Desk milestone is complete only when the learner can:

- build it;
- test it;
- break it intentionally;
- diagnose the boundary involved;
- trace the implementation;
- explain an alternative;
- explain when the SPP mechanism should not be used.

The project is therefore a **continuous laboratory**, not a portfolio of isolated demos.
