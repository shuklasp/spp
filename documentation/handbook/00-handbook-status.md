# SPP Handbook — Research and Learning Status

## What this handbook is

This is the **canonical, source-driven handbook for SPP**.

It is written for two readers at the same time:

1. someone who has never used a software framework and needs every concept explained from the beginning; and
2. an experienced developer/architect who wants implementation details, source paths, lifecycle rules, and architectural boundaries.

Every chapter follows the same teaching pattern:

```text
Problem
  ↓
Plain-language concept
  ↓
SPP feature
  ↓
Practical use
  ↓
Internal implementation
  ↓
When to use / not use
  ↓
Framework comparison
```

## September 2026 source baseline

A repository-wide rescan was performed against the current SPP repository state on **2026-09-07**. The latest source commit inspected is `9315a847fb6b79c00879b9c4abca712bbc6a92ed` (`Sanitised SPP`). The preceding repository update `9b793303d72751842fa37ddb9df428e615b73222` records creation of `sppdocs`.

The handbook must therefore be maintained against the September source state rather than treating the August 21 snapshot as permanently authoritative. See [72 — Current Source Rescan — 2026-09-07](72-current-source-rescan-2026-09-07.md).

## Evidence levels

Every substantive claim follows this evidence hierarchy:

- **Implemented** — directly verified in executable source and/or tests.
- **Documented** — established by repository documentation but not yet fully source-audited.
- **Derived** — architectural interpretation of implemented behavior.
- **Guidance** — recommended engineering practice, clearly separated from framework behavior.
- **Proposed** — future design idea; never presented as current functionality.

A class existing in the repository is not by itself proof of every behavior one might expect from its name.

## Canonical source policy

For behavioral claims, use this order of authority:

1. executable source;
2. tests and fixtures;
3. configuration/manifests consumed by the source;
4. existing repository documentation;
5. architectural interpretation.

When older documentation and current code disagree, the current executable implementation wins.

## Diagram policy

The handbook uses a strict visual rule:

- **Mermaid** — genuine architecture, lifecycle, request flow, dependency flow, event flow, decision flow, and deployment topology.
- **Code blocks** — PHP/JavaScript/YAML/XML/CLI commands, literal directory layouts, configuration, and actual output.
- **Tables** — simple comparisons and relationships that do not need a graphic.
- **Prose/lists** — explanations and procedures.

A diagram must be useful, source-accurate, simple enough to understand, and valid for GitHub rendering. Decorative or redundant diagrams are removed.

## Current learning order

### Foundations

- `01-getting-started.md` — What SPP is and what a framework does.
- `02-kernel-scheduler.md` — Applications, contexts, and the Scheduler.
- `03-registry-and-container.md` — Runtime values and dependency injection.
- `04-events-and-event-handlers.md` — Events, listeners, priorities, overrides, and propagation.
- `05-modules-and-manifests.md` — Modules, activation, dependencies, and compiled metadata.

### Presentation and reactive architecture

- `06-sppview-and-bladeone.md` — SPPView, ViewTags, Drishyam, and extended BladeOne.
- `07-livecomponent.md` — Server-side reactive components and state lifecycle.
- `08-spp-live-transports.md` — Live transport/runtime engines and handlers.
- `09-sppux-runtime.md` — Client-side reactivity, scheduling, events, reconciliation, and error boundaries.

### Integration and security

- `10-polyglot-and-external-applications.md` — Polyglot bridges and independent external applications.
- `10-security-and-runtime-contracts.md` — Authentication/authorization boundaries, state integrity, rendering safety, and trust boundaries.

### Building applications

- `11-nerd-tutorial-roadmap.md` — How the hands-on tutorial is structured.
- `12-first-spp-application.md` — First application from zero framework knowledge.
- `13-request-lifecycle.md` — What happens after a browser sends a request.
- `14-middleware-and-request-pipeline.md` — Request-boundary processing.
- `15-routing-and-request-dispatch.md` — Application selection, routing, and handler dispatch.
- `16-database-and-storage.md` — SPPDB, adapters, XDB, caching, and storage boundaries.
- `17-authentication-and-authorization.md` — Guards, sessions, rights, roles, and authorization.
- `18-cache-logging-workflow.md` — Operational supporting systems.
- `19-cli-and-developer-tooling.md` — CLI architecture and development workflow.
- `20-testing-and-debugging.md` — Tests and systematic runtime diagnosis.

### Enterprise and migration

- `21-enterprise-architecture-and-deployment.md` — Multiple applications, processes, protocols, polyglot services, and deployment topology.
- `22-total-nerd-tutorial.md` — The same application evolved from plain PHP to SPP, LiveComponent, and SPPUX.
- `23-coming-from-other-frameworks.md` — Conceptual migration guides for Laravel, Symfony, Django, Spring, ASP.NET, React, Vue, and Flutter readers.

### September 2026 source-rescan additions

- `72-current-source-rescan-2026-09-07.md` — Current module inventory and curriculum impact from the latest repository state.

The rescan specifically requires deeper treatment of SPPAPI, SPPAuth, SPPCache, SPPCrypto, SPPDBPool, SPPEnv, SPPIntegrations, SPPLang, SPPLive, SPPLogger, SPPMaker, SPPMedia, SPPDocs, and SPPXDB.

## What remains for future deep-dive reference work

The learning path is intentionally expanded whenever a source-backed subsystem represents a distinct developer responsibility. Future additions should deepen existing chapters rather than create unsupported parallel architecture.

Priority deep dives after the September rescan are:

- SPPAPI controllers, dispatchers, OpenAPI, subscribers, resources, responses, pagination, model binding, live actions and AJAX;
- SPPAuth identity, guards, policies, field policies, RBAC, MFA, magic links, OAuth, SCIM, rate limiting and audit logging;
- SPPCache lifecycle and operations;
- SPPCrypto Vault and key-management architecture;
- SPPDBPool and its relationship to SPPDB;
- SPPEnv and environment/runtime configuration;
- SPPIntegrations and integration boundaries;
- SPPLang and localization;
- SPPLogger and diagnostics;
- SPPMaker and framework meta-programming;
- SPPMedia and media/file workflows;
- SPPDocs as framework documentation-generation tooling;
- deeper SPPXDB source tracing including Raft-related, ACL, locking, observers, validation, query, and controller surfaces.

These should only be added when their implementation has been traced sufficiently to support normative documentation.

## Important research corrections

Earlier drafts contained assumptions stronger than the available source evidence. Those drafts are **not canonical**.

The handbook does not present the following as universal implemented SPP behavior unless concrete source/test evidence proves it:

- a generic distributed event bus across arbitrary applications;
- an arbitrary server-side DOM-diff protocol;
- lifecycle methods imported from another framework without matching SPP source;
- automatic computed-property dependency graphs not established by the implementation;
- universal IPC semantics across every bridge; or
- universal database guarantees merely because similarly named methods exist.

## Research source roots

The primary repository roots are:

- `spp/core/` — kernel/runtime infrastructure.
- `spp/modules/spp/` — first-party modules and platform subsystems.
- `spp/modules/contrib/` — contributed modules and external integrations.
- `spp/tests/` and module tests — executable evidence.
- `spp/docs/` and `documentation/` — supporting project documentation, generated references, and tutorials.

The September source tree explicitly contains first-party directories for API, authentication, cache, crypto, database, database pooling, environment configuration, integrations, language support, live runtime, logging, maker/scaffolding, and media, in addition to the previously documented presentation and reactive modules.

## Editorial rule

Every chapter should answer, in order:

1. What problem does this feature solve?
2. What does the term mean in ordinary programming language?
3. What does SPP implement?
4. How do I use it?
5. What does the source actually do internally?
6. When should I not use it?
7. How does it compare conceptually with frameworks the reader may already know?

The objective is not to make SPP documentation sound complicated. The objective is to make SPP understandable without sacrificing technical accuracy.