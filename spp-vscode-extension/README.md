# SPP VS Code Extension

An enterprise-grade IDE toolkit designed to rigidly enforce the architectural constraints and design patterns of the **SPP Framework**. This extension transitions VS Code from a text editor into an active framework guardian, providing real-time linting, intelligent scaffolding, and context-aware auto-completion.

## 🚀 Key Features

### 1. Architecture Enforcement (Real-Time Linting & Quick Fixes)
The extension actively parses your PHP, HTML, and Blade files to prevent common framework violations:
- **Zero Inline HTML Linter**: Detects raw HTML literals inside Controllers/Services and provides a **Quick Fix** to automatically extract them into external partials.
- **CLI SAPI Guard Linter**: Ensures that any CLI command extending `\SPP\Command` overrides the `isCLIOnly()` method to prevent web execution.
- **DDL Security Linter**: Detects string interpolation inside `CREATE/ALTER/DROP TABLE` statements, preventing SQL injection vulnerabilities in migrations.
- **Dual Event Bus Linter**: Detects when `\SPP\SPPEvent::fireEvent()` is called without its mandatory sibling `triggerHook()`.
- **Workflow Form Guard Verifier**: Ensures that forms and controllers manipulating workflow-enabled entities utilize the `SPPWorkflowGuardValidator`.
- **CDN Asset Auto-Fixer**: Flags the use of external CDNs for HTMX/Turbo and provides a **Quick Fix** to rewrite the tags using the mandatory `sppadmin` local alias.
- **SPP-UX Anti-Bypass Linter**: Prevents the use of native DOM inline events (e.g., `onclick`, `onmousedown`) and provides a **Quick Fix** to rewrite them as synthetic SPP-UX directives (e.g., `@click`, `@mousedown`).

### 2. Intelligent Scaffolding (Context Menus)
Right-click inside the VS Code Explorer to rapidly generate framework components:
- **Make Partial**: Right-click an `.html` or `.php` file -> `SPP: Extract to Partial`.
- **Make Stream**: Scaffold real-time Turbo Streams.
- **Make App**: Scaffold an entire application module, complete with routing and workflow provisioning directories.
- **Generate Tutorial Scaffold**: Open the Command Palette and run `SPP: Generate Tutorial Scaffold` to instantly generate a novice-first documentation template adhering to SPP's mandatory tutorial format.
- **Man Page Generation**: Open the Command Palette and run `SPP: Generate Dual-Format Man Pages` to scaffold standard Markdown and Unix `.1` groff man pages.

### 3. Workflow Visualizer (Webview)
SPP relies heavily on YAML-based workflow orchestration. 
- Open any `.yml` or `.yaml` file inside `etc/apps/*/workflows/`.
- Click the **SPP: Visualize Workflow** icon in the editor's title bar (top right).
- A rich webview pane will open, parsing your states and transitions into a beautiful, interactive **Mermaid.js State Diagram**.

### 4. Scaffold & Stub Synchronizer
SPP mandates that if you fix a bug in a generated file, you must fix it in the framework core stub.
- Right-click any Controller, Form, or Service file.
- Select **SPP: Find Corresponding Framework Stub**.
- The extension locates the original `.stub` file inside `spp/system/stubs/` and opens it side-by-side!

### 5. AI Auto-Refactoring Integration
- Right-click anywhere in a PHP editor.
- Select **SPP: Auto-Refactor to Enterprise Standards**.
- Automatically delegates the current file to the underlying SPP CLI command `ai:refactor:enterprise` for autonomous bug fixing and cleanup.

### 6. Enterprise Code Snippets & Auto-Complete
- Start typing `spp-` to access powerful boilerplate generation:
  - `spp-lock`: Distributed deployment mutex locking (`Deployer\TargetConnection::acquireDeploymentLock()`).
  - `spp-cqrs`: Event Store snapshots.
  - `spp-dag`: Token-bucket throttled DAG job dispatching.
  - `spp-ai`: SPP AI Tool calling boilerplate.
  - `spp-w3c-trace`: W3C Trace Context telemetry.
  - `spp-binary-indexer`: High-performance O(log N) binary indexing.
- **Smart Completion**: Start typing inside `$this->renderPartial('` or `$entity->applyTransition('` to receive fully contextual IntelliSense based on the project's actual filesystem and YAML definitions!

---

## 🛠 Installation (From VSIX)
1. Go to the VS Code Extensions view (`Ctrl+Shift+X`).
2. Click the `...` menu in the top right.
3. Select **Install from VSIX...**
4. Choose the `spp-vscode-extension-0.0.1.vsix` file.

Enjoy the ultimate SPP development experience!
