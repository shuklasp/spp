# SPP Framework Handbook

This directory is the **canonical current handbook** for SPP on branch `handbook-v3`.

## Read the handbook

Start with [00 — Handbook Status](00-handbook-status.md), then [01 — Getting Started](01-getting-started.md) and follow the learning roadmap in [61 — SPP Learning Roadmap](61-spp-learning-roadmap.md).

The handbook uses one continuous learning loop:

**Learn → Build → Test with Parikshak → Deliberately break → Diagnose → Trace source → Learn when not to use it.**

Parikshak is the primary SPP testing engine.

## Canonical documentation policy

Only current documentation belongs in this directory. Superseded chapter variants, duplicate tutorial branches, stale aliases, historical drafts, and research scratch files should be removed rather than presented as parallel documentation.

See [93 — Canonical Handbook Fileset](93-latest-handbook-index.md).

## Foundations

- [00 — Handbook Status](00-handbook-status.md)
- [01 — Getting Started](01-getting-started.md)
- [50 — Frameworks 101](50-frameworks-101-and-how-spp-builds-on-them.md)
- [51 — Framework Concept → SPP Feature Map](51-framework-concept-to-spp-feature-map.md)
- [52 — 30-Minute SPP Quick Start](52-30-minute-quickstart.md)
- [53 — Configuration and Runtime Settings](53-configuration-and-settings.md)
- [54 — Routing and Dispatch](54-routing-and-dispatch.md)
- [55 — Forms, Validation, and Data Binding](55-forms-validation-and-data-binding.md)
- [61 — SPP Learning Roadmap](61-spp-learning-roadmap.md)
- [62 — Continuous Task Desk Curriculum](62-continuous-task-desk-curriculum.md)
- [63 — Feature Evidence and Status Model](63-feature-evidence-and-status-model.md)
- [64 — Handbook Documentation Quality Gate](64-handbook-documentation-quality-gate.md)
- [65 — The SPP Mental Model](65-spp-mental-model.md)
- [71 — What Makes SPP Different?](71-what-makes-spp-different.md)

## Runtime and Kernel

- [02 — Kernel Scheduler and Application Contexts](02-kernel-scheduler.md)
- [03 — Registry and Container](03-registry-and-container.md)
- [04 — Events and Event Handlers](04-events-and-event-handlers.md)
- [05 — Modules and Manifests](05-modules-and-manifests.md)
- [56 — Middleware and Pipeline](56-middleware-and-pipeline.md)
- [57 — Events and SPPEvent](57-events-and-sppevent.md)
- [58 — Registry and Dependency Injection](58-registry-and-dependency-injection.md)
- [59 — Modules, Manifests, and Scaffolding](59-modules-manifests-and-scaffolding.md)

## Presentation and Reactive Architecture

- [06 — SPPView, BladeOne, and Drishyam](06-sppview-and-bladeone.md)
- [07 — LiveComponent](07-livecomponent.md)
- [08 — SPP Live Transports](08-spp-live-transports.md)
- [09 — SPPUX Runtime](09-sppux-runtime.md)
- [45 — LiveComponent from Zero to Kernel](45-livecomponent-from-zero-to-kernel.md)
- [46 — SPP Live Transport Architecture](46-spp-live-transport-architecture.md)
- [47 — SPPUX Browser Runtime from Zero](47-sppux-browser-runtime-from-zero.md)
- [83 — Live Architecture Delta](83-live-architecture-delta.md)

## Data, Identity, Security, API, and Platform

- [16 — Database, SPPDB, and SPP XDB](16-database-and-storage.md)
- [17 — Authentication and Authorization](17-authentication-and-authorization.md)
- [18 — Cache, Logging, Workflow, and Operations](18-cache-logging-workflow.md)
- [20 — Testing and Source-Driven Diagnosis](20-testing-and-debugging.md)
- [21 — Enterprise Architecture and Deployment](21-enterprise-architecture-and-deployment.md)
- [40 — Data and Persistence](40-data-entities-sppdb-and-xdb.md)
- [41 — Storage and Content Promotion](41-storage-transfer-and-content-promotion.md)
- [42 — Reporting and Observability](42-reporting-observability-and-diagnostics.md)
- [43 — Queue, Cron, and Background Execution](43-queue-cron-and-background-execution.md)
- [44 — SPPAI and AI Integration](44-spai-and-ai-integration.md)
- [74 — SPPAPI](74-sppapi.md)
- [75 — SPPAuth](75-sppauth.md)
- [76 — Data, Cache, Crypto, and Pools](76-data-cache-crypto-pools.md)
- [77 — Platform Services](77-platform-services.md)
- [78 — Maker and SPPDocs Toolchain](78-maker-and-docs-toolchain.md)
- [79 — SPPXDB](79-sppxdb.md)
- [80 — September Platform Reference Map](80-september-platform-reference-map.md)
- [84 — Data, Identity, and Platform Architecture Delta](84-data-identity-platform-architecture-delta.md)

## Architecture and Integration

- [48 — Polyglot, IPC, and External Applications](48-polyglot-ipc-and-external-application-architecture.md)
- [49 — Multi-Application Enterprise Architecture](49-multi-application-enterprise-deployment.md)
- [66 — Routing as a Multi-Paradigm Architecture](66-routing-multi-paradigm.md)
- [67 — Workflow, Approval Chains, and State Machines](67-workflow-approval-wizards-and-state-machines.md)
- [68 — Queue, Cron, Workers, and Background Execution](68-queue-cron-workers-background-execution.md)
- [69 — Internationalization, Reporting, and Observability](69-i18n-reporting-observability.md)
- [66A — Same Problem, Multiple SPP Solutions](66-same-problem-multiple-spp-solutions.md)
- [67A — Architecture Anti-Patterns](67-architecture-antipatterns-and-mistakes.md)
- [68A — How to Read the SPP Source](68-reading-the-spp-source.md)
- [69A — Enterprise Reference Case Study](69-enterprise-reference-case-study.md)
- [81 — September Architecture Delta Audit](81-september-architecture-delta-audit.md)
- [82 — SPPAPI and Security Boundary Lab](82-sppapi-and-security-lab.md)

## Developer Guides

- [85 — Framework-Level Module Development Guide](85-framework-module-development-guide.md)
- [86 — Application-Level Module Development Guide](86-application-module-development-guide.md)
- [87 — SPPAI Developer Guide](87-spai-developer-guide.md)

These deliberately separate **framework/platform module authorship** from **application-specific module development**.

## Migration and Coverage

- [23 — Coming to SPP from Other Frameworks](23-coming-from-other-frameworks.md)
- [24 — Tutorial Curriculum](24-tutorial-curriculum.md)
- [28 — Framework Feature Inventory](28-framework-feature-inventory.md)
- [29 — Feature Coverage Roadmap](29-feature-coverage-roadmap.md)
- [60 — Handbook Completion Plan](60-handbook-completion-plan.md)
- [70 — Porting to SPP from Other Frameworks](70-porting-to-spp-from-other-frameworks.md)
- [70A — Framework Porting Playbooks](70a-framework-porting-playbooks.md)

## Source-first evidence policy

For current behavior, use this authority order:

```text
Executable source
      ↓
Tests / fixtures
      ↓
Consumed configuration / manifests
      ↓
Repository documentation
      ↓
Architectural interpretation
```

The handbook distinguishes **Implemented**, **Documented**, **Derived**, **Guidance**, and **Planned/Unverified** claims. Strong security, transaction, distributed-systems, AI, transport, and deployment guarantees require concrete evidence.

## Diagram policy

- **Mermaid** — architecture, lifecycle, sequence, decision, and data-flow diagrams.
- **Code blocks** — PHP, JavaScript, YAML/XML, CLI, directory layouts, configuration, and actual output.
- **Tables** — straightforward comparisons and relationships.
- **Prose** — explanation and procedures.

Every diagram must be useful, source-accurate, simple, and GitHub-renderable.
