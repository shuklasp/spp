# SPP Workspace Agent Rules

## SPP External Partials & Components Referencing Rule

When developing controllers, views, or UI components within the SPP framework, you MUST adhere to the following strict architectural constraints:

1. **Zero Inline HTML Literals**: NEVER write inline HTML string literals within controller actions, service classes, or JavaScript component logic. 
2. **Use External Partials**: ALWAYS reference standalone external `.html`, `.php`, or `.js` files to be inserted or updated at particular places in the main page.
3. **Controller Rendering APIs**: 
   - Use `$this->renderPartial('partials/name.html', $data)` for dynamic partials (HTMX / AJAX).
   - Use `$this->renderStaticPartial('partials/name.html')` for high-performance static fragments (with ETag & Cache-Control).
   - Use `$this->stream('streams/update.php', $data)` for real-time Turbo Streams.
4. **Smart Content Negotiation**: When building `ResourceController` CRUD endpoints, ensure actions inspect `HX-Request` and `Turbo-Frame` headers to automatically resolve and serve corresponding external partials (`partials/{entity}_index.html`, `partials/{entity}_row.php`, etc.).
5. **Template Directives**: Use `@spppartial('partials/file.html', ['key' => 'val'])` in Blade and Twig templates rather than raw PHP includes or manual view locator calls.
6. **Scaffolding Generators**: Whenever a task requires a new partial or stream, generate it using the dedicated CLI tools:
   - `php spp.php make:partial <PartialName.html|.php|.js> [--app=AppName]`
   - `php spp.php make:stream <StreamName.html|.php> [--app=AppName]`

## SPP CLI Security, Concurrency & Documentation Rules

When developing, refactoring, or scaffolding CLI commands, interactive shell built-ins, or deployment orchestration routines within the SPP framework, you MUST adhere to the following strict architectural constraints:

1. **Strict CLI SAPI Guarding**: Any command performing high-privilege system operations, arbitrary execution (`shell`, `tinker`), or direct environment manipulation MUST override `public function isCLIOnly(): bool { return true; }` in its command class. This ensures `CommandManager::execute()` blocks execution from web contexts (`PHP_SAPI !== 'cli'`).
2. **DDL Identifier Sanitization**: When building database migration, DDL generation, or schema inspection commands, NEVER interpolate raw user input into SQL DDL statements. ALWAYS validate table and column names using `SchemaValidator::isValidIdentifier($name)` or the `$this->escapeIdentifier($name)` helper method.
3. **SPPDeploy Distributed Mutex Locking**: All deployment orchestration commands (e.g., `deploy:push`, `deploy:rollback`, `deploy:token:rotate`) MUST prevent concurrent execution race conditions by wrapping their core logic in a distributed mutex lock block:
   ```php
   try {
       \SPPMod\SPPDeploy\Deployer\TargetConnection::acquireDeploymentLock();
       // ... deployment logic ...
   } finally {
       \SPPMod\SPPDeploy\Deployer\TargetConnection::releaseDeploymentLock();
   }
   ```
4. **Mandatory Dual-Format Man Page Generation (Unix & Markdown)**: For every CLI command or interactive shell built-in (e.g., `tab`) added or modified, you MUST author or update a detailed and well-formatted manual page in BOTH standard Markdown format (`docs/commands/<safe-command-name>.md`) AND traditional Unix troff/groff format (`docs/commands/man/<safe-command-name>.1`).
   - **Markdown Format Requirements**: The Markdown manual page MUST include the following standard sections to maintain excellent documentation formatting:
     - `## <command_name>`: The exact command signature.
     - `**Purpose**: <brief description>`: A clear, concise summary of what the command achieves.
     - `### Synopsis`: Code block demonstrating exact syntax and parameter flags.
     - `### Extended Usage`: Detailed explanation of command behavior, use cases, and practical examples.
     - `### Options Available`: A well-formatted list of all supported options, flags, and their default values.
     - `### Under the Hood Activity`: Explicitly detailing filesystem writes, DB interactions, or outbound HTTP calls.
   - **Unix Format Requirements**: The Unix troff/groff manual page (`.1` section 1 file) MUST adhere to standard man page macro conventions (`.TH`, `.SH NAME`, `.SH SYNOPSIS`, `.SH DESCRIPTION`, `.SH OPTIONS`, `.SH UNDER THE HOOD`) to ensure native compatibility with the Unix `man` command.

## SPP Workflow Scaffolding & Architecture Rules

When developing, refactoring, or scaffolding applications, entities, or components within the SPP framework, you MUST adhere to the following workflow orchestration constraints:

1. **Mandatory Workflow Provisioning**: All app and entity scaffolding commands (e.g., `make:app`, `make:scaffold`, `make:blade-scaffold`, `make:blade-project`, `make:mixed-paradigm`) MUST automatically create workflow configuration directories (`etc/apps/{$appName}/workflows` or `src/{$appName}/etc/workflows`).
2. **YAML-Based Definition Standards**: Workflow definitions MUST be structured in YAML format within `etc/apps/<AppName>/workflows/<workflow_name>.yml` (or subdirectories for categorization). Each workflow definition MUST define:
   - `states`: Valid lifecycle stages.
   - `transitions`: Allowed movements between states.
   - `timeout` & `timeout_transition`: SLA definitions for automated daemon escalation (`spp workflow:process-timeouts`).
3. **Saga Pattern & Parallel Markings Support**: Workflows must support parallel concurrent markings (stored as arrays in `spp_entity_workflow_history` and `spp_workflows`) and Saga-style compensating transactions (`WorkflowManager::rollback()`) via `compensations` callbacks.
4. **Scaffold Tutorial Documentation**: Whenever a sample workflow YAML file is generated during scaffolding, it MUST include standard tutorial comments explaining the core orchestration concepts (States, Transitions, Parallel Markings, Saga Pattern, and SLA Timeouts) to guide future developers.

## SPP Core Workflow Integration & Local Client Assets Rule

When building, extending, or debugging entities, controllers, event listeners, forms, or client-side assets in the SPP framework, you MUST adhere to the following workflow orchestration and asset referencing rules:

1. **Native Entity Workflow APIs**: All database models extending `SPPEntity` natively inherit workflow capabilities. Do NOT directly set or manipulate the raw `status` property for state transitions. ALWAYS utilize the native fluent methods:
   - `$entity->getWorkflowState()`: Retrieves current state markings (including parallel states).
   - `$entity->canTransition($transitionName)`: Checks if a transition is valid from the current state.
   - `$entity->applyTransition($transitionName, $user, $comment, $contextData)`: Executes a transition, evaluates guards, triggers event hooks, and logs audit history.
   - `$entity->getWorkflowHistory()`: Retrieves the lineage of state changes from `spp_entity_workflow_history`.
2. **Controller Transition Helpers & Smart Content Negotiation**: In any controller extending `ViewController`, `ResourceController`, or `WizardController`, NEVER perform manual transition checks and custom response formatting. ALWAYS use:
   ```php
   $this->transitionEntity($entity, $transitionName, $contextData, $successView, $errorView);
   ```
   This helper automatically applies the transition and performs smart content negotiation (serving HTMX partials or real-time Turbo Stream updates based on request headers).
3. **Dual Event Bus Firing**: When creating custom workflow orchestration routines or evaluating state changes, you MUST fire both `\SPP\SPPEvent::fireEvent()` and `\SPP\SPPEvent::triggerHook()` for `workflow.before_transition` (or `workflow:before_transition`) and `workflow.after_transition` (or `workflow:after_transition`) to ensure full coverage for all event subscribers.
4. **Workflow Form Guards**: When building forms or update endpoints for workflow-managed entities, ALWAYS include `SPPWorkflowGuardValidator` (or `SPP_Validator_WorkflowGuardValidator`) in the validation rules to prevent unauthorized modifications on locked, pending, or in-flight entities.
5. **Local Standalone Client Assets**: Do NOT reference external CDNs for HTMX or Turbo Streams. ALWAYS include the local, high-performance standalone distributions located in `spp/admin/js/`:
   - Use the `sppadmin` alias in views (e.g. `APP_BASE_URI . '/sppadmin/js/htmx.min.js'`) because root `.htaccess` strictly forbids direct access to the `spp/admin` directory, resulting in 403 Forbidden errors if accessed via `/spp/admin/`.

## SPP Developer-Friendly Enterprise Feature Scaffolding & Implementation Rule

When developing, refactoring, or scaffolding applications, entities, controllers, services, or CLI commands within the SPP framework, you MUST ensure that all advanced enterprise capabilities are developer-friendly and have straightforward implementations:

1. **Straightforward Scaffolding**: All code generators and stubs (`controller.stub`, `command.stub`, `livecomponent.stub`, `service.stub`, `model.stub`, `scaffold_controller.stub`) MUST include clear, developer-friendly boilerplate and documented examples for the core enterprise features:
   - **AI Tool Calling**: Easy integration using `\SPPMod\SPPAI\SPPAI::callTool($prompt, $tools)`.
   - **CQRS Event Store & Snapshots**: Direct event logging and point-in-time snapshot retrieval via `\SPPMod\SPPWorkflow\CQRS\EventStore`.
   - **DAG Job Orchestration**: Token-bucket throttled job dispatching with dependencies via `\SPPMod\SPPQueue\DagJobOrchestrator`.
   - **O(log N) Binary Indexing**: High-performance binary search indexing via `\SPPMod\SPPStorage\XdbBinaryIndexer`.
   - **W3C Trace Context Telemetry**: Distributed tracing propagation via `\SPPMod\SPPReport\W3CTraceContext`.
   - **View Transitions & Live Components**: Seamless frontend reactivity using `wire:navigate`, `wire:click`, and external partial rendering without inline HTML strings.
2. **Strict Adherence in Scaffolds**: Scaffolds must never contain inline HTML literal strings in PHP logic, must adhere to strict CLI SAPI guarding (`isCLIOnly()`), and must showcase best-practice usage patterns so developers can adopt enterprise features effortlessly.

## SPP Comprehensive Novice-First Documentation & Tutorial Rule

Whenever anything is added, modified, or deleted within the SPP framework, you MUST ensure that the framework documentation and tutorial guides (`docs/` and `docs/tutorials/`) are updated with a very detailed, highly comprehensive writeup.

1. **Target Audience (Novice-First)**: The documentation and tutorial articles MUST be authored for someone who is a total novice to SPP. Someone who has never even heard of the SPP framework must be able to read the guide and gain a complete, in-depth ("in and out") understanding of the features being added, modified, or deleted.
2. **Mandatory Documentation Coverage**:
   - **Foundational Concepts**: Clearly explain what the feature is, why it exists in the framework, and what problem it solves in plain, accessible language.
   - **Lifecycle & Architecture**: Provide an end-to-end breakdown of how the feature interacts with other core modules (such as Routing, ViewControllers, CQRS, Workflows, or CLI daemons).
   - **Step-by-Step Tutorials**: Include clear, copy-pasteable examples and practical walkthroughs showing exactly how a novice developer configures, deploys, and interacts with the feature from scratch.
   - **Impact of Deletions/Modifications**: If a feature is modified or deleted, explicitly document the legacy behavior, the rationale behind the change, and the exact migration or replacement steps required.

## SPP Framework Integrity & Anti-Bypass Rule

When you encounter a bug or limitation in the SPP framework (such as SPP-UX global event delegation failing to catch specific events, or a view controller missing a hook), you MUST NOT bypass the framework's architecture to achieve a quick fix. 

1. **No Native Hacks**: NEVER use native inline DOM event attributes (e.g., `onmousedown`, `onpointerdown`) to hack around SPP-UX synthetic event directives (e.g., `@mousedown`, `@pointerdown`).
2. **Fix the Core**: ALWAYS investigate the root cause within the framework's core files (e.g., `sppux.js` dispatcher, `ViewController` logic) and correct the framework directly so it supports the required behavior natively.
3. **Architectural Purity**: Your solutions must elevate and mature the framework, rather than avoiding its constraints. If a framework abstraction exists, you must use it or improve it.

## SPP Scaffold & Stub Synchronization Rule

When you identify and fix a bug, omission, or architectural flaw in an application or file that was generated by a framework scaffold (e.g., `make:app`, `make:scaffold`, or `.stub` files), you MUST also locate and apply the exact same fix to the original scaffold template within the framework core.
1. **Never Fix Locally Only**: If the buggy file is essentially unmodified from its generated state, fixing the local app alone is insufficient.
2. **Synchronize Core Stubs**: Find the underlying `.stub` or template file (e.g., in `spp/system/stubs/` or `commands/stubs/`) and apply the patch there to prevent future regressions for newly generated code.

## SPP Ephemeral Verification & Clean Release-Ready Workspace Rule

When performing debugging, exploration, prototyping, or automated test verification within the SPP framework, you MUST adhere to the following clean-workspace constraints:

1. **Strict Ephemeral Lifecycle**: Any temporary script, test runner, probe file, curl dump, or intermediate artifact (e.g., `test_*.php`, `scratch_*.php`, `temp.*`, `out.*`, `trace.*`, `tmp_*.txt`, `cookies*.txt`, `session_*.txt`) created in the workspace root or project subdirectories for the purpose of validation, debugging, or reproduction MUST be deleted immediately after execution and verification is completed.
2. **Pre-Deletion Safety Verification**: Before unlinking or deleting any file matching temporary naming patterns, you MUST explicitly verify that it is truly an ephemeral probe created during the active debugging session (e.g., by inspecting `git status` for untracked status, checking creation time, or reviewing file contents) and NEVER delete permanent fixtures, legitimate regression test suites (such as those residing under `tests/`), or useful project files that happen to share similar naming conventions.
3. **Dedicated Scratch Isolation**: Whenever possible, place one-off test scripts in isolated scratch locations (such as the agent's scratch directory `<appDataDir>\brain\<conversation-id>\scratch\` or `sys_get_temp_dir()`) rather than creating ad-hoc files directly in project directories. If files must be placed in the workspace for autoloading or bootstrap reasons, track them explicitly and remove them in a `finally` block or immediate post-execution cleanup step.
4. **Release-Ready Invariant**: NEVER leave behind untracked probe scripts or test files upon concluding a task. ALWAYS verify `git status` or inspect the working tree to ensure that only intended, production-ready project code, tests in permanent directories (`tests/`), and documentation remain.

## SPP Native Infrastructure First Rule

When designing, implementing, refactoring, or extending applications, modules, entities, or services within the SPP framework, you MUST ALWAYS leverage native SPP framework infrastructure and core abstractions wherever applicable before considering custom or non-framework solutions:

1. **Native Entity Architecture (`SPPEntity`)**:
   - ALL database-backed domain models, institutional entities, records, and transactional resources MUST extend `\SPPMod\SPPDB\SPPEntity` (or an application entity base extending it).
   - NEVER query or mutate database tables using disconnected ad-hoc PDO statements or raw arrays when an entity model exists or can be modeled.
   - ALWAYS utilize native active record methods (`::find()`, `::findBy()`, `::findWhere()`, `$entity->save()`, `$entity->delete()`, `$entity->get()`, `$entity->set()`) and declarative relationship definitions (`parent_entity`, `has_many`, `belongs_to`).

2. **Native Workflow & State Machine Engine (`WorkflowManager`)**:
   - NEVER manually assign or mutate a lifecycle `status` field as an arbitrary string.
   - ALWAYS implement `\SPP\Core\Interfaces\WorkflowableInterface` on status-bearing models and define declarative YAML lifecycles in `etc/apps/<AppName>/workflows/<workflow_name>.yml`.
   - ALWAYS execute state transitions using native fluent methods: `$entity->canTransition()`, `$entity->applyTransition()`, `$entity->getWorkflowState()`, and `$entity->getWorkflowHistory()`.
   - In controllers, ALWAYS use `$this->transitionEntity()` for automated transition handling and smart content negotiation (HTMX / Turbo Streams).

3. **Native Event Bus & Extension Hooks (`SPPEvent`)**:
   - ALWAYS broadcast domain occurrences, lifecycle changes, and cross-module notifications via `\SPP\SPPEvent::fireEvent($eventName, $payload)` and `\SPP\SPPEvent::triggerHook($hookName, $context)`.
   - Ensure dual event bus firing (`fireEvent` + `triggerHook`) for all critical entity and workflow state transitions.

4. **Native Presentation & Reactive View Engine (`ViewController` & Drishyam)**:
   - ALL web routes and endpoints MUST be served by controllers extending `\SPPMod\SPPView\ViewController` (or `ResourceController`, `WizardController`).
   - ZERO inline HTML string literals in PHP logic; ALL markup MUST reside in standalone external Blade or Twig templates/partials.
   - Utilize native SPP directives (`@spppartial`, `@sppstream`, `wire:click`, `wire:navigate`) for client-side reactivity and live components.
   - For forms and validation, utilize `@sppform_bind('form_id', $entity)` to attach Form YAML validation schemas to plain HTML forms (with automated initial entity hydration and old-input retention), `@error('field') ... @enderror` and `@old('field')` for error recovery, and `$this->validateForm()` or `$this->validateFormOrFail()` in controllers for headless request validation. Support 1-click entity form synthesis (`php spp.php make:form <Name> --entity=<Entity>`), built-in database validation rules (`unique:table,column`, `exists:table,column`), debounced background validation, and universal custom rules extending `Rule`.

5. **Native Background Queue & DAG Orchestration (`DagJobOrchestrator`)**:
   - Complex background processes, bulk batch operations, multi-stage imports, and scheduled maintenance MUST be structured as Directed Acyclic Graphs (DAGs) using `\SPPMod\SPPQueue\DagJobOrchestrator`.
   - Enforce token-bucket flow control and distributed mutex locking (`TargetConnection::acquireDeploymentLock()`) for high-concurrency tasks.

6. **Native High-Performance Storage & Binary Indexing (`XdbBinaryIndexer`)**:
   - For fast search lookups, lookup tables, and telemetry stores, utilize `\SPPMod\SPPStorage\XdbBinaryIndexer` for $O(\log N)$ binary searching and structured SQLite WAL ledgers.

7. **Native Caching Architecture (`SPPCacheManager`)**:
   - ALWAYS utilize `\SPPMod\SPPCache\SPPCacheManager` for application, view, or configuration caching. NEVER write ad-hoc cache files or rely on unmanaged process memory.

8. **Native CLI Infrastructure (`SPP\CLI\Command`)**:
   - ALL command-line tools MUST extend `\SPP\CLI\Command` and be registered with `CommandManager`.
   - High-privilege or CLI-targeted commands MUST implement `public function isCLIOnly(): bool { return true; }`.
   - Every CLI command MUST be accompanied by dual-format man pages (Markdown in `docs/commands/` and Unix troff in `docs/commands/man/` and `man/man1/`).

9. **Native Testing Engine (`Parikshak`)**:
   - Parikshak (`\SPPMod\Parikshak\Parikshak`) is the official, primary native testing engine of the SPP framework.
   - ALWAYS author tests using Parikshak test infrastructure: extend `\SPPMod\Parikshak\SPPTestCase` or `\SPPMod\Parikshak\TestCase`.
   - Leverage native testing traits and fixtures including `RefreshDatabase` for isolated in-memory testing, `SPPFactory` / `SPPFaker` for test entity generation, and `InteractsWithApi` / `InteractsWithBrowser` for endpoint assertions.
   - Execute test suites using the framework's native test commands: `php spp.php test [--coverage]` or `php spp.php test:run [entity] [--app=AppName] [--coverage]`.

10. **Native Modularity 2.0 & Module Architecture (`\SPP\Module` & `\SPP\Core\ModuleConfigManager`)**:
   - ALL modules MUST define metadata strictly via YAML (`module.yml`), declarative database schemas via `db.yml`, zero-boot events via `events.yml`, and container integration via `ServiceProviderInterface`.
   - NEVER read or mutate module configuration files directly; ALWAYS utilize `\SPP\Core\ModuleConfigManager`.
   - ALL database tables for modules MUST be defined in `db.yml` and synchronized via `ModuleInstaller::upgrade()` or `php spp.php module:update`.
   - Compulsory framework modules (`Module::isCompulsory()`) MUST NEVER be deactivated or uninstalled.
   - Scaffolding new modules MUST be done via `php spp.php make:module <name> [--scope=app|optional|contrib|spp] [--app=AppName]`.
   - Quality assurance and packaging MUST use native CLI commands: `php spp.php module:doctor [name] [--fix]` for health auditing, `php spp.php test:module <name>` for isolated Parikshak testing, and `php spp.php module:pack <name>` for distribution packaging.

## SPP Primary Testing Engine (Parikshak) Rule

When authoring, scaffolding, refactoring, or running automated tests in SPP applications, modules, or core components, you MUST recognize and use **Parikshak** as the primary testing engine:

1. **Parikshak as Primary Testing Engine**:
   - Parikshak is SPP's first-party, native testing and evaluation framework (`spp/modules/optional/parikshak/` and `\SPPMod\Parikshak`).
   - NEVER default to disconnected, non-framework testing scripts when verifying SPP applications.
2. **Native Test Classes & Traits**:
   - Unit and integration tests must extend `\SPPMod\Parikshak\SPPTestCase` (for application context and container-aware tests) or `\SPPMod\Parikshak\TestCase`.
   - Utilize the `RefreshDatabase` trait for automated database isolation (in-memory SQLite), `SPPFactory` and `SPPFaker` for test fixtures, and `InteractsWithApi` / `InteractsWithBrowser` for HTTP/API assertions.
3. **Execution & CLI Tooling**:
   - Automated tests are orchestrated via `SPPTestRunner` and executed through the native CLI commands:
     - `php spp.php test [--coverage]` (runs unit/feature test runner for the active app context)
     - `php spp.php test:run [entity] [--app=AppName] [--coverage]` (runs Parikshak evaluation suite)
     - `php spp.php sys:test:auto [app]` (triggers evolutionary/chaos testing)
4. **Test Location & Naming**:
   - Application tests reside in `src/<AppName>/tests/` (or `tests/`) named `<EntityOrFeature>Test.php` with public `test*()` methods.

## SPP Universal AI Knowledge Ingestion & Synchronization Rule

Whenever any feature, capability, CLI command, entity paradigm, workflow construct, controller helper, or architectural convention is added, modified, or deleted within the SPP framework, you MUST keep the Universal Knowledge Ingestion Standard synchronized in lockstep:

1. **Root AI Ingestion Manifests (`llms.txt` & `llms-full.txt`)**:
   - `llms.txt`: Keep the condensed quickstart (~1,200–2,500 tokens), directory taxonomy, 10 Golden Rules, and CLI cheatsheet updated with any new or modified core commands and invariants.
   - `llms-full.txt`: Keep the full framework architecture bible updated with complete, copy-pasteable code examples, API signatures, controller paradigms, workflow YAML schemas, and Parikshak test patterns.

2. **Multi-Agent Instruction Profiles**:
   - Ensure changes are propagated to the relevant IDE and agent configuration files:
     - `CLAUDE.md` (Anthropic Claude Code CLI & Claude Desktop)
     - `.cursorrules` (Cursor AI IDE)
     - `.windsurfrules` (Codeium Windsurf)
     - `.github/copilot-instructions.md` (GitHub Copilot)
     - `.agents/AGENTS.md` (Antigravity & Agent framework)

3. **Core AI Engine Synchronization (`AiArchitectureEngine`)**:
   - Update `\SPPMod\SPPStorage\AiArchitectureEngine`:
     - `getContext()`: Ensure synthesized system prompts reflect the latest golden rules and anti-hallucination traps.
     - `getBlueprint()`: Ensure introspected capabilities expose newly introduced modules or command categories.
     - `getRecipes()` & `getRecipe()`: Author or update verified, copy-pasteable architectural recipes for new capabilities.
     - `lintCode()`: Add or refine AST/semantic linter patterns to catch violations of new invariants.

## SPP Developer Simplicity & Anti-Complexity Rule (High-Value Invariant)

When designing, adding, refactoring, or modifying any component, API, CLI tool, or configuration within the SPP framework, you MUST adhere to the **Simplicity First** invariant:

1. **Simplicity Over Over-Engineering**:
   - The primary purpose of any framework enhancement is to **simplify developer life** by eliminating repetitive boilerplate, reducing cognitive load, and preventing runtime errors.
   - NEVER introduce complex multi-layer abstractions, convoluted configuration schemas, or unnecessary indirection when a clean, straightforward implementation achieves the goal.
2. **High-Value Justification Threshold**:
   - Do NOT complicate the framework with niche abstractions, speculative generality, or gratuitous features.
   - Any added complexity MUST be justified by **extraordinary, undeniable value** (such as a 10x reduction in developer boilerplate, critical enterprise data protection, or automated failure recovery).
   - If an existing framework abstraction (`SPPEntity`, `ViewController`, `WorkflowManager`, `SPPEvent`, `FilePropsManager`) can solve the problem cleanly, ALWAYS leverage or extend it rather than inventing a new subsystem.
3. **Zero-Overhead & Opt-In Default**:
   - Advanced capabilities (e.g. circuit breakers, micro-frontends, PII masking, DAG queues) MUST be **100% opt-in** and zero-config by default.
   - Standard CRUD applications and simple controllers must remain ultra-lightweight and unburdened by enterprise machinery. Developers who do not need an advanced feature must never be forced to configure it or pay a performance penalty.
4. **Minimal Moving Parts**:
   - Avoid creating standalone background daemons, disconnected configuration directories, or third-party dependencies when native framework mechanisms suffice.
   - Prefer single cohesive service classes and declarative per-directory manifest attributes (`__spp_file_props.yml`) over scattered, complex configuration networks.

## SPP Zero Route Fragmentation & Manifest Auto-Wiring Rule

When defining routes or resolving dependencies within the SPP framework, you MUST adhere to the Single Source of Truth invariant:

1. **Zero Route Fragmentation**:
   - Routes MUST be defined in **one place only**: centrally in `src/<AppName>/etc/routes.yml` (the recommended standard for clean visibility) OR co-located directly above controller action methods using PHP 8 `#[Route]` attributes.
   - NEVER manually maintain route mappings across dispersed per-directory manifests (`__spp_file_props.yml`).
   - The Manifest Router (`\SPPMod\SPPRouter\ManifestRouter`) autonomously discovers, compiles, and scopes routes across all sources with compile-time collision detection (`php spp.php route:list --compile`).
2. **Zero-Code Dependency Injection**:
   - In PSR-11 `Container::get()`, interfaces and singletons are auto-wired directly from the compiled manifest index (`file_props.cache.php`).
   - Concrete implementations declaring `attributes.implements` or implementing interface types are automatically bound and resolved with constructor parameter auto-wiring without manual service provider registration.


## SPP Studio & Full Application Lifecycle Builder Rule

When developing, compiling, or extending visual pages, manifests, blueprints, or views in SPP Studio:

1. **Full Lifecycle & App Starting Point Clarity**: SPP Studio manages the complete lifecycle of applications. All applications must have clear, introspectable ingress starting points (default route `/`, entry page `defaults.home`, entry controller, and landing view). The interactive App Blueprint must accurately reflect the 5-stage ingress pipeline, declared routes, embedded partial hierarchies, and workflow state machines.
2. **Zero-Code Visual Canvas as Default**: All pages, Blade partials, and layouts MUST open in Visual Canvas mode by default (`studioMode = 'canvas'`). Semantic template synthesis must parse templates into non-empty component trees (search inputs, nav links, headings, badges, alerts, partial embeds) so partials and views are never rendered blank.
3. **Manifest Storage & Standards**: Page manifests MUST be stored in YAML format under `etc/apps/<AppName>/pages/<page_name>.yml` (or `src/<AppName>/etc/pages/<page_name>.yml`). Each manifest defines `page` metadata (route, title, layout, middleware), `data` sources bound to `SPPEntity` models, and a hierarchical `components` tree.
4. **Extensible Component Registry**: UI components must be defined and retrieved via `\SPPMod\Drishyam\Compiler\ComponentRegistry`. New custom components can be registered dynamically via the `drishyam.studio.component_registry` SPPEvent.
5. **Compilation Pipeline**: Compiling manifests with `PageCompiler` or `php spp.php compile:page` MUST generate:
   - `ViewController` with PHP 8 `#[Route]` attributes and native entity bindings.
   - External Blade templates and partials (`@spppartial`) adhering strictly to the Zero Inline HTML rule.
   - Row partials for entity tables enabling HTMX partial swaps (`hx-post`, `hx-target`, `hx-swap`).
   - Parikshak test cases (`SPPTestCase`) with automated HTTP and partial assertions.
6. **CLI Tools & SAPI Guarding**: Page scaffolding via `php spp.php make:page <Name> [--template=...]` and compilation via `php spp.php compile:page <Name> [--all]` must enforce strict CLI-only execution (`isCLIOnly(): bool { return true; }`) and be documented with dual-format man pages (`docs/commands/` and `docs/commands/man/`).

## SPP Framework Sovereignty & Zero External Software References Rule

When writing code, designing interfaces, authoring comments, generating documentation, or planning within the SPP framework, you MUST adhere to the **Sovereignty & Implementation Purity** invariant:

1. **Permitted Scope for Comparison (Planning Documents Only)**:
   - External, non-SPP software product names (e.g., Visual Studio, VS Code, JetBrains, PhpStorm, Sublime, Eclipse, Postman, ngrok, cPanel, etc.) MAY be used within **planning documents, feature comparison matrices, and design proposals** strictly for the purpose of architectural benchmarking, competitive parity evaluation, and scoping requirements.
2. **Zero External Software Names in Actual Implementation**:
   - In all **actual implementations** (production source code, PHP/JS/CSS logic, class/method/variable names, comments, UI text, labels, button captions, tooltips, error messages, and user-facing technical documentation/manuals), there MUST be **ABSOLUTELY ZERO** mention of external software product names.
   - All implemented capabilities must be named, styled, and documented strictly as **native, sovereign, first-class SPP framework features** (e.g., "SPP Developer Studio", "SPPCoder", "SPP Workbench", "Parikshak Test Explorer", "Drishyam Studio", "Sovereign Webhook Relay", "API Studio").
3. **First-Class Sovereign Adaptation**:
   - NEVER describe or present an implemented feature in the interface as "imported from elsewhere", "ported from [Product X]", or "emulating [Product Y]". Every interface must feel organic, seamless, and indigenous to the SPP ecosystem, designed from first principles for SPP developers.

## SPP Modular Navigation & Autonomous Plug-and-Play Packages Rule

When developing, refactoring, or extending applications, packages, navigation menus, or views in the SPP framework, you MUST adhere to the following modular navigation constraints:

1. **Decoupled Navigation**: Navigation links for the global Ctrl+K Command Palette, 9-dot App Launcher modal, and project Sidebar MUST NEVER be hardcoded within core Blade layout templates.
2. **Autonomous Package Manifests (`menus.yml`)**: Every autonomous package declares its own navigation contributions in `src/<AppName>/packages/<package_id>/menus.yml`, specifying `palette_items`, `launcher_suites`, and `sidebar_items`.
3. **Dynamic Aggregation & 2-Tier Filtering**: `MenuRegistry` dynamically queries `PackageKernel::hasPackage()` (physical installation) and `FeatureManager::isEnabled()` (per-project tenant enablement) to ensure inactive packages and disabled features remain completely invisible.
4. **Seamless Plug-and-Play Lifecycle**: Dropping a package folder into `src/<AppName>/packages/` immediately manifests its links in all navigation menus; removing the directory instantly removes all links across the application with zero template modifications or core registry edits.

## SPP Definitive Architecture Book Synchronization Rule

Whenever any feature, capability, CLI command, entity paradigm, workflow construct, controller helper, theme adapter, or architectural convention is added, modified, or deleted within the SPP framework, you MUST inspect and update the Definitive Architecture Book (`docs/book/v1/` and `docs/book/project.yml`) in lockstep:

1. **Mandatory Book Inspection & Synchronization**:
   - For every change in framework behavior or capabilities, locate the corresponding chapter(s) in `docs/book/v1/` and update the narrative, code examples, and architectural diagrams.
   - If a new fundamental subsystem or major paradigm is introduced, add a dedicated chapter, preserve chronological chapter numbering (`NN-<slug>.md`), and update `docs/book/v1/index.md` and the sidebar taxonomy in `docs/book/project.yml`.

2. **Novice-First Standard in Book Updates**:
   - All chapter additions and updates MUST be authored for a novice reader: explain *why* the feature exists, *what problem it solves*, provide visual Mermaid flowcharts, and supply complete, copy-pasteable code examples.
   - Never rely on incomplete code snippets or abstract pseudo-code.

3. **Inviolable Constraints in Book Content**:
   - **Zero Inline HTML**: Code examples in chapters MUST adhere strictly to the Zero Inline HTML Literals rule and Drishyam external partial conventions.
   - **Zero Prohibited Framework Name Occurrences**: The prohibited framework name must NEVER appear anywhere in any book chapter, metadata, YAML configuration, test, or response.
   - **Standalone Local Assets**: All client assets must reference local distributions in `/res/shared/` or `/sppadmin/js/` without external CDN links.

4. **Automated Verification**:
   - After updating any book content or structure, ALWAYS run the Parikshak integrity test suite:
     ```bash
     php spp.php test src/SPPDocs/tests/test.BookProjectIntegrityTest.php
     ```
   - Ensure 100% pass rate before concluding any task.



