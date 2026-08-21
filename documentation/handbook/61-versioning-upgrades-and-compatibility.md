# 61. Versioning, Upgrades, and Compatibility

Framework knowledge is not complete if it only explains how to build the first version of an application. Real applications live through upgrades.

## 61.1 Separate four kinds of change

When SPP changes, identify which kind of change you are dealing with:

```text
runtime code change
configuration change
application data/schema change
content/deployment artifact change
```

These may require different migration strategies.

## 61.2 Application upgrade lifecycle

A safe upgrade should look conceptually like:

```mermaid
flowchart LR
    A[Current application] --> B[Inventory dependencies]
    B --> C[Read SPP changes]
    C --> D[Run tests]
    D --> E[Update code/config]
    E --> F[Run migrations]
    F --> G[Run full test suite]
    G --> H[Stage]
    H --> I[Verify]
    I --> J[Promote]
```

## 61.3 What to inventory before an upgrade

Record:

- enabled modules;
- module versions where applicable;
- application-local modules;
- custom middleware;
- custom event handlers;
- route/page definitions;
- forms/entities;
- SPPDB/XDB migrations;
- custom CLI commands;
- LiveComponent and SPP Live integrations;
- SPPUX assets/runtime dependencies;
- polyglot bridges;
- scheduled/queued work;
- deployment/transfer conventions.

## 61.4 Why source-first compatibility matters

A documentation page may describe an older behavior while current source implements another behavior.

For upgrade work, prefer:

```text
current source
→ current tests
→ current consumed configuration
→ current documentation
```

The handbook should call out known compatibility changes instead of silently presenting old examples as current API guarantees.

## 61.5 Migrations are not replacements for application testing

A migration can update data successfully while breaking application behavior.

Therefore:

```text
migration success
≠
application upgrade success
```

Test:

- routes;
- middleware;
- events;
- views;
- APIs;
- permissions;
- background work;
- reactive interactions;
- reporting;
- integrations.

## 61.6 Configuration compatibility

When configuration changes, verify:

```text
key still exists?
meaning unchanged?
default unchanged?
new required key?
old key ignored or rejected?
app-local override still loaded?
compiled/cache artifact regenerated?
```

This is especially important for configuration that participates in compiled registries, routing, views, modules, or runtime caches.

## 61.7 Rollback planning

Before a production upgrade, answer:

> Can application code be rolled back without losing data?

If the answer is not obviously yes, the migration needs an explicit compatibility strategy.

A common safe pattern is:

```text
old code supports old + new data shape
→ migrate data
→ deploy new code
→ verify
→ remove old compatibility path later
```

Whether this pattern applies to a particular SPP subsystem must be verified from its actual migration behavior.

## 61.8 Content promotion and upgrades

When the application uses offline preparation and live promotion, coordinate:

```text
framework version
application version
schema/data version
content package version
migration version
```

These versions should not be treated as automatically interchangeable.

## 61.9 Tutorial exercise

Take the Task Desk application and simulate an upgrade:

1. add a new task field;
2. update the entity;
3. create the migration;
4. update forms;
5. update API output;
6. update LiveComponent state if necessary;
7. update SPPUX presentation if necessary;
8. run Parikshak;
9. stage and verify;
10. document rollback/recovery.

The goal is to experience a framework upgrade as an architectural operation rather than a package download.
