# SPP Framework: Technical Wiki

Welcome to the technical documentation for the SPP (Satya Portal Pack) Framework 2.0. This wiki provides a modular, deep-dive exploration of the framework's internal mechanics.

## 🏛️ [Basic Architecture](index.md)
*   [The Orchestrator Shell Model](index.md#the-orchestrator-shell-model)
*   [Inversion of Control (IoC)](index.md#inversion-of-control)

## 📦 Core Components
*   [**Architecture & App Structure**](architecture-and-app-structure.md): Core layers, directory layout, app structures, modules, config, services, and runtime boundaries.
*   [**Full SPP API Reference**](spp-full-api-reference.md): Runtime APIs, utility APIs, module catalog, architecture map, and usage patterns.
*   [**Application Development**](application-development.md): Creating, configuring, developing, testing, and troubleshooting SPP apps.
*   [**SPPUX Application Development**](sppux-application-development.md): Step-by-step SPPUX app development with PHP and reactive component examples.
*   [**Scheduler**](core-scheduler.md): Context detection, routing, and process management.
*   [**Booting & App Loading**](booting-and-app-loading.md): Full bootstrap sequence, app discovery, context detection, and middleware loading.
*   [**App Lifecycle**](app-lifecycle.md): Application instantiation, bootstrap level, and environment setup.
*   [**Middleware Pipeline**](middleware.md): Onion-style request/response layers, MiddlewareKernel, global and route-level middleware, built-in inventory, and CLI commands.
*   [**Registry**](registry.md): Global state and hierarchical storage.
*   [**Service Container**](container.md): Dependency Injection (DI), PSR-11, and auto-wiring.
*   [**Event System**](event-system.md): Stage-based execution, overridable handlers, and subscriber patterns.
*   [**View Rendering**](view-rendering.md): The overridable rendering pipeline and template engines.
*   [**Caching Systems**](caching.md): Orion, Redis, Asset Orchestration, and Memory registries.
*   [**Path Resolution**](path-resolution.md): Portability logic and the "Smart Path" algorithm.
*   [**Polyglot Interop**](polyglot-architecture.md): Cross-language communication and shared state.
*   [**SPP XDB**](sppxdb.md): Tier-1 Native XML Database with Global ACID, GraphQL, and Blockchain Audit.
*   [**SPP InterDB**](sppinterdb.md): Federated Data Aggregation & GraphQL Gateway.
*   [**Admin Panel**](admin-panel.md): The Developer Workbench and System Orchestration UI.
*   [**LiveService**](liveservice.md): Unified Reactive Architecture & SAJAX-style Services.
*   [**SPPUX Next & SPPEX**](sppux.md): Zero-Build Native UI Framework and SPA Engine.
*   [**Mobile Studio Pro**](mobile-studio.md): Elite Visual Builder for Cross-Platform Apps.
*   [**Core Modules**](modules/index.md): Deep-dive into SppView, SppEntity, SppLogger, etc.

---

## Architecture Overview

### The Orchestrator Shell Model
SPP 2.0 functions as a **Passive Orchestrator**. It provides the lifecycle stages and structural shell, while applications (like Lekhak) and modules provide the specific business logic. This is achieved through:
*   **Middleware Pipeline**: An onion-style request processing engine for security and transformation.
*   **Event Pipeline**: 4-stage lifecycle hooks with overridable default handlers for IoC.
*   **Shared Registry**: A hybrid memory/file-based state manager for polyglot and inter-app communication.

### Inversion of Control (IoC)
The framework defines **what** needs to happen (e.g., "Detect Context", "Render Page"), while the applications and modules define **how** it happens. Core components fire events that modules can "override" to completely replace default framework behavior (e.g., custom routing or custom template engines).

### Performance & Scalability
*   **Asset Orchestration**: Automatic bundling and minification of frontend resources.
*   **Orion Cache**: High-performance module registry for zero-I/O bootstrapping.
*   **Distributed Task Queue**: Cross-process job execution for long-running tasks via the shared registry.
*   **DI Container**: PSR-11 compliant dependency management with recursive auto-wiring.

---
[Core Modules](modules/index.md) | [App Development Guide](../lekhak/index.md)
