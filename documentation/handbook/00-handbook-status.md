# SPP Handbook — Research and Learning Status

## What this handbook is

This is the **canonical, source-driven handbook for SPP**.

It is written for two readers at the same time:

1. someone who has never used a software framework and needs every concept explained from the beginning; and
2. an experienced developer/architect who wants the implementation details, source paths, lifecycle rules, and architectural boundaries.

The chapters therefore progress from:

```text
What is a framework?
        ↓
What is an SPP application?
        ↓
How does an SPP request work?
        ↓
How do services, modules, events and middleware work?
        ↓
How does SPP render pages?
        ↓
How does LiveComponent work?
        ↓
How does SPP Live transport live interactions?
        ↓
How does SPPUX run in the browser?
        ↓
How does SPP integrate other runtimes and applications?
        ↓
How do we design and operate an enterprise SPP system?
```

## Evidence levels

Every substantive claim follows this evidence hierarchy:

- **Implemented** — directly verified in executable source and/or tests.
- **Documented** — established by repository documentation but not yet fully source-audited.
- **Derived** — architectural interpretation of implemented behavior.
- **Guidance** — recommended engineering practice, clearly separated from framework behavior.
- **Proposed** — future design idea; never presented as current functionality.

A class existing in the repository is not by itself proof of every behavior one might expect from its name.

## Canonical source policy

For behavioral claims, the evidence order is:

1. executable source;
2. tests and fixtures;
3. configuration/manifests consumed by the source;
4. existing repository documentation;
5. architectural interpretation.

When older documentation and current source disagree, the current executable implementation wins.

## Diagram policy

The handbook uses a simple visual rule:

- **Mermaid** — architecture, lifecycle, request flow, dependency flow, event flow, and other genuine diagrams.
- **Code blocks** — PHP/JavaScript/YAML/XML/CLI commands, directory examples, and literal output.
- **Tables** — simple comparisons or relationships that do not need a graphic.
- **Prose/lists** — explanations and procedures.

Diagrams are included only when they improve understanding. A diagram must be source-accurate and valid for GitHub Markdown rendering.

## Current handbook learning order

### Foundations

- `01-getting-started.md` — What SPP is and the framework mental model.
- `02-kernel-scheduler.md` — Applications, contexts, and the Scheduler.
- `03-registry-and-container.md` — Registry data and dependency injection.
- `04-events-and-event-handlers.md` — Events, handlers, listeners, priorities, overrides.
- `05-modules-and-manifests.md` — Modules, activation, dependencies, compiled registry.

### Presentation and reactive architecture

- `06-sppview-and-bladeone.md` — SPPView, extended BladeOne, ViewTags, Drishyam.
- `07-livecomponent.md` — Server-side reactive components.
- `08-spp-live-transports.md` — AJAX, SSE, WebSocket, Redis/SQLite engines and live runtime.
- `09-sppux-runtime.md` — Client-side reactive runtime and DOM reconciliation.

### Integration and enterprise boundaries

- `10-polyglot-and-external-applications.md` — Other runtimes and independent applications.
- `10-security-and-runtime-contracts.md` — Trust boundaries and security responsibilities.

### Hands-on learning

- `11-nerd-tutorial-roadmap.md` — The complete tutorial progression.
- `12-first-spp-application.md` — Build the first application from zero knowledge.
- `13-request-lifecycle.md` — Follow one request through the runtime.
- `14-middleware-and-request-pipeline.md` — Build request-boundary middleware.
- `15-routing-and-request-dispatch.md` — Understand route/page/API dispatch.

## What is still being expanded

The handbook is intentionally not considered complete yet.

The next major areas are the detailed reference/tutorial chapters for:

- application configuration and environments;
- service providers/provider-like registration mechanisms where implemented;
- forms and validation;
- ViewTags and component internals;
- LiveComponent attributes and state/serialization internals;
- each concrete SPP Live engine;
- SPPUX reactive internals and reconciliation algorithms;
- CLI commands and generators;
- database/XDB and storage architecture;
- authentication/authorization modules;
- workflow/audit/logging/cache subsystems;
- testing and fixtures;
- deployment and production topology; and
- the full plain-PHP → SPP → LiveComponent → SPPUX tutorial.

Those chapters will be added only after their source paths have been traced.

## Important research corrections

Earlier chat-only drafts contained assumptions that were stronger than the source evidence. Those drafts are **not canonical**.

In particular, the handbook does not present the following as universal implemented SPP behavior unless a concrete source path proves it:

- a generic distributed event bus across arbitrary applications;
- an arbitrary server-side DOM-diff protocol;
- lifecycle methods imported from another framework without matching SPP source;
- automatic dependency graphs for computed properties that the inspected implementation does not establish; or
- a universal IPC/security protocol covering every external integration.

## Research source roots

The primary repository roots used for research are:

- `spp/core/` — kernel/runtime infrastructure.
- `spp/modules/spp/` — first-party modules, including SPPView, SPP Live, Drishyam, SPPUX, database, auth, API, workflow and related subsystems.
- `spp/modules/contrib/` — contributed modules and external integrations.
- `spp/tests/` and module tests — executable evidence.
- `spp/docs/` and `documentation/` — supporting project documentation and tutorials.

## Editorial rule

The handbook should be **detailed without becoming unnecessarily complicated**.

Every chapter should answer, in order:

1. What problem does this feature solve?
2. What does the term mean in ordinary programming language?
3. What does SPP implement?
4. How do I use it?
5. What does the source actually do internally?
6. When should I not use it?
7. How does it compare conceptually with frameworks the reader may already know?

The objective is not to make the documentation sound sophisticated. The objective is to make SPP understandable.
