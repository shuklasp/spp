# Lekhak CMS: Application Registry & Configuration System

Lekhak utilizes a structured, declarative configuration registry defining the application context, core dependencies, service declarations, hooks, and dynamic parameters. This self-documenting architecture ensures consistent framework discovery and runtime decoupling.

---

## 1. Declarative Module Manifest (`module.yml`)

The core CMS metadata, asset pipelines, and services are defined inside [modules/lekhak/module.yml](file:///c:/projects/apache/school1/src/lekhak/modules/lekhak/module.yml):

```yaml
module:
    name: lekhak
    version: "1.0"
    pubname: Lekhak CMS Core
    modgroup: SPP_CMS
    pubdesc: Universal Polyglot CMS Engine with Zero Dependencies.
```

---

## 2. Dynamic Service Container Registration

Lekhak registers critical system handlers as shared, lazy-loaded components inside the SPP Dependency Injection container:

*   **`lekhak.renderer`** (`\SPPMod\Lekhak\Core\Renderer`): Orchestrates Blade, Twig, and SPPUX templates and pipelines.
*   **`lekhak.storage`** (`\SPPMod\Lekhak\Core\StorageOrchestrator`): Validates database schema integrity and manages dynamically generated field tables.

---

## 3. Core Hook & Pipeline Events

Lekhak exposes high-priority event dispatchers permitting modules to hook into lifecycle actions:

*   **`lekhak_render_pipeline`**: Filter hook triggered before sending HTML layouts to browser streams. Used to parse LaTeX/MiKTeX math blocks or custom Wiki tags.
*   **`lekhak_node_presave`**: Fired prior to persisting content records, letting modules sanitize HTML or auto-generate aliases.
*   **`lekhak_node_postsave`**: Fired post-commit, ideal for clearing CDN caches or queueing search indexes.

---

## 4. Managed Configuration Settings

Dynamic parameters are customizable at the application layer or within admin control panels:

*   **`primary_language`**: Configures default localization codes (`en`, `hi`, `fr`).
*   **`default_status`**: Sets initial states of new content nodes (defaults to safe `draft` state).
*   **`enable_canvas`**: Toggles block-based visual canvas layouts.
*   **`shared_registry`**: Synchronizes settings across polyglot microservice contexts.
*   **`task_queue`**: Delegates background operations (like calculations or batch indexings) to asynchronous workers.

---
[Back to Index](index.md)
