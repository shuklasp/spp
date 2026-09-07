# 60. Handbook Completion Plan

This page is the execution plan for turning `documentation/handbook/` into a complete SPP learning system, not merely a reference collection.

## 60.1 Definition of done

A major SPP subsystem is **complete in the handbook** only when the following are present or explicitly marked not applicable:

| Requirement | Meaning |
|---|---|
| Concept | Explained without assuming framework knowledge |
| General framework model | Explained independently of SPP |
| SPP mapping | Exact SPP mechanism identified |
| Hands-on build | Learner uses the capability |
| CLI/scaffold | Shown when the repository exposes one |
| Parikshak | Test exercise or explicit reason it does not apply |
| Failure lab | Controlled failure and diagnosis |
| Source map | Relevant implementation/tests/configuration landmarks |
| Architecture | Appropriate Mermaid diagram, if useful |
| Comparison | Mapping to familiar frameworks where useful |
| Trade-offs | When to use and when not to use |
| Security/performance | Relevant operational concerns |
| Cross-links | Links to prerequisites and dependent subsystems |
| Evidence status | Implemented/documented/derived/guidance/planned classification |

A feature is not considered complete because it has a chapter title or a source-code description.

## 60.2 Order of execution

### Phase A — Beginner foundation

1. Frameworks 101
2. Framework Concept → SPP Feature Map
3. 30-minute quick start
4. Plain PHP → MVC → SPP Task Desk
5. request lifecycle
6. application contexts and Scheduler

### Phase B — Framework mechanics

7. Middleware/Pipeline
8. Events/SPPEvent
9. Registry/DI
10. Configuration/Settings
11. Routing paradigms
12. Modules/scaffolding
13. SPPView/BladeOne/Drishyam
14. Forms/validation

### Phase C — Persistent applications

15. Entities
16. SPPDB
17. XDB
18. migrations/seeders
19. auth/identity/RBAC
20. web security
21. cache/logging/audit
22. Parikshak

### Phase D — Application capabilities

23. SPPAPI
24. Workflow/approval/wizard
25. Queue/Cron/workers
26. Storage
27. i18n
28. Reporting/observability
29. SPPAI

### Phase E — Reactive architecture

30. LiveComponent
31. SPP Live transports
32. SPPUX
33. LiveComponent + SPP Live + SPPUX combined project

### Phase F — Enterprise architecture

34. Migration/transfer/content promotion
35. diff/revision/audit
36. polyglot/IPC
37. external non-SPP applications
38. multiple SPP applications
39. deployment/failure isolation
40. enterprise capstone

## 60.3 Continuous Task Desk rule

The learner should keep extending one application rather than creating unrelated toy projects.

The Task Desk should progressively acquire:

- pages and routing;
- middleware;
- events;
- services and DI;
- modules;
- forms;
- entities and persistent storage;
- authentication/RBAC/security;
- tests;
- API;
- workflow and approvals;
- jobs and schedules;
- reports;
- LiveComponent;
- SPP Live;
- SPPUX;
- AI-assisted functionality;
- offline-to-live promotion;
- integration with at least one external service;
- a multi-application enterprise topology.

## 60.4 Repository QA pass

Before a handbook release, audit the repository for:

- stale “Coming Soon” claims;
- unsupported guarantees;
- duplicated explanations with conflicting behavior;
- dead internal links;
- code examples that contradict current APIs;
- CLI commands that no longer exist;
- diagrams represented as ASCII instead of Mermaid;
- Mermaid diagrams that do not render on GitHub;
- references to classes/files that do not exist;
- tutorials that do not map to a concrete source implementation;
- modules that appear in the feature inventory but nowhere in the curriculum;
- scaffolds/generators omitted from the relevant tutorial;
- security-sensitive examples lacking validation/authentication context;
- claims about distributed or enterprise behavior that lack implementation/test evidence.

## 60.5 Evidence rules

The canonical handbook uses these status labels:

**Implemented** — executable implementation is directly verified.

**Documented** — repository documentation describes it, but implementation verification is incomplete.

**Derived** — conclusion follows from multiple verified implementation facts.

**Guidance** — architectural recommendation rather than a claim about current runtime behavior.

**Planned/Unverified** — source/docs do not establish the behavior strongly enough to present it as current capability.

Do not upgrade a feature's status merely because its class, generated PHPDoc page, or historical documentation exists.

## 60.6 Chapter QA template

Every major tutorial chapter should eventually contain this sequence:

1. **Problem** — show the pain in plain PHP.
2. **Concept** — explain the general framework idea.
3. **SPP mapping** — identify the actual SPP mechanism.
4. **Build** — create the feature.
5. **Run** — observe it working.
6. **Test** — write the Parikshak or other appropriate test.
7. **Break** — introduce a controlled failure.
8. **Diagnose** — identify the failing SPP layer.
9. **Source trace** — follow the implementation.
10. **Architecture** — understand how the feature interacts with the rest of SPP.
11. **Trade-offs** — decide when not to use it.
12. **Challenge** — extend the feature independently.

## 60.7 Final release bar

The handbook is ready for a major release when a beginner can:

- explain what a framework does;
- explain the SPP runtime in their own words;
- create an SPP application;
- create pages through the supported routing paradigms;
- use middleware and events;
- use Registry/DI and modules;
- render with SPP's presentation stack;
- persist data through the documented abstractions;
- secure the application;
- test it with Parikshak;
- build an API;
- build a workflow;
- run background work;
- build reactive features with LiveComponent;
- understand SPP Live transports;
- build browser reactivity with SPPUX;
- integrate an external application/runtime;
- promote content safely to a live site where supported;
- and trace a production request into the SPP source.

The final criterion is not chapter count. It is **demonstrable competence**.