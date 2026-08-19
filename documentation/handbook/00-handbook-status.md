# SPP Handbook — Research Status

## Purpose

This handbook is being reconstructed from the supplied SPP source tree. The source code is treated as authoritative; existing documentation is supporting evidence, not the final authority.

## Evidence levels

- **Implemented:** directly verified in source and/or tests.
- **Documented:** present in repository documentation but not yet fully source-audited.
- **Derived:** architectural interpretation of implemented behavior.
- **Proposed:** future design guidance; never presented as current framework behavior.

## Source roots used for research

- `spp/core/` — kernel, scheduler, registry, container, events, middleware, security, routing, polyglot bridges.
- `spp/modules/spp/` — first-party modules, including SPPView, SPP Live, Drishyam, SPPUX, database, auth, API, audit, workflow, integrations, and related modules.
- `spp/modules/contrib/` — contributed modules and external-app adapters.
- `spp/tests/` and module tests — executable evidence for behavior.
- `spp/docs/` and repository `docs/` — supporting documentation and tutorials.

## Research corrections

Earlier chat-only drafts contained several assumptions that were stronger than the current source evidence. Those drafts are not considered canonical. In particular, the handbook will not claim generic features such as a universal distributed event bus, arbitrary DOM patch transport, or lifecycle methods that are not present in the inspected implementation.

## Planned research domains

1. Kernel and Scheduler
2. Application discovery and context execution
3. Registry data tree and IoC container
4. Module manifests, registries, compiler cache, installer, and dependency resolution
5. EventHandler + SPPEvent + EventParams
6. MiddlewareKernel and security middleware
7. Routing and route attributes
8. SPPView compiler, renderer, ViewTags, forms, validators, assets, and PHP components
9. Drishyam and extended Blade integration
10. LiveComponent state, attributes, rendering, validation, dispatch, streaming, downloads, and transport engines
11. SPP Live engines: AJAX fallback, SQLite, Redis, SSE, and WebSocket
12. SPPUX reactive runtime and UI modules
13. Polyglot bridges and daemon services
14. External application integration
15. CLI commands and generators
16. Storage, database, workflow, audit, cache, logging, deployment, and testing

## Canonical-source policy

Every substantive handbook page must identify its source anchors. Claims about behavior should be traceable to executable code, tests, configuration, or a repository document that is itself backed by executable behavior.
