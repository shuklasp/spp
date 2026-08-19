# Volume I — Foundations

## Chapter 1 — Introduction to SPP

SPP (Satya Portal Pack) is a modular PHP application framework whose current source tree combines a kernel/runtime (`spp/core`), module packages (`spp/modules`), application sources (`src` and app-specific trees), rendering infrastructure, LiveComponent support, SPPUX, CLI tooling, event handling, middleware, and polyglot bridges.

This handbook is source-driven. Statements marked **Implemented** are based on the supplied SPP source tree. Statements marked **Architecture** describe relationships directly visible in the implementation. Proposed extensions are explicitly labeled and are not presented as current functionality.

## 1.1 The Architectural Center

The framework has several collaborating runtime centers rather than one monolithic kernel class.

```text
┌──────────────────────────────────────────────────────────────────────┐
│                           SPP RUNTIME                                │
├──────────────────────────────────────────────────────────────────────┤
│ Scheduler                                                            │
│  ├─ application-process registry                                    │
│  ├─ active application context                                      │
│  └─ context switching / context execution                           │
├──────────────────────────────────────────────────────────────────────┤
│ Registry                                                             │
│  ├─ hierarchical key/value registry                                 │
│  ├─ service container adapter                                       │
│  ├─ shared registry storage                                         │
│  └─ directory/class/function registration                           │
├──────────────────────────────────────────────────────────────────────┤
│ Module Runtime                                                       │
│  ├─ module discovery                                                 │
│  ├─ manifest parsing                                                 │
│  ├─ dependency resolution                                            │
│  └─ compiled module registry                                        │
├──────────────────────────────────────────────────────────────────────┤
│ Runtime Services                                                     │
│  ├─ Events / EventHandler / SPPEvent                                 │
│  ├─ MiddlewareKernel                                                 │
│  ├─ Service Providers                                                │
│  ├─ Router                                                            │
│  └─ Security / storage / async services                             │
├──────────────────────────────────────────────────────────────────────┤
│ Presentation & Reactive Runtime                                     │
│  ├─ SPPView / extended Blade integration                            │
│  ├─ ViewTags / components / forms                                   │
│  ├─ LiveComponent                                                    │
│  ├─ SPP Live transport engines                                      │
│  └─ SPPUX JavaScript runtime                                        │
├──────────────────────────────────────────────────────────────────────┤
│ Polyglot / External Integration                                      │
│  ├─ Polyglot bridge factory                                          │
│  ├─ language-specific bridge implementations                        │
│  ├─ daemon services                                                  │
│  └─ external-application integration modules                        │
└──────────────────────────────────────────────────────────────────────┘
```

**Source anchors:** `spp/core/class.scheduler.php`, `spp/core/class.registry.php`, `spp/core/class.sppevent.php`, `spp/core/class.middlewarekernel.php`, `spp/core/class.modulecompiler.php`, `spp/modules/spp/sppview/class.livecomponent.php`, `spp/modules/spp/spplive/`, `spp/modules/spp/drishyam/`, `spp/core/Polyglot/`.

## 1.2 Applications Are Runtime Processes

`SPP\Scheduler` maintains a registry of `SPP\App` objects in a static `$procs` map. The active application is represented by `$AppContext`.

The scheduler exposes:

- `regProc(App $proc)` to register an application process;
- `setContext(string $context)` to select or switch the active process;
- `getContext()` to obtain the active context name;
- `getProcObj(string $pname)` to retrieve a registered application object;
- `getActiveProc()` to retrieve the currently active application.

When context changes, the current `App` is moved to `APP_WAITING`, the target `App` is moved to `APP_EXEC`, and the scheduler changes the active context name.

This is an **implemented multi-application runtime**, not merely a documentation concept.

### 1.2.1 Context execution

The scheduler also exposes `withContext(string $context, callable $callback)`, allowing code to execute within another registered application context and then restore the previous context.

This is the key primitive for code that needs to cross application boundaries inside a single SPP runtime.

## 1.3 Application Discovery

`SPP\App::getGlobalSettings()` loads global settings and can dynamically discover application definitions by inspecting `src/*/etc/app.yml` under the configured SPP application directory. Discovered application settings are merged with existing application configuration and cached into the application's system configuration cache.

The application object also resolves application-specific paths including:

- source directory,
- configuration directory,
- module-configuration directory,
- module directory,
- data directory,
- log directory,
- cache directory, and
- temporary directory.

## 1.4 The Registry Is Two Things

The class `SPP\Registry` contains two distinct mechanisms that should not be conflated.

### 1.4.1 Hierarchical registry

`register()`, `get()`, `remove()`, and related methods maintain a hierarchical key/value tree. Dotted names are normalized to `=>` path separators.

Examples of capabilities visible in the implementation include:

- hierarchical values,
- lockable configuration branches,
- directory/class/function registries,
- shared state under the `__shared` namespace.

### 1.4.2 IoC service container

`Registry::container()` lazily creates an `SPP\Core\Container`. The Registry exposes:

- `bind()`;
- `singleton()`; and
- `make()`.

The handbook therefore documents **Registry data storage** and **dependency injection** as related but technically separate APIs.

## 1.5 Shared Registry Storage

When a registry entry is registered beneath `__shared`, the Registry marks the shared state dirty and schedules synchronization at shutdown. The implementation can select Redis shared storage when Redis is enabled and available, otherwise it uses file-backed shared storage. A runtime failure in Redis storage can fall back to file storage.

This is an important enterprise feature because it allows selected registry state to cross process boundaries without making the entire registry global.

## 1.6 Event Architecture

SPP has an event system centered on `SPP\SPPEvent` plus the older/compatibility-facing `SPP\EventHandler` abstraction.

`SPPEvent` maintains:

- listener registrations,
- event definitions,
- event boot state, and
- collected trace state.

The runtime supports:

1. explicit listener registration through `listen()`;
2. YAML event definitions;
3. attribute-based discovery for methods carrying `#[SPP\Attributes\On]`;
4. listener priorities;
5. overridable events;
6. propagation stopping through `EventParams`;
7. before/main-or-inline/after event stages.

### 1.6.1 Event execution shape

```text
fireEvent(event, params)
        │
        ▼
 before_<event>
        │
        ├── propagation stopped? ── yes ──► finish
        │
        ▼
 override handler OR inline handler/default handler
        │
        ▼
 <event> listeners
        │
        ▼
 propagation stopped?
        │
        ▼
 after_<event>
```

The exact implementation is in `spp/core/class.sppevent.php`. Earlier handbook drafts that described a generic publish/subscribe bus should be read as simplified terminology; the actual runtime has explicit **before**, **main/override**, and **after** hook stages.

## 1.7 Middleware

SPP includes a `MiddlewareInterface`, a `MiddlewareKernel`, middleware implementations, API middleware, and security middleware. The repository includes CSRF, rate-limiting/throttling, API authentication, security headers, and audit trace-context middleware.

Middleware is therefore documented separately from events and hooks.

## 1.8 Modules

The module architecture is implemented through module manifests, module discovery, activation registries, dependency resolution, and a compiled registry cache.

The source contains both XML and YAML module registries, including application-local registries under `etc/apps/*` and module manifests such as `spp/modules/spp/sppview/module.yml`.

The compiled module registry performs dependency traversal and explicitly detects circular dependencies. Missing or inactive dependencies cause a `MissingDependencyException` during dependency resolution.

## 1.9 Rendering and Reactive Layers

SPP's presentation stack is broader than a single template engine.

The source includes:

- `class.viewcompiler.php`;
- `class.viewrenderer.php`;
- `class.viewrouter.php`;
- `class.viewtag.php`;
- `class.viewassetmanager.php`;
- `class.phpcomponent.php`;
- `class.livecomponent.php`;
- forms and validators;
- Drishyam's Blade integration (`class.sppblade.php` and related classes);
- SPP Live transport engines; and
- the SPPUX JavaScript runtime.

The handbook therefore treats **SPPView**, **LiveComponent**, **SPP Live**, **Drishyam**, and **SPPUX** as related but separate subsystems.

## 1.10 SPPUX Is a Real Client Runtime

The current SPPUX source describes a modular client runtime with separate modules for:

- reactive signals and computed state;
- batched scheduling;
- tagged-template rendering;
- event delegation;
- keyed DOM reconciliation; and
- error boundaries.

The primary facade `spp/modules/spp/drishyam/js/sppux.js` imports these modules and re-exports the public runtime primitives, including `Signal`, `Computed`, `effect`, `batch`, `createStore`, `html`, `Fragment`, and `BaseComponent`.

This is materially different from treating SPPUX as a collection of UI widgets.

## 1.11 Polyglot Architecture Is Implemented

The framework contains a `SPP\Core\Polyglot` bridge family, including a bridge interface, factory, compiler/default bridge, and language-specific bridges for .NET, Go, and Java. The tree also contains runtime assets and daemon services for C++, .NET, Go, Java, Node.js, Perl, and Python.

Existing documentation also covers polyglot commands and external-application integration. These facilities will be documented as **implemented integration capabilities** only where source and tests support the claim.

## 1.12 Documentation Rule

The authoritative order for this handbook is:

1. executable framework source;
2. framework tests and fixtures;
3. configuration/manifests used by the source;
4. existing project documentation;
5. architectural interpretation.

A statement is not promoted into a normative specification merely because it appears in an older document. Conflicts are resolved in favor of current executable source and tests.

---

### Source map for this chapter

- Kernel: `spp/core/class.scheduler.php`, `spp/core/class.app.php`, `spp/core/class.registry.php`
- Events: `spp/core/class.sppevent.php`, `spp/core/class.eventhandler.php`, `spp/core/class.eventparams.php`
- Modules: `spp/core/class.module.php`, `spp/core/class.modulecompiler.php`, `spp/core/class.moduleinstaller.php`
- Middleware: `spp/core/class.middlewarekernel.php`, `spp/core/MiddlewareInterface.php`
- LiveComponent: `spp/modules/spp/sppview/class.livecomponent.php`
- Live transports: `spp/modules/spp/spplive/`
- SPPView: `spp/modules/spp/sppview/`
- Drishyam/SPPUX: `spp/modules/spp/drishyam/`
- Polyglot: `spp/core/Polyglot/`, `spp/services/`, `spp/lib/`
