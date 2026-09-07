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
