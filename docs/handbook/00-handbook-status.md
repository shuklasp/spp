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
- `02-kernel-scheduler.md` — Applications, contexts, Scheduler, DomainContext integration, and stack-based domain execution scoping (`withDomain()`).
- `03-registry-and-container.md` — Runtime values and dependency injection.
- `04-events-and-event-handlers.md` — Events, listeners, priorities, overrides, and propagation.
- `05-modules-and-manifests.md` — Modules, activation, dependencies, and compiled metadata.

### Presentation and reactive architecture

- `06-sppview-and-bladeone.md` — SPPView, ViewTags, Drishyam, and extended BladeOne.
- `07-livecomponent.md` — Server-side reactive components and state lifecycle.
- `08-spp-live-transports.md` — Live transport/runtime engines and handlers.
- `09-sppux-runtime.md` — Client-side reactivity, scheduling, events, reconciliation, and error boundaries.

### Integration and security

- `10-polyglot-and-external-applications.md` — Polyglot bridges, sovereign multi-language application hosting (Python, Node.js, Go, PHP-FPM, Static SPAs), subpath virtualization engine, cross-platform process supervision, ambient sensing SDKs, inter-app service mesh (`\SPP\Mesh`), and unified Developer Studio (`/sppdev#apps`).
- `10-security-and-runtime-contracts.md` — Authentication/authorization boundaries, state integrity, rendering safety, and trust boundaries.

### Building applications

- `11-nerd-tutorial-roadmap.md` — How the hands-on tutorial is structured.
- `12-first-spp-application.md` — First application from zero framework knowledge.
- `13-request-lifecycle.md` — What happens after a browser sends a request.
- `14-middleware-and-request-pipeline.md` — Request-boundary processing.
- `15-routing-and-request-dispatch.md` — Application selection, routing, handler dispatch, Single Domain Mode vs Canonical Multi-Domain Mode (`DomainRouter`, `DomainRegistry`), Centralized PathResolver (`PathResolver`), Global vs Domain Applications & the Domain Activation Matrix, 4-tier configuration cascade (`ConfigurationResolver`), automated migration tooling (`domain:migrate`), multi-language ingress subpath virtualization, domain scoping, and application lifecycle management.
- `16-database-and-storage.md` — SPPDB, entities, relations, Next-Gen XDB & Apex multi-model database engine, Multi-Tenant Database Context Resolution (`DatabaseContextResolver`), isolated connection pools, and sovereign framework file services (`\SPP\File`, `putAtomic` staged lock protection, `\SPP\Storage`, pure-PHP S3/R2 cloud disks, virtual in-memory storage, fluent directory scanning, temporary signed URLs, and HTTP 206 partial content range streaming).
- `17-authentication-and-authorization.md` — Guards, sessions, rights, roles, fail-closed multi-domain authorization (`AuthorizationService`, `AccessDeniedException`), and centralized JSONL audit logging (`AuditLogService`) with automated secret redaction.
- `18-cache-logging-workflow.md` — Operational supporting systems.
- `19-cli-and-developer-tooling.md` — CLI architecture, development workflow, supervisor and mesh CLI tools (`app:start`, `app:status`, `app:logs`, `mesh:call`), multi-domain CLI commands (`domain:migrate`, `domain:mode`, `domain:list`), and browser visual operations & developer workstations (SPPAdmin & SPPDev: Domain Architecture mode switcher, persistent Domain Selector & Header Scope Chip, Domain Activation Matrix, Universal Infrastructure & OS topology badge).
- `20-testing-and-debugging.md` — Tests and systematic runtime diagnosis.
- `27-manifest-os-and-file-properties.md` — Declarative Manifest OS (`__spp_file_props.yml`), capability sandboxing, SQLite WAL compilation, SSR/hydration policies, multi-database routing, AST self-healing, and SBOM supply chain governance.

### Enterprise and migration

- `21-enterprise-architecture-and-deployment.md` — Multiple applications, processes, protocols, polyglot services, universal OS & infrastructure engine (`\SPP\Infra`), hosting topology adaptations (FHS, cPanel user home, Windows IIS, Docker, Kubernetes, Serverless), canonical multi-domain enterprise filesystem layout (`apps/`, `domains/<domain>/apps/`, `domains/<domain>/etc/`, `domains/<domain>/sppdata/`), and multi-scope disaster recovery backups (`BackupAdminService`).
- `72-sovereign-store-user-governance-and-baremetal-telemetry.md` — Sovereign App Store, publisher self-service registration, granular visibility tiers (public, personal, closed_group, team), SemVer package release management, vanity publisher profile pages, central moderation queue, and bare-metal Linux node operations console (`/admin/server`) tailored for zero-cPanel deployments (AlmaLinux 9).
- `73-sovereign-communication-evaarta-mail-and-direct-mx-delivery.md` — Sovereign e-Vaarta Mail Server, MTA peer-to-peer Direct MX delivery, RFC 6376 DKIM canonicalization and signing (RSA-2048), automated SPF/DMARC deliverability auditing, inbound anti-relay defense, loopback bypass, virtual mailbox management, and offline service worker outbox queue.
- `74-sovereign-edge-infrastructure-and-cloudflare-bridge.md` — Sovereign Edge Infrastructure, Cloudflare REST API v4 Bridge, multi-tier scoped API token verification fallback, masked credential UX, ~300ms global Anycast DNS propagation, 1-click instant subdomain auto-provisioner, 1-click mail DNS (SPF & DKIM) auto-alignment, and global CDN cache purging.
- `22-total-nerd-tutorial.md` — The same application evolved from plain PHP to SPP, LiveComponent, and SPPUX.
- `23-coming-from-other-frameworks.md` — Conceptual migration guides for Laravel, Symfony, Django, Spring, ASP.NET, React, Vue, and Flutter readers.

## What remains for future deep-dive reference work

The current handbook is the complete learning path. Further additions should deepen existing chapters rather than create unsupported parallel architecture. Candidates for deeper reference appendices include:

- complete ViewTag grammar and parser internals;
- concrete SPP Live engine source-level references;
- full SPPUX reconciliation and reactive implementation analysis;
- complete CLI command catalog with per-command options;
- detailed language-specific bridge references;
- workflow/audit internals;
- testing patterns for each major subsystem; and
- detailed deployment runbooks for concrete environments.

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
- `spp/modules/spp/` — first-party modules, including SPPView, SPP Live, Drishyam, SPPUX, database, authentication, API, workflow, cache, and related subsystems.
- `spp/modules/contrib/` — contributed modules and external integrations.
- `spp/tests/` and module tests — executable evidence.
- `spp/docs/` and `documentation/` — supporting project documentation and tutorials.

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