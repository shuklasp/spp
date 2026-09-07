# 72 — Current SPP Source Rescan — 2026-09-07

## Purpose

The SPP repository changed substantially after the earlier August documentation baseline. This chapter records the September 7 rescan and tells the handbook what must be taught or revised.

## Source baseline

The latest repository source commit inspected for this rescan is `9315a847fb6b79c00879b9c4abca712bbc6a92ed` (`Sanitised SPP`, September 7, 2026). A preceding commit, `9b793303d72751842fa37ddb9df428e615b73222`, explicitly records creation of `sppdocs` as part of the repository update.

The handbook must therefore stop treating the August 21 source snapshot as the only current baseline. Older chapters remain useful historical/reference material, but current API and architecture statements must be reconciled with the September source.

## Newly important first-party areas found in the current source

The `spp/modules/spp/` tree now exposes a broader first-party platform surface, including:

- `sppapi` — API controllers, dispatchers, OpenAPI support, subscribers, live actions, API resources/responses, pagination, and route model binding;
- `sppauth` — authentication plus guards, policies, field policy, RBAC, MFA, magic links, OAuth server support, SCIM handling, audit logging, and rate limiting;
- `sppcache` — cache manager, cache commands, module initialization, and a `src` implementation tree;
- `sppcrypto` — Vault support, MPC key sharding, and crypto commands;
- `sppdb` — the principal database subsystem;
- `sppdbpool` — database-pooling as a distinct first-party concern;
- `sppenv` — environment/runtime configuration concerns;
- `sppintegrations` — integration infrastructure;
- `spplang` — language/internationalization support;
- `spplive` — live transport/runtime infrastructure;
- `spplogger` — logging infrastructure;
- `sppmaker` — framework/application generation tooling;
- `sppmedia` — media/file-oriented platform support;
- `drishyam` — presentation/rendering integration;
- `dbconfig` and related configuration support.

These are not merely names to append to a feature list. Each needs to be classified by responsibility, activation model, public API, configuration, CLI/scaffold surface, and source location.

## SPP API architecture has expanded

The current `sppapi` module contains dedicated directories for Controllers, Dispatchers, OpenAPI, and Subscribers. It also contains implementation classes for API documentation, live actions, AJAX/API handling, the API facade, API resources, API responses, pagination, and route model binding.

The tutorial consequence is important: API development should be taught as a coherent SPP subsystem rather than as a thin example of returning JSON from a controller.

The API learning branch should cover:

1. endpoint responsibility;
2. dispatch;
3. structured API responses;
4. resources;
5. pagination;
6. model binding;
7. middleware/security;
8. OpenAPI/documentation generation;
9. live/AJAX boundaries; and
10. testing.

## SPP authentication is broader than a basic login tutorial

The current `sppauth` source contains explicit implementations for anonymous users, audit logging, field policy, guard interfaces, magic links, MFA, OAuth server behavior, policy registry, rate limiting, RBAC, SCIM handling, the main authentication facade, and groups.

The handbook therefore needs a layered identity curriculum:

```text
Authentication
      ↓
Guard / identity
      ↓
Groups / roles / rights
      ↓
Policies / field policy
      ↓
MFA / magic link / OAuth / SCIM
      ↓
Rate limiting / audit logging
```

Each layer must be documented separately so readers do not confuse authentication (who the user is) with authorization (what the user may do) or identity lifecycle/integration (how identities enter and leave the system).

## Cache is a first-class subsystem

The current source contains an `SPPCacheManager`, command support, module initialization, configuration, and a source tree. Cache should therefore be represented as its own framework subsystem in the handbook rather than appearing only as a paragraph inside database/storage documentation.

The future cache chapter should explain:

- why caching exists;
- cache keys and values;
- lifecycle/invalidation;
- framework integration;
- CLI/operations support;
- backend selection where the source supports it; and
- interaction with SPPDB/XDB and application contexts.

## Cryptography and secret management require their own treatment

The current `sppcrypto` module contains `Vault`, `MpcKeySharder`, and a command area. The handbook must not reduce this to generic “encryption” language.

The curriculum should distinguish:

- cryptographic operations;
- secret storage/management;
- key material handling;
- key sharding;
- command-line administration; and
- application security boundaries.

Security claims must remain tied to actual implementation evidence.

## SPPDB and SPPDBPool are separate architectural concerns

The current source exposes both `sppdb` and `sppdbpool`. The handbook should explain the distinction instead of implying that connection pooling is automatically part of every database operation.

A useful teaching model is:

```text
Application
    ↓
SPPDB data abstraction
    ↓
connection/driver layer
    ↓
optional pooling infrastructure where configured
    ↓
database engine
```

The exact pooling behavior must be documented from the current source and configuration rather than inferred from the module name.

## SPPLive and SPPUX remain distinct layers

The current repository continues to expose a dedicated `spplive` module and generated documentation for the SPP Live and SPPUX ecosystems. The handbook should preserve the layered explanation:

```text
LiveComponent
     ↓
SPP Live
     ↓
transport/runtime boundary
     ↓
SPPUX/browser runtime
```

The purpose of this separation is explanatory as well as architectural: it gives developers three distinct places to diagnose reactive behavior.

## SPPDocs is now a framework capability to investigate

The September source documentation exposes an `SppDocs` namespace with classes including `SPPDocGenerator` and `SPPRouteDocCollector`. This is evidence that documentation generation/collection is now represented in the SPP ecosystem.

This **does not mean the handbook should teach how to host the handbook website**. Instead, SPPDocs belongs in the framework tooling/reference curriculum: developers should learn what the framework's documentation-generation capability does, how it discovers framework/API information, and where it fits in the developer-tooling architecture.

## Developer tooling surface has expanded

The repository contains `sppmaker` and SPP documentation-generation infrastructure in addition to the established CLI/scaffolding material. The handbook should therefore connect:

```text
CLI
 ↓
Maker/scaffolding
 ↓
application artifacts
 ↓
framework runtime
```

and separately:

```text
source/API/routes
 ↓
SPPDocs collectors/generators
 ↓
documentation artifacts
```

These are different forms of framework meta-programming and should not be conflated.

## Documentation impact matrix

| Current area | Handbook action |
|---|---|
| SPPAPI | Expand API branch to cover controllers, dispatchers, OpenAPI, subscribers, resources, responses, pagination, model binding, live actions and AJAX. |
| SPPAuth | Expand identity branch into authentication, authorization, MFA, OAuth, SCIM, policies, rate limiting and audit logging. |
| SPPCache | Add a dedicated cache architecture/tutorial chapter. |
| SPPCrypto | Add secret/key-management and cryptographic architecture chapter. |
| SPPDB | Re-audit current implementation and examples. |
| SPPDBPool | Add a separate pooling chapter and source map. |
| SPPEnv | Add environment/configuration chapter where current source supports it. |
| SPPIntegrations | Add integration architecture chapter. |
| SPPLang | Expand internationalization/language chapter. |
| SPPLive | Re-audit transports and handlers against current source. |
| SPPLogger | Expand logging/diagnostics chapter. |
| SPPMaker | Expand scaffolding/meta-programming chapter. |
| SPPMedia | Add media/file platform branch. |
| SPPDocs | Add framework tooling/reference chapter; do not turn it into a handbook-hosting tutorial. |
| SPPXDB | Re-audit XDB facade, engines, ACL, locking, observers, validation, queries, and controllers. |
| Drishyam/SPPView | Re-audit rendering and ViewTag examples. |

## Editorial rule for the September baseline

Do not rewrite every existing chapter merely because a new module exists. Update a chapter when the new source changes its mental model, public API, configuration, examples, or architectural boundary. Create a new chapter when the capability represents a distinct responsibility that a normal SPP developer needs to understand.

The result should be a handbook that grows with SPP without becoming a directory listing.
