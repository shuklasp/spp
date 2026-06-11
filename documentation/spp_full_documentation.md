# 🏛️ SPP (Satya Portal Platform) - Universal Enterprise Manual

Welcome to the definitive documentation manual for the **Satya Portal Platform (SPP)**. SPP is a highly robust, enterprise-grade application ecosystem engineered for unyielding security, absolute offline execution sovereignty, zero-latency visual prediction models, declarative HTML-first composition, and synergistic multi-tier architecture.

This manual is segmented into three dedicated perspectives to provide step-by-step tutorials and actionable code examples for every team role.

---

## 📋 Table of Contents
1. [Perspective A: The SPP App Developer Guide](#app-dev)
   - 1.1 [Scaffolding a New Application & AI Blueprints](#scaffold)
   - 1.2 [Sovereign Component Extractions (`import:component`)](#exchange)
   - 1.3 [Isolated Routing & Relative Path Normalization](#routing)
   - 1.4 [Declarative HTML Directives Tutorial (Zero-JS Workflows)](#directives)
   - 1.5 [SPPUX Next & SPPEX: The Native UI Ecosystem](#sppux-next)
   - 1.6 [Localized Domain Event Listeners & Traversal Maps](#events)
2. [Perspective B: The SPP Core Developer Guide](#core-dev)
   - 2.1 [Core Architecture & Lifecycle Orchestration](#kernel)
   - 2.2 [Extending Agentic AI Driver Contracts (`SPPAI`)](#ai-contracts)
   - 2.3 [Cryptographic Merkle-DAG Audits & Lineage Verification](#dag-audits)
   - 2.4 [WASM Sandboxing & Sub-Resource Integrity (SRI) Hashes](#sandboxing)
   - 2.5 [HMR Live-Reload Middleware & OPcache Pre-Warming Loops](#hmr-cache)
   - 2.6 [Zero-Direct-Access Security Enforcement & Drishyam Component Decoupling](#zero-direct-access)
   - 2.7 [XDB Embedded Database & Safe MySQL Parity](#xdb-core)
   - 2.8 [Dynamic Translations, Virtual Fields, and sppdiff Revisions](#translations-virtual-revisions)
   - 2.9 [Distributed Shared Registry & Circuit Breaker](#registry-circuit-breaker)
3. [Perspective C: The SPP User & Administrator Guide](#user-admin)
   - 3.1 [Declarative Visual Island Composer Studio (`drishyam:studio`)](#studio)
   - 3.2 [Cinematic Hardware-Accelerated View Transitions](#transitions)
   - 3.3 [Absolute Air-Gapped Framework Compliance Certification](#sovereignty-check)
   - 3.4 [Workbench System Diagnostics & Live Telemetry](#telemetry)

---

<a name="app-dev"></a>
## 👨‍💻 Perspective A: The SPP App Developer Guide

As an application developer, your objective is to build modular, secure micro-portals rapidly without writing redundant boilerplate or manual state integration script logic.

<a name="scaffold"></a>
### 1.1 Scaffolding a New Application & AI Blueprints
SPP automates standalone workspace creation complete with full SPP branding, standard configurations, and responsive preview wrappers out of the box.

#### Step-by-Step Tutorial:
1. Open your terminal at the framework workspace root.
2. Execute the CLI engine passing your application identifier and optional AI blueprint specifications:
   ```powershell
   php spp.php create:app portal --ai-blueprint="Dark-themed enterprise data visualization portal layout"
   ```
3. **What happens**: The engine outputs the exact physical mapping directory under `src/portal/`. It automatically integrates isolated asset routers, sample PHP execution services, dynamic macro views, and prominent alert widgets capturing your initial blueprint parameters directly inside `src/portal/pages/index.php`.

<a name="exchange"></a>
### 1.2 Sovereign Component Extractions (`import:component`)
Instead of issuing remote calls to external network package repositories, SPP embeds an offline Sovereign Component Exchange straight inside its resource maps.

#### Step-by-Step Tutorial:
1. To import an advanced, pre-vetted interactive UI module straight into your sub-application code paths:
   ```powershell
   php spp.php import:component UI/DataGrid --target=portal
   ```
2. **Result**: A pristine production component file (`DataGrid.sppux.js`) is automatically synthesized directly inside `src/portal/components/UI/`. It ships natively configured with customizable layout attributes alongside pre-bound button mutate commands.

<a name="routing"></a>
### 1.3 Isolated Routing & Relative Path Normalization
Every sub-application maintains self-contained parameters defined in `src/<app>/etc/routes.yml`.

#### Code Example:
```yaml
routes:
  - path: "assets/"
    target: "src/portal/assets/"
    type: "static_asset"
    absolute: false

  - path: ""
    target: "src/portal/pages/index.php"
    type: "page_view"
    absolute: false
```
* **Relative Autonomy**: Because paths do not hardcode root backslashes, the app functions smoothly either accessed flatly at the base domain root (`/`) or routed dynamically via context prefixes (`/portal/`).
* **Absolute Overrides**: If your sub-app needs to project global root hooks overriding base configuration borders, set `absolute: true`.

<a name="directives"></a>
### 1.4 Declarative HTML Directives Tutorial (Zero-JS Workflows)
SPP eliminates manual client scripts by evaluating custom attribute bindings inside `sppux.js`.

#### Actionable Examples:
* **Background Form Posting & Island Target Morphing**:
  ```html
  <form data-spp-post="task.create" data-spp-target="#task-box" data-spp-transition="scale">
      <input type="text" name="taskTitle" placeholder="Task summary..." required />
      <button type="submit">Execute Task</button>
  </form>
  <div id="task-box"></div>
  ```
* **Real-Time Two-Way Variable Hydration**:
  ```html
  <input type="text" data-spp-bind="searchQuery" placeholder="Type query..." />
  <p>Currently querying: <span data-spp-text="searchQuery">None</span></p>
  ```
* **Client Signal Searching Engine**:
  ```html
  <input type="text" data-spp-search="#item-grid" placeholder="Filter cards..." />
  <div id="item-grid">
      <div data-search-name="Analytics Module">Analytics Module</div>
      <div data-search-name="Security Firewall">Security Firewall</div>
  </div>
  ```

<a name="sppux-next"></a>
### 1.5 SPPUX Next & SPPEX: The Native UI Ecosystem
SPP includes a built-in, zero-build frontend framework that mirrors the capabilities of React and Vue, executing entirely natively via `<script type="module">`. The ecosystem comprises 4 tiers totalling **35 native modules** in under 45KB:

* **SPPUX Next** (`sppux.js`): The core runtime offering a **Proxy-based Global Store**, **HTML5 Client-Side Router**, **Native Suspense** (`SPPUX.await`), and a **Web Components Compiler** (`SPPUX.defineElement`).
* **SPPEX** (`sppex.js`): 5 modules porting the top React ecosystem packages:
  * `Query` (React Query), `Form` (React Hook Form), `Motion` (Framer Motion), `Helmet` (React Helmet), `DnD` (dnd-kit).
* **SPPEX Pro** (`sppex-pro.js`): 10 structural primitives:
  * `VirtualList` (react-window), `InfiniteScroll`, `StoreSync` (useLocalStorage), `Machine` (xstate), `Carousel` (react-slick), `Floating` (@floating-ui), `Select` (react-select), `DatePicker`, `Markdown` (react-markdown), `i18n` (react-i18next).
* **SPPEX Ultra** (`sppex-ultra.js`): 20 advanced UI and utility modules:
  * `DataGrid` (ag-grid), `Masonry`, `Resizable`, `Tree`, `Dropzone`, `ContextMenu`, `ColorPicker`, `RangeSlider`, `Rating`, `Skeleton`, `Accordion`, `Timeline`, `Highlight` (syntax highlighter), `AvatarGroup`, `ProgressBar`, `Badge`, `Pagination`, `Breadcrumbs`, `CopyToClipboard`, `WebSocket` (auto-reconnecting).

> All modules are **zero-dependency** and fully **air-gapped sovereign**. See `documentation/framework/sppux.md` for complete API reference.

<a name="events"></a>
### 1.6 Localized Domain Event Listeners & Traversal Maps
SPP applications encapsulate independent lifecycle listeners natively discovered under `src/<app>/events/`.

#### Creating a Local Interceptor Hook:
Create `src/portal/events/UserRegisteredHandler.php`:
```php
<?php
namespace EventHandlers;

class UserRegisteredHandler extends \SPP\EventHandler {
    public function afterHandler(&$params = []) {
        // Triggered symmetrically by core broadcast channels
        @file_put_contents(SPP_APP_DIR . '/var/logs/portal_events.log', "Target event cleanly processed.\n", FILE_APPEND);
    }
}
```

---

<a name="core-dev"></a>
## ⚙️ Perspective B: The SPP Core Developer Guide

Core developers optimize the underlying kernel, extend framework abstraction boundaries, and ensure deterministic system performance.

<a name="kernel"></a>
### 2.1 Core Architecture & Lifecycle Orchestration
1. **Bootstrapping**: Requests pass to `sppinit.php` which maps continuous autoloader protocols and resolves default parameters (`SPP_DEBUG`).
2. **Dual-Loading Webroot Resolution**: The scheduler dynamically cross-references `routes.yml` configuration definitions, allowing targeted fallback application views to load identically via domain roots (`/checkout`) or route mappings (`/default/checkout`).

<a name="ai-contracts"></a>
### 2.2 Extending Agentic AI Driver Contracts (`SPPAI`)
To introduce support for a new model provider, subclass the baseline driver contracts enforcing consistent `callTool` response outputs.

#### Code Example:
```php
<?php
namespace SPPMod\SPPAI\Drivers;

class NewModelDriver implements \SPPMod\SPPAI\AIDriverInterface {
    public function callTool(string $prompt, array $tools): array {
        // Enforce strict openapi tool schemas matching manifest targets
        return ['status' => 'success', 'tool_calls' => []];
    }
    public function structured(string $prompt, array $schema): array {
        return ['extracted_payload' => []];
    }
}
```

<a name="dag-audits"></a>
### 2.3 Cryptographic Merkle-DAG Audits & Lineage Verification
State mutation routines execute `SPPAjax::appendMerkleLineage()` generating chained timing-safe SHA-256 state signatures stored in local verification blocks. Auditors verify mathematical continuity cleanly via the CLI:
```powershell
php spp.php audit:lineage --app=portal
```

<a name="sandboxing"></a>
### 2.4 WASM Sandboxing & Sub-Resource Integrity (SRI) Hashes
* **WASM Micro-Frontends**: Core extensions securely isolate external untrusted computational logic inside strict runtime payload caps using `SPPExt::executeFederatedSandbox()`.
* **SRI Hash Generation**: The `DrishyamRenderer` dynamically signs compiled template responses with SHA-256 inline hash validation tags (`data-spp-sri`), locking root components against untrusted client code vectors.

<a name="hmr-cache"></a>
### 2.5 HMR Live-Reload Middleware & OPcache Pre-Warming Loops
* **Live-Reload Checksums**: The middleware checks disk signature access parameters (`__svc=spp:dev_modcheck`) natively inside `index.php` yielding continuous microsecond document hash comparisons. Client runtimes poll changes to instantiate UI page flashes and HMR morphing instantaneously.
* **OPcache Caching**: `Drishyam::preWarm()` traverses nested layout definitions, pre-storing structural template evaluation trees inside memory maps to drop disk access delays straight down to sub-microsecond retrieval speeds.

<a name="zero-direct-access"></a>
### 2.6 Zero-Direct-Access Security Enforcement & Drishyam Component Decoupling
To enforce ultimate filesystem sovereignty and prevent unauthorized direct HTTP file execution vectors, SPP enforces a framework-wide **Zero-Direct-Access** architecture.

#### A. Static Route Governance (`global-config.yml`)
Setting `block_direct_access: true` instructs the framework dispatcher to block direct HTTP access to code locations (e.g. `/src/lekhak/comp/lekhak.js`) with a `403 Forbidden` header. Applications define authorized delivery paths globally or via module manifests:
```yaml
# module.yml
module:
  name: lekhak
  assets:
    - comp
    - js
    - img
```
During startup, `SPP\Module::register()` scans enabled manifests to build the internal dynamic lookup table (`__asset_routes`), delivering requested payload assets exclusively via controlled dispatcher pathways.

#### B. Component Markup Externalization & Automagic Reconciliation
Inline multi-line HTML string literals are completely decoupled from UI component classes. Markup blueprints are maintained in standalone files (e.g., `comp/templates/LekhakView.html`).
* **Pre-Warmed Ceiling Embeds**: `DrishyamRenderer` scans component subfolders to inject extracted markup directly inside top-level hidden document ceiling wrappers (`<template id="spp-tpl-[component]">`).
* **Automagic Hydration**: `BaseComponent::update()` intercepts components returning empty stubs from `render()`, automatically cloning pre-warmed template buffers and interpolating state data via lightweight DOM tokens (`interpolateTemplate`), keeping JavaScript logic layers completely clean and maximizing rendering speed.

<a name="xdb-core"></a>
### 2.7 XDB Embedded Database & Safe MySQL Parity
The framework features **SPP XDB**, a high-performance, self-contained in-memory XML database engine mapping enterprise storage schemas dynamically to secure local XML structures. XDB implements extensive safe SQL subset features to emulate full MySQL operational parity without requiring heavy server processes.

#### 1. SQL Compatibility Features
* **Set Operations (`UNION` / `UNION ALL`)**: Quote-aware sub-query parsing allows complex data set combinations with optional deduplication.
* **Derived Subqueries inside `FROM`**: Supports executing nested SELECT sub-queries to form derived virtual datasets.
* **Successive Join Chains**: Process any number of successive `JOIN` / `LEFT JOIN` operations with multi-level table aliases, automatically matching fields with prefixed references (`e.id = d.id`).
* **Write Integrity validation (`NOT NULL` & `DEFAULT`)**: Dynamic schemas enforce precise nullability constraints on `INSERT` and `UPDATE` operations, including translation of bareword `NULL` query variables to PHP native nulls.

#### 2. CLI Compatibility Verification Suite
Core developers can audit and run the complete database verification suite through the CLI:
```powershell
php spp.php db:verify
```
Runs arithmetic updates, transactional rollback/commits, schema modifications (Alter column, Drop column, Rename, Modify, NOT NULL constraints), joins, unions, and virtual views in real-time.

<a name="translations-virtual-revisions"></a>
### 2.8 Dynamic Translations, Virtual Fields, and sppdiff Revisions
SPP incorporates advanced modules for local database-driven translation management, schema-less virtual entity fields, and base64-encoded, Gzip-compressed delta-based audit history tracking.

#### 1. Dynamic Translation Engine & spplang Scanner
The `SPPLang` module introduces a sovereign dynamic translation and fallback framework that allows instant translation updates without altering template structures:
* **spplang Directory Scanner**: The static scanner `SPPLang::scanDirectory($scanDir, $locale)` parses files recursively, extracting all translatable string matches wrapped in the standard `__("...")` helper. Extracted keys are automatically registered and persisted to the local sqlite database table (`spp_translations` / `lek_translations`).
* **Universal Global Helper**: The global translation helper `__($key, $locale = null)` is registered universally in the `sppinit.php` bootstrap file. This guarantees global scope availability (`\__`) immediately upon framework boot, preventing any namespace or load-order initialization failures.
* **Fallback Database Override Lookup**: When called, the helper searches the localized translations table for a matching custom translation override with status `active`. If an active override is found, it is returned; otherwise, the lookup gracefully falls back to returning the bare key code.

#### 2. SPPEntity & LekhakNode Virtual Fields
To support rich, custom meta configurations (such as visual design settings or content rating tags) without repeatedly mutating physical database tables, the core `SPPEntity` class implements seamless **Virtual Fields**:
* **Dynamic Property Interception**: Any attribute assigned to an entity that is not defined in the physical schema model is automatically intercepted by the core `__get` and `__set` methods.
* **Fields Data Compression**: Intercepted attributes are packed dynamically inside a single JSON-serialized physical database column named `fields_data`.
* **Transparent Attribute Access**: Upon loading database records (`find_one` / `load`), packed JSON arrays inside the `fields_data` column are transparently unpacked, exposing all dynamic virtual attributes as native object attributes.

#### 3. sppdiff Revisions & Delta Reconstruction Engine
SPP guarantees enterprise-grade historical accountability and auditability for all core entities via the **sppdiff Revisions** engine:
* **Dynamic Table Initialization**: Calling `RevisionManager::boot()` automatically guarantees that the structured audit revision table (`spp_entity_history` / `lek_entity_history`) is fully verified and constructed without manual migrations.
* **Granular Lifecycle Event Interceptors**: Revision tracking hooks cleanly into `entity:before_save` and `entity:after_save` event broadcasts.
* **Gzip-Compressed Delta Storage**: The `RevisionManager::auditEntity()` method automatically decodes the `fields_data` JSON column for both the loaded database copy and the new active entity state, including virtual fields in the computed diff. After filtering out ephemeral columns and the raw `fields_data` string, the remaining granular changes are compressed via `gzcompress` and stored as base64-encoded delta buffers in the history table.
* **Deterministic Reverse Reconstruction**: The high-performance `DeltaEngine::patch($current, $delta)` allows developers to pass any present entity state and revert it backwards to a precise past revision by applying computed deltas in reverse.

<a name="registry-circuit-breaker"></a>
### 2.9 Distributed Shared Registry & Circuit Breaker
The framework utilizes `\SPP\Registry` as the absolute single source of truth for runtime configurations across all interlinked applications.

#### 1. Distributed Storage Adapters
The Registry seamlessly scales across multi-container orchestrations by dynamically discovering and mounting storage adapters (`\SPP\Core\Interfaces\SharedStorageInterface`):
* **Redis Auto-Discovery**: Automatically mounts `RedisSharedStorage` to push cross-application configurations directly into the unified memory cluster.
* **Atomic File Fallback**: Reverts to `FileSharedStorage` (via `flock(LOCK_EX)`) guaranteeing zero corruption when concurrency peaks locally without memory clusters.

#### 2. Embedded Circuit Breaker / Graceful Degradation
To eliminate Single Point of Failure (SPOF) risks, the core Registry intrinsically wraps Redis memory operations in a highly resilient Circuit Breaker logic loop.
* If a remote Redis database drops a connection mid-execution, refuses a request, or times out, the Registry catches the resulting exception.
* The system instantly trips the circuit, gracefully downgrades the active adapter to `FileSharedStorage`, and recursively re-attempts the read/write operation against the local disk.
* **Result**: `100%` fault-tolerance. The framework survives massive infrastructure outages without crashing the master HTTP request or losing distributed shared state configurations.

---

<a name="user-admin"></a>
## 👤 Perspective C: The SPP User & Administrator Guide

Administrators oversee framework status metrics, layout design themes, and enterprise readiness compliance safely.

<a name="studio"></a>
### 3.1 Declarative Visual Island Composer Studio (`drishyam:studio`)
Content managers can fine-tune interface layout themes visually without risking arbitrary stored script injections.

#### Step-by-Step Authoring:
1. Navigate directly to your local workspace domain appending the studio parameter service string:
   ```text
   http://localhost/?__svc=drishyam:studio
   ```
2. **Visual Editor Integration**: A standalone live WYSIWYG control dashboard loads instantly. Modify primary accent properties using native layout input controls alongside text arrays mapped straight to preview fragments.
3. **Zero-XSS Preservation**: Click **Commit Island Overrides**. Parameter targets map straight back to localized `theme.yml` parameter dictionaries safely, maintaining clean separation between configuration flags and raw interface code layouts.

<a name="transitions"></a>
### 3.2 Cinematic Hardware-Accelerated View Transitions
Navigating between standalone application contexts automatically uses native hardware-accelerated **View Transitions API** instructions generated dynamically by Drishyam. Interface screens cross-fade and expand gracefully via OS-native graphics animations without external library scripts.

<a name="sovereignty-check"></a>
### 3.3 Absolute Air-Gapped Framework Compliance Certification
To verify that your repository instance functions completely offline without referencing external CDNs or external web font targets, execute the compliance checker:
```powershell
php spp.php verify:sovereignty
```
**Certified Output Report:**
```text
🛡️ Auditing Absolute Air-Gapped Sovereign Compliance Across Stack...
  🔍 Traversing native framework extension trees and JavaScript components...
  📦 Evaluated 42 core script engines and 29 native sublayer modules.
  ✅ ZERO third-party network reference strings (http://, https://, //cdn) detected.
  🌟 Absolute Sovereign Rating: 100% (Air-Gapped Intranet Production Certified).
```

<a name="telemetry"></a>
### 3.4 Workbench System Diagnostics & Live Telemetry
Access the built-in Administration workbench terminal at `/spp/admin/` to tabulate active core modules alongside continuous memory allocation graphs and active HMAC parameter verifications natively.

---
**SPP Framework Enterprise Sovereign Edition guarantees absolute execution resilience, absolute cryptographic audit transparency, and immediate UI rendering throughput.**
