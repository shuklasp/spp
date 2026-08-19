# SPP Framework Handbook

## Canonical Documentation

This directory is the canonical Markdown source for the SPP Framework Handbook on branch `handbook-v2`.

The handbook is source-driven: framework behavior is documented from the SPP implementation, with architectural explanations, enterprise patterns, comparisons with other frameworks, and deep-dive tutorials.

## Evidence policy

Every substantial claim is classified internally as **Implemented**, **Documented**, **Derived**, or **Proposed**. Current executable source and tests take precedence over older prose documentation. Proposed architecture is never presented as existing framework behavior.

## Audience tracks

- **Explorer** — first-time SPP developers
- **Builder** — application developers
- **Architect** — framework and enterprise architects
- **Kernel Hacker** — source-code and runtime deep dives

## Current chapters

- [00 — Research status and evidence policy](00-handbook-status.md)
- [01 — Introduction to SPP](01-getting-started.md)
- [02 — Scheduler and application contexts](02-kernel-scheduler.md)
- [03 — Registry and IoC container](03-registry-and-container.md)
- [04 — Events, EventHandler, and SPPEvent](04-events-and-event-handlers.md)
- [05 — Module discovery, manifests, and compiled registry](05-modules-and-manifests.md)
- [06 — SPPView, extended BladeOne, and Drishyam integration](06-sppview-and-bladeone.md)
- [07 — LiveComponent](07-livecomponent.md)
- [08 — SPP Live transport engines](08-spp-live-transports.md)
- [09 — SPPUX runtime](09-sppux-runtime.md)
- [10 — Polyglot bridges and external applications](10-polyglot-and-external-applications.md)

## Expanded handbook roadmap

The finished handbook will continue with source-audited chapters for:

1. bootstrap and autoloading;
2. `SPPObject`, base classes, and framework utilities;
3. service providers and provider interfaces;
4. MiddlewareKernel, middleware contracts, security middleware, and pipeline mechanics;
5. routing, route attributes, view routing, and application route discovery;
6. complete module loader/compiler/installer behavior;
7. configuration layers, module configuration, YAML/XML compatibility, and cache compilation;
8. SPPView compiler/renderer/locator/router internals;
9. ViewTag language and component facilities;
10. BladeOne extension directives and template macros;
11. forms, validators, data transformation, and accessibility;
12. asset orchestration;
13. LiveComponent attributes, hydration/dehydration, state signing, validation, dispatch, streaming, lazy and isolated components;
14. SPP Live AJAX/SSE/WebSocket/Redis/SQLite implementations;
15. SPPUX signals, computed state, scheduler, event delegation, template runtime, reconciler, error boundaries, grid, and UI layers;
16. asynchronous workers, queues, Scheduler cron, and long-running services;
17. security architecture and authentication/authorization modules;
18. storage/database/XDB and cross-database integration;
19. audit, logging, workflow/CQRS, cache, and deployment subsystems;
20. polyglot bridges and each supported language runtime;
21. external application integration and contributed adapters;
22. CLI and generator reference;
23. testing strategy and executable architecture specifications;
24. enterprise tutorials and migration guides.

## Tutorial tracks

### Track A — PHP fundamentals

Build a minimal SPP application from the actual bootstrap and routing mechanisms, then add configuration, modules, services, persistence, views, validation, authentication, and tests.

### Track B — LiveComponent

Refactor the same application into server-side reactive components using the implemented LiveComponent attributes, state model, validation, dispatch, streaming, lazy/isolated rendering, and selectable live engines.

### Track C — SPPUX

Add client-side reactive islands using the actual SPPUX runtime: signals, stores, tagged templates, event delegation, scheduling, reconciliation, and error boundaries.

### Track D — Enterprise integration

Use multiple SPP application contexts and then integrate selected functions with the actual polyglot bridge and external-application mechanisms present in the repository.

## Migration/comparison tracks

The handbook will include "coming from" guides for Laravel, Symfony, Django, Spring Boot, ASP.NET, React, Vue, and Flutter. Each mapping will be created after the corresponding SPP subsystem has been source-audited.

## Diagram standard

Canonical diagrams are authored as readable text/Markdown diagrams so labels remain visible, searchable, and render reliably in repository viewers. SVG text is not used for core architecture diagrams.
