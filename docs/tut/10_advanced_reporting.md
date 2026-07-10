# 10. Business Intelligence & Advanced Reporting

Welcome to the final chapter of the tutorial. We'll explore the **SPP Report Builder**, a zero-dependency, highly dynamic Business Intelligence tool built natively into the SPP ecosystem with state-of-the-art Hypermedia capabilities.

## The SPPReport Engine

Reporting traditionally requires heavy client-side libraries (like DataTables) or massive server-side code generation. The `sppreport` module changes this by offering highly performant Hypermedia external partials (HTMX & Turbo Streams), a clean ResourceController API, and a JSON-to-SQL backend builder.

### Key Capabilities:
- **Hypermedia Core (HTMX & Turbo Streams):** Zero inline HTML literals in controllers. Renders standalone external partials via smart content negotiation (`HX-Request`, `Turbo-Frame`).
- **Dual-Mode Templating Architecture:** Unified templates providing distinct views for both interactive Screen Mode (`.spp-screen-mode`) and unconstrained printer-friendly Print Mode (`.spp-print-mode`).
- **AI-Powered Builder (Ask AI):** Translate natural language into complex SQL joins and aggregations instantly using `SPPAI`.
- **Federated BI:** Query completely external databases dynamically via secure PDO connections mapped in the UI with strict SSRF protection.
- **Dynamic Schema Introspection:** Automatically reads your database entities and validates DDL identifiers via `SchemaValidator`.
- **Relational Joins:** Visually link multiple tables together (LEFT JOIN, INNER JOIN).
- **Calculated Columns:** Define custom arithmetic logic directly in the UI.
- **Interactive Pivot Tables:** Slice, dice, and cross-tabulate data securely in the browser without writing SQL.
- **Infinite Nested Filters & RBAC:** Supports AND/OR grouped conditions visually, and dynamic Row-Level Security (e.g. `{{CURRENT_USER_ID}}`).
- **White-Labeling & Live Data:** Embed with multi-tenant custom CSS themes (logos, fonts, brand colors) and auto-refresh intervals.
- **Automated Cron Emails:** Schedule background workers to email Rich HTML or PDF exports with strict CLI SAPI guarding and distributed mutex locking.
- **Zero-Dependency:** Built entirely with native browser features, local standalone client assets (`htmx.min.js`, `turbo-streams.min.js`), and core SPP PHP.

## One-Line Integration

Integrating the BI suite into any page of your app is completely declarative and hypermedia-driven.

### 1. The Hypermedia Dashboard Mount
Mount the full interactive analytics dashboard and query configurator using HTMX:

```html
<div id="spp-main-dashboard-mount" hx-get="/spp/admin/api.php?action=report_api&modname=sppreport&report_action=dashboard" hx-trigger="load" hx-swap="innerHTML">
    Initializing Hypermedia Analytics Dashboard...
</div>
```

### 2. Legacy SPP-UX Fallback (Deprecated)
For backwards compatibility with V3/V4 client-side rendering apps:

```html
<div data-spp-type="ux" data-spp-path="/spp/modules/spp/sppreport/js/sppreport-ui.js"></div>
```

## Next-Gen Enterprise BI Features (V5)

### 1. Unified Dual-Mode (Screen & Print) Templating 📄
A flagship architectural enhancement in V5 is the unified Dual-Mode Report Template (`views/partials/report_template_dual_mode.php`). A single template encapsulates two distinct presentation wrappers:
* **Screen Mode (`.spp-screen-mode.no-print`)**: Renders a premium glassmorphism interface with live local table search bars (`sppFilterTable()`), active record count counters, horizontal scrollable grids (`overflow-x: auto`), generated SQL syntax highlighting, and instant export buttons (CSV, Excel, Print/PDF).
* **Print Mode (`.spp-print-mode.only-print`)**: Automatically toggled via CSS `@media print` queries. Disables all scrollbars to let table rows flow unconstrained across pages with repeating table headers (`display: table-header-group`). Automatically injects formal corporate branding (`SPP Global Enterprise Solutions`), confidentiality notices, and formal authorization signature blocks (Prepared By, Reviewed By, Approved By).

### 2. Smart Content Negotiation & Zero Inline HTML Literals 🧠
The `ReportController` inspects incoming request headers (`HX-Request`, `Turbo-Frame`, `Accept: text/vnd.turbo-stream.html`). Instead of returning raw JSON or mixing HTML string literals in PHP logic, it dynamically negotiates the exact standalone external partial (`report_dashboard.html`, `report_configurator.php`, `report_preview.php`) or Turbo Stream (`report_update.php`) to serve.

### 3. Real-Time Turbo Streams Broadcasting ⚡
By passing `Accept: text/vnd.turbo-stream.html` or configuring live streaming parameters, `ReportController` broadcasts live data updates directly to DOM targets using local standalone client assets (`spp/admin/js/turbo-streams.min.js`). The broadcast template (`streams/report_update.php`) is fully configurable for streaming intervals, target IDs, action types (`replace`, `append`, `update`), and widget display formats (Data Grid, KPI Cards, Threshold Alert Banners).

### 4. Robust CLI Guarding & Distributed Mutex Locking 🔒
The automated reporting cron (`ReportCronCommand.php`) enforces strict CLI SAPI guarding (`isCLIOnly()`) to prevent execution from web contexts. To eliminate race conditions during concurrent cron triggers across clustered deployments, the engine wraps execution in distributed mutex locks:
```php
try {
    \SPPMod\SPPDeploy\Deployer\TargetConnection::acquireDeploymentLock();
    // Execute scheduled reports & evaluate webhook thresholds
} finally {
    \SPPMod\SPPDeploy\Deployer\TargetConnection::releaseDeploymentLock();
}
```

## Advanced Enterprise BI (V4 & Legacy)

### 1. Natural Language "Ask AI" & Automated Insights 🤖
Administrators can type an English request (e.g., *"Show me sum of sales grouped by region for 2024"*). The system contacts your local `SPPAI` driver, parses the database schema context, and automatically configures the Tables, Columns, Aggregates, and Joins in the visual builder instantly. End-users can also click **✨ AI Insights** to generate a 3-bullet executive summary identifying upward trends, anomalies, or outliers.

### 2. Federated BI (Multi-Source Data) 🌐
In the Settings Modal, you can define an External PDO DSN (e.g., `mysql:host=live.logistics;dbname=shipping`). The engine dynamically builds a secure `ExternalDatabaseConnection` wrapper, fetches the external schema, and allows you to build reports over an entirely separate system completely transparently.

### 3. Client-Side Pivot Tables & Interactive Drill-Downs 📊
End-users can toggle the **Pivot View** to enable a drag-and-drop cross-tabulation table. Users select Row, Column, and Value fields (with dynamic aggregations like COUNT/SUM/AVG) to slice the dataset on the fly. Clicking any visualization bar, pie slice, or numerical pivot cell instantly filters the dataset to display the exact contributing rows.

### 4. Threshold Alerts & Webhooks 🚨
Authors can define a Webhook URL and an Alert Condition (e.g., `revenue < 10000`). During the background `sppreport_cron.php` execution, the engine evaluates the dataset against the condition. If breached, an HTTP POST request is instantly dispatched to the Webhook URL (e.g. Slack, MS Teams) containing the alert trigger and the top 5 rows.

### 5. Report Version Control & Audit Trail 🕒
Every time you save a report in the Builder, a timestamped `.yml.bak` copy is automatically generated in `etc/sppreports/history/`. A **🕒 History** button in the Builder toolbar allows one-click rollback to a safe state.

## Enterprise Security & Access Control 🛡️

Reporting engines have direct access to sensitive database information, making security critical. The `sppreport` module employs multiple layers of defense:

1. **Strict API Authentication & SAPI Guards:** The `api.php` handler is strictly locked down using `SPPAuth`. Unauthenticated access is rejected, and destructive actions (saving queries, modifying templates, version restoration) require the **Admin** role. CLI commands enforce `isCLIOnly()`.
2. **Role-Based Access Control (RBAC):** Every saved report can specify `allowed_roles`. When an end-user attempts to view or export a report, the backend engine validates their active session roles against the report's configuration.
3. **SSRF Protection:** The Federated BI functionality (External DSNs) is restricted entirely to Admin users. This prevents malicious actors from utilizing your server to scan or query internal network IP addresses via Server-Side Request Forgery.
4. **DDL Identifier & Export Sanitization:** Dynamic SQL table and column names are securely verified through `\SPP\Core\SchemaValidator::isValidIdentifier()`. All CSV and XLS exports are automatically sanitized on-the-fly: any dataset values beginning with executable formula characters (`=`, `+`, `-`, `@`) are prefixed with a single quote (`'`), protecting administrators from spreadsheet macro injection attacks.
