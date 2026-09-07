# 55. Application Contexts and the Scheduler

One of the easiest ways to misunderstand SPP is to treat an application name as a passive configuration value.

In the current core implementation, the **Scheduler owns the active application context** and coordinates registered `SPP\App` processes.

## 55.1 What is an application context?

Imagine one SPP runtime serving more than one application:

```text
Runtime
├── default application
├── admin application
└── reporting application
```

A request or operation needs to know which application is active.

That selection is the **application context**.

## 55.2 Scheduler's role

`SPP\Scheduler` maintains:

```text
active context name
        +
registered App objects
```

The current source exposes operations including:

```text
regProc()
setContext()
getContext()
hasContext()
getProcObj()
getActiveProc()
withContext()
detectAndEnforceContext()
```

These are implementation facts. Their use in a particular application depends on the execution path.

## 55.3 Context switching

Conceptually:

```mermaid
flowchart LR
    A[Context A active] --> B[Switch to Context B]
    B --> C[Run operation in B]
    C --> D[Restore Context A]
```

`withContext()` exists specifically for this scoped pattern. It switches to the target application, runs a callback, and restores the previous context in a `finally` block when there was a previous context.

This is safer than scattering manual context restoration through application code.

## 55.4 Application status

`SPP\App` defines these statuses:

```text
APP_EXEC
APP_WAITING
APP_STOPPED
APP_ERROR
```

The scheduler's `setContext()` marks the current process waiting and the new process executing when switching between already registered contexts.

A status constant is not, by itself, proof of a complete process supervisor. Treat it as the runtime state model that the current source visibly implements.

## 55.5 How context detection works

The current `detectAndEnforceContext()` implementation:

1. loads shared registry state;
2. registers context and route-resolution events;
3. reads and normalizes `REQUEST_URI`;
4. reads configured applications;
5. fires `event_spp_context_enforce` so the context can be selected/modified;
6. falls back to the configured `base_app` or `default`;
7. fires `event_spp_route_resolve`;
8. stores the resulting context as the active scheduler context.

The important architectural insight is that **context selection is itself extensible through events**.

## 55.6 Application discovery

`SPP\App` reads global settings and, when appropriate, scans `src/*/etc/app.yml` for self-contained applications.

The current implementation also supports a cached configuration file and a skip-discovery switch used for lightweight operations.

Therefore application discovery is not simply “scan every directory on every request.” There is a configuration/cache path as well as dynamic discovery logic.

## 55.7 Why context matters

Once a context is active, application-dependent operations can resolve paths and configuration against that application.

`SPP\App` initializes application directories including data, logs, cache, temporary files, configuration, and modules.

This creates a boundary between:

```text
framework runtime
        ↓
application-specific state
```

That boundary becomes particularly important in multi-application deployments.

## 55.8 Context versus process versus service

Do not use these words interchangeably.

| Term | Meaning in this chapter |
|---|---|
| Application | An `SPP\App` instance/configured application |
| Context | Which application is currently active |
| Process | The `App` object registered with Scheduler in this core model |
| Service | Reusable application/framework functionality; not necessarily a process |
| Worker | A background execution mechanism; not synonymous with context |

This distinction prevents the common mistake of treating “multi-application” as automatically meaning “distributed microservices.”

## 55.9 Context is not authorization

Selecting an application does **not** answer whether the caller is allowed to perform an operation.

Keep these questions separate:

```text
Context:
Which application should handle this?

Authentication:
Who is the caller?

Authorization:
What may the caller do?
```

Security decisions must be traced to the actual authentication/authorization layer.

## 55.10 Failure lab

Deliberately attempt to switch to an unregistered context.

The current scheduler throws an `SPPException` for an unregistered context.

Then attempt to access the active process before any context has been set. The current implementation also throws rather than silently inventing an active application.

This is an excellent example of why controlled failure reveals architecture.

## 55.11 Source trace

Start here:

```text
spp/core/class.scheduler.php
spp/core/class.app.php
```

Then trace callers of:

```text
Scheduler::setContext()
Scheduler::withContext()
Scheduler::detectAndEnforceContext()
App::getApp()
App::getGlobalSettings()
```

Do not stop at the method definitions. The caller tells you why the context transition exists.

## 55.12 Parikshak exercise

Create framework-aware tests for at least:

```text
registered context → becomes active
unregistered context → rejected
withContext() → target context is active during callback
withContext() → previous context is restored afterward
```

The restoration test is particularly valuable because context leaks are difficult to diagnose once unrelated operations start running under the wrong application.

## 55.13 When not to use multiple contexts

If an application is small and has one clear runtime boundary, adding several application contexts may add complexity without solving a real problem.

Use multiple contexts when there is a concrete application-boundary requirement and keep ownership, configuration, data, and security boundaries explicit.

## 55.14 Architectural takeaway

The Scheduler is not merely a cron scheduler despite its name.

In the core source examined here, it is a **context and process coordination component** whose responsibilities include active application selection, context switching, and request-derived context detection.

That distinction is foundational for understanding SPP's multi-application architecture.
