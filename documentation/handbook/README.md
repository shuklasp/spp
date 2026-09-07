# SPP Framework Handbook

## Canonical Documentation

This directory is the canonical Markdown source for the SPP Framework Handbook on branch `handbook-v3`.

The handbook is a **learning book plus an implementation reference**. It assumes the reader may know a programming language but may have no idea what a framework is, why frameworks exist, or how the parts of a framework cooperate. Advanced readers can use the **Kernel Hacker** sections and source maps to jump directly into implementation details.

## Evidence policy

Every substantial claim is classified internally as **Implemented**, **Documented**, **Derived**, **Guidance**, or **Planned/Unverified**. Authority order: executable source; tests/fixtures; consumed configuration/manifests; repository documentation; architectural interpretation. When documentation is stronger than implementation evidence, the handbook records the discrepancy rather than copying an unsupported guarantee.

See [63 — Feature Evidence and Status Model](63-feature-evidence-and-status-model.md) for the full evidence rules.

## Diagram policy

- **Mermaid** for genuine architecture, lifecycle, sequence, decision, and data-flow diagrams.
- **Code blocks** for PHP/JavaScript/YAML/XML/CLI, literal layouts, configuration, and actual output.
- **Tables** for simple comparisons and relationships.
- **Prose/lists** for explanation and procedures.

Every diagram must be useful, source-accurate, simple, and GitHub-renderable.

## Architecture-audit additions

The September source audit identified integrated architecture views that complement the feature chapters:

- [81 — September architecture delta audit](81-september-architecture-delta-audit.md)
- [82 — Lab: SPPAPI and security boundary](82-lab-sppapi-and-security.md)
- [83 — Live architecture delta](83-live-architecture-delta.md)
- [84 — Data, identity, and platform architecture delta](84-data-identity-platform-architecture-delta.md)

These are integration/audit chapters, not replacements for subsystem tutorials.

## Architecture layers to keep coherent

1. application and context management;
2. runtime, middleware, events, modules, and dependency management;
3. MVC/SPPView and rendering;
4. LiveComponent, LiveAction, SPP Live, and SPPUX;
5. SPPAPI request/exposure/dispatch boundaries;
6. identity, authentication, authorization, and security middleware;
7. SPPDB, SPPDBPool, and SPPXDB;
8. cache, crypto, environment, logging, media, language, and integrations;
9. queues, Cron, workers, polyglot/IPC, and external applications;
10. Maker, SPPDocs, OpenAPI, and Parikshak developer lifecycle.

## Key reference chapters

### Foundations

- [00 — Research status and learning order](00-handbook-status.md)
- [01 — Getting Started](01-getting-started.md)
- [50 — Frameworks 101](50-frameworks-101-and-how-spp-builds-on-them.md)
- [51 — Framework Concept to SPP Feature Map](51-framework-concept-to-spp-feature-map.md)
- [52 — 30-Minute Quick Start](52-30-minute-quick-start.md)
- [53 — Configuration and Runtime Settings](53-configuration-and-settings.md)
- [54 — Routing and Dispatch](54-routing-and-dispatch.md)
- [55 — Forms, Validation, and Data Binding](55-forms-validation-and-data-binding.md)
- [65 — The SPP Mental Model](65-spp-mental-model.md)
- [71 — What Makes SPP Different?](71-what-makes-spp-different.md)

### Presentation and reactive architecture

- [06 — SPPView, BladeOne, and Drishyam](06-sppview-and-bladeone.md)
- [07 — LiveComponent](07-livecomponent.md)
- [08 — SPP Live transports](08-spp-live-transports.md)
- [09 — SPPUX runtime](09-sppux-runtime.md)
- [45 — LiveComponent from zero to kernel](45-livecomponent-from-zero-to-kernel.md)
- [46 — SPP Live transport architecture](46-spp-live-transport-architecture.md)
- [47 — SPPUX browser runtime from zero](47-sppux-browser-runtime-from-zero.md)
- [83 — Live architecture delta](83-live-architecture-delta.md)

### API, security, data, and platform

- [74 — SPPAPI](74-sppapi.md)
- [75 — SPPAuth](75-sppauth.md)
- [76 — Data, cache, crypto, and pools](76-data-cache-crypto-pools.md)
- [77 — Platform services](77-platform-services.md)
- [78 — Maker and SPPDocs toolchain](78-maker-and-docs-toolchain.md)
- [79 — SPPXDB](79-sppxdb.md)
- [80 — September platform reference map](80-september-platform-reference-map.md)
- [84 — Data, identity, and platform architecture delta](84-data-identity-platform-architecture-delta.md)

### Core reference

- [16 — Database, SPPDB, and SPP XDB](16-database-and-storage.md)
- [17 — Authentication and authorization](17-authentication-and-authorization.md)
- [18 — Cache, logging, workflow, and operations](18-cache-logging-workflow.md)
- [19 — CLI and developer tooling](19-cli-and-developer-tooling.md)
- [20 — Testing and source-driven diagnosis](20-testing-and-debugging.md)
- [21 — Enterprise architecture and deployment](21-enterprise-architecture-and-deployment.md)
- [23 — Coming to SPP from other frameworks](23-coming-from-other-frameworks.md)

### Deep tutorial branches

- [40 — Data and Persistence](40-data-entities-sppdb-and-xdb.md)
- [41 — Storage and Content Promotion](41-storage-transfer-and-content-promotion.md)
- [42 — Reporting and Observability](42-reporting-observability-and-diagnostics.md)
- [43 — Queue, Cron, and Background Execution](43-queue-cron-and-background-execution.md)
- [44 — SPPAI](44-spai-and-ai-integration.md)
- [45 — LiveComponent](45-livecomponent-from-zero-to-kernel.md)
- [46 — SPP Live](46-spp-live-transport-architecture.md)
- [47 — SPPUX](47-sppux-browser-runtime-from-zero.md)
- [48 — Polyglot and IPC](48-polyglot-ipc-and-external-application-architecture.md)
- [49 — Multi-Application Enterprise Architecture](49-multi-application-enterprise-deployment.md)
- [56 — Middleware and Pipeline](56-middleware-and-pipeline.md)
- [57 — Events and SPPEvent](57-events-and-sppevent.md)
- [58 — Registry and Dependency Injection](58-registry-and-dependency-injection.md)
- [59 — Modules, manifests, and scaffolding](59-modules-manifests-and-scaffolding.md)
- [66 — Same Problem, Multiple SPP Solutions](66-same-problem-multiple-spp-solutions.md)
- [67 — Architecture Anti-Patterns](67-architecture-antipatterns-and-mistakes.md)
- [68 — How to Read the SPP Source](68-reading-the-spp-source.md)
- [69 — Enterprise Reference Case Study](69-enterprise-reference-case-study.md)

### Migration and coverage

- [70A — Framework Porting Playbooks](70a-framework-porting-playbooks.md)
- [70 — Porting to SPP](70-porting-to-spp-from-other-frameworks.md)
- [24 — Tutorial curriculum](24-tutorial-curriculum.md)
- [28 — Framework feature inventory](28-framework-feature-inventory.md)
- [29 — Feature coverage roadmap](29-feature-coverage-roadmap.md)
- [60 — Handbook completion plan](60-handbook-completion-plan.md)
- [61 — SPP Learning Roadmap](61-spp-learning-roadmap.md)
- [62 — Continuous Task Desk Curriculum](62-continuous-task-desk-curriculum.md)
- [63 — Feature Evidence and Status Model](63-feature-evidence-and-status-model.md)
- [64 — Handbook Documentation Quality Gate](64-handbook-documentation-quality-gate.md)

## Canonical learning loop

**Learn → Build → Test with Parikshak → Deliberately break → Diagnose → Trace source → Learn when not to use it.**

A subsystem is not fully learned merely because its reference chapter has been read.

## Source-first rule

The handbook never treats the existence of a class, method, scaffold, or documentation paragraph as proof of a broad enterprise guarantee. Distributed consistency, transaction semantics, correlation propagation, protocol security, AI recovery, content-promotion guarantees, and similar claims must be tied to concrete implementation/test evidence before being presented as current SPP behavior.

For the release checklist used when adding or revising chapters, see [64 — Handbook Documentation Quality Gate](64-handbook-documentation-quality-gate.md).
