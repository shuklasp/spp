# 65. Canonical Chapter Map

This page defines how to interpret the large handbook during the transition from the original reference-oriented chapters to the newer beginner-first tutorial chapters.

## Rule

For learning SPP from zero, prefer the **hands-on/tutorial chapter** listed below.

For concise implementation lookup, use the **reference chapter**.

When the two differ, the handbook evidence policy applies: source and tests outrank prose.

| Subject | Learning-first chapter | Reference / deep-dive |
|---|---|---|
| Frameworks 101 | `50-frameworks-101-and-how-spp-builds-on-them.md` | `01-getting-started.md` |
| SPP concept map | `04-framework-to-spp-concept-map.md` | — |
| Application/context | `33-tutorial-core-01-first-spp-application.md` | `02-kernel-scheduler.md` |
| Middleware | `32-tutorial-core-02-middleware-pipeline.md` | `14-middleware-and-request-pipeline.md` |
| Events | `33-tutorial-core-03-events.md` | `04-events-and-event-handlers.md` |
| Registry / DI | `34-tutorial-core-04-registry-and-di.md` | `03-registry-and-container.md` |
| Configuration | `35-tutorial-core-05-configuration-settings.md` | configuration/reference material |
| Routing | `36-tutorial-core-06-routing-and-dispatch.md` plus the routing paradigm/CLI chapters | `15-routing-and-request-dispatch.md` |
| Modules | `37-tutorial-core-07-modules.md` | `05-modules-and-manifests.md` |
| Views/forms | `38-tutorial-core-08-sppview-views-forms.md` | `06-sppview-and-bladeone.md` |
| Data/XDB | `40-data-entities-sppdb-and-xdb.md` and XDB advanced branch | `16-database-and-storage.md` |
| Authentication/security | security tutorial branches | `17-authentication-and-authorization.md`, `10-security-and-runtime-contracts.md` |
| Parikshak | Parikshak branch | `20-testing-and-debugging.md`, `26-parikshak-testing.md` |
| API | API branch | API framework reference |
| Workflow | Workflow branch | workflow reference |
| Storage/transfer | storage/transfer branch | migration reference |
| Reporting/observability | reporting branch | operations reference |
| Queue/Cron | queue/Cron branch | operations reference |
| AI | AI branch | AI reference |
| LiveComponent | deep LiveComponent branch | `07-livecomponent.md` |
| SPP Live | deep transport branch | `08-spp-live-transports.md` |
| SPPUX | deep SPPUX branch | `09-sppux-runtime.md` |
| Polyglot/IPC | polyglot branch | `10-polyglot-and-external-applications.md` |
| Enterprise architecture | enterprise case study/capstone | `21-enterprise-architecture-and-deployment.md` |

## Duplication policy

Do not maintain two competing tutorials for the same feature. Older chapters may remain as references, but the README should point beginners to one canonical learning path.

When a reference chapter contains a source-verified detail that the tutorial lacks, copy the verified detail into the tutorial rather than asking the reader to reconcile two independent narratives.

## Diagram policy

Architecture diagrams should live in the canonical learning chapter or in a dedicated architecture chapter. Do not maintain multiple slightly different ASCII/Mermaid representations of the same runtime path without a specific reason.
