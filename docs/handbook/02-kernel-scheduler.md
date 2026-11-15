# Volume II — Kernel

## Chapter 2 — Scheduler and Application Contexts

**Evidence:** `spp/core/class.scheduler.php`, `spp/core/class.app.php`

The SPP Scheduler is a context and application-process manager. The implementation keeps a static map of registered `SPP\\App` objects and a current application context name.

## 2.1 Scheduler state

The scheduler tracks two things that matter for normal application code: the active context and the registered application objects.

```mermaid
flowchart TB
    S[Scheduler] --> A[Active context]
    S --> P[Registered applications]
    P --> D[Default]
    P --> B[Application A]
    P --> C[Application B]
    P --> E[Application C]
```

The diagram is deliberately small: it shows the relationship that developers need without implying ownership of every subsystem by `Scheduler`.

The important point is that an application is represented by an `SPP\\App` object and registered through `Scheduler::regProc()`.

## 2.2 Registering an application process

`Scheduler::regProc(App $proc)` obtains the process name from `App::getName()` and stores the object in the internal process map if that name is not already present.

```php
Scheduler::regProc($app);
```

Registration is idempotent with respect to an existing process name: an already-registered name is not replaced by `regProc()`.

## 2.3 Selecting the active context

`Scheduler::setContext()` performs validation and context switching.

```mermaid
flowchart TD
    A[Select target context] --> B[Normalize context]
    B --> C{Registered process}
    C -- No --> X[Throw exception]
    C -- Yes --> D{Already active}
    D -- Yes --> H[Keep current context]
    D -- No --> E[Current application waits]
    E --> F[Target application executes]
    F --> G[Update active context]
    G --> H
```

The implementation writes an optional debug trace to `SPP_LOG_DIR/spp_context.log` when `SPP_DEBUG` is enabled.

## 2.4 Application status model

`SPP\\App` defines four status constants:

| Constant | Meaning |
|---|---|
| `APP_EXEC` | Active/executing application |
| `APP_WAITING` | Registered but not the current execution context |
| `APP_STOPPED` | Stopped application |
| `APP_ERROR` | Application in error state |

`App::setStatus()` accepts only these four values.

## 2.5 Accessing context-bound application state

The Scheduler exposes two complementary APIs:

- `getActiveProc()` — the current `SPP\\App` object.
- `getProcObj($name)` — a registered application by name.

Convenience methods such as `getModsConfDir()` delegate into the active `App` object.

## 2.6 Context-scoped execution

`Scheduler::withContext()` is a higher-level context-switching primitive. When the requested context is already active, the callback is invoked directly. Otherwise, SPP switches context, executes the callback, and restores the previous context.

```mermaid
sequenceDiagram
    participant A as Current context
    participant S as Scheduler
    participant B as Target context
    A->>S: Run work in target context
    S->>B: Activate target
    S->>B: Execute callback
    B-->>S: Return result
    S->>A: Restore previous context
```

This pattern is useful for code that needs to inspect or operate on another registered application without permanently changing the caller's context.

## 2.7 URI-driven context detection

`Scheduler::detectAndEnforceContext()` derives an application context from the request URI and configured application `base_url` values.

The implementation:

1. loads shared Registry state;
2. registers context-enforcement and route-resolution events;
3. normalizes the request URI;
4. reads global application settings;
5. fires `event_spp_context_enforce` with the URI, application map, and a nullable context;
6. selects the matched application or configured `base_app` fallback;
7. fires `event_spp_route_resolve`, allowing the event pipeline to influence the selected context; and
8. stores the final context in `Scheduler::$AppContext`.

This is a concrete example of SPP's event system influencing scheduler behavior.

## 2.8 DomainContext and Multi-Domain Execution Context

Beyond selecting which application runs, enterprise SPP deployments frequently host multiple tenants or independent websites on a single framework installation.

In SPP 2.0+, the Scheduler integrates directly with the **DomainContext** subsystem (`\SPP\Core\Domain\DomainContext` and `\SPP\Core\Domain\DomainContextResolver`).

```mermaid
flowchart TD
    Req["Incoming HTTP or CLI Request"] --> Det["Scheduler::detectAndEnforceContext()"]
    Det --> Res["DomainContextResolver::resolveContext()"]
    Res --> Rec["Match Host against etc/domains.yml"]
    Rec --> DC["Immutable DomainContext"]
    DC --> SchedContext["Scheduler::$domainContext"]
    SchedContext --> AppContext["Scheduler::$AppContext"]
    
    subgraph ContextState["Immutable DomainContext Capabilities"]
        DC --> DId["Domain ID (e.g. portal.example.com)"]
        DC --> DPaths["Filesystem Roots (domains/portal.example.com/)"]
        DC --> DStore["Storage & Isolated Database Context"]
        DC --> DActiveApp["Active Application & Module Context"]
    end
```

### Retrieving Active Domain Context
Applications, services, controllers, and background workers can access the active domain context at any time:

```php
use SPP\Scheduler;

// 1. Get the full immutable DomainContext object
$domainContext = Scheduler::getDomainContext();

$domainId   = $domainContext->getDomainId();       // e.g. "portal.example.com"
$domainRoot = $domainContext->getDomainPath();     // e.g. "/var/www/spp/domains/portal.example.com"
$activeApp  = $domainContext->getActiveApp();      // e.g. "school1"
$isMulti    = $domainContext->isMultiDomain();     // true if mode: multi

// 2. Convenience shortcut for active domain identifier
$currentDomain = Scheduler::getDomainId();
```

### Stack-Based Domain Scoping (`withDomain`)
Just as `Scheduler::withContext()` allows running a routine within another application, `Scheduler::withDomain()` allows executing work scoped to another tenant domain without permanently altering global execution state.

```php
use SPP\Scheduler;

// Temporarily switch to another domain tenant, execute callback, and restore previous domain
$result = Scheduler::withDomain('portal.example.com', function() {
    $scopedDomain = Scheduler::getDomainId(); // "portal.example.com"
    
    // Database queries, config lookups, and filesystem paths
    // automatically bind to portal.example.com
    return Student::where('status', 'enrolled')->findAll();
});
// Previous domain context is guaranteed to be restored (even if an exception was thrown)
```

## 2.9 Application discovery and App instances

`SPP\\App::getApp()` resolves the requested application name from the explicit parameter or the active Scheduler context. `App` maintains its own static instance map.

`App::getGlobalSettings()` loads the system configuration cache when available. Otherwise it parses `global-settings.yml` and performs dynamic application discovery by scanning `apps/*/etc/app.yml` (canonical repository) and `src/*/etc/app.yml` (compatible repository).

The discovery logic can populate application-specific `etc_path`, `src_path`, `var_path`, and `modules_path` values, normalizing relative paths under the discovered application's source tree.

## 2.10 Enterprise implications

The implemented scheduler model enables a single SPP runtime to host multiple `App` objects while maintaining a single active context. That is materially different from a conventional single-application request bootstrap.

What the source **does not** by itself prove is a general-purpose process isolation mechanism comparable to operating-system processes. The term `process` in this subsystem refers to an SPP `App` runtime object and its execution status.

## 2.11 Comparison with common frameworks

| Concern | Typical PHP MVC | SPP implementation |
|---|---|---|
| Application holder | One application object | Multiple registered `App` objects |
| Active application | Implicit/request-scoped | Explicit Scheduler context |
| Domain context | Often external or ad-hoc | First-class `Scheduler::getDomainContext()` |
| Context switch API | Usually absent | `setContext()` / `withContext()` / `withDomain()` |
| Application status | Often external | `APP_EXEC`, `WAITING`, `STOPPED`, `ERROR` |
| URI-based application selection | Router-specific | Scheduler event-assisted context detection |

## Kernel Hacker note

The Scheduler is intentionally small. It does not contain the module loader, the view compiler, or the event listener implementation. Instead, it provides the **context boundary** through which those systems locate the active application and domain tenant.

### Source map

- `spp/core/class.scheduler.php`
- `spp/core/class.app.php`
- `spp/core/Domain/DomainContext.php`
- `spp/core/Domain/DomainContextResolver.php`
- `spp/core/Domain/DomainRegistry.php`
- `spp/core/Domain/PathResolver.php`