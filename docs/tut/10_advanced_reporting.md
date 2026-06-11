# 10. Business Intelligence & Advanced Reporting

Welcome to the final chapter of the tutorial. We'll explore the **SPP Report Builder**, a zero-dependency, highly dynamic Business Intelligence tool built natively into the SPP ecosystem.

## The SPPReport Engine

Reporting traditionally requires heavy client-side libraries (like DataTables) or massive server-side code generation. The `sppreport` module changes this by offering a declarative SPP-UX web component and a JSON-to-SQL backend builder.

### Key Capabilities:
- **AI-Powered Builder (Ask AI):** Translate natural language into complex SQL joins and aggregations instantly using `SPPAI`.
- **Federated BI:** Query completely external databases dynamically via secure PDO connections mapped in the UI.
- **Dynamic Schema Introspection:** Automatically reads your database entities.
- **Relational Joins:** Visually link multiple tables together (LEFT JOIN, INNER JOIN).
- **Calculated Columns:** Define custom arithmetic logic directly in the UI.
- **Interactive Pivot Tables:** Slice, dice, and cross-tabulate data securely in the browser without writing SQL.
- **Infinite Nested Filters & RBAC:** Supports AND/OR grouped conditions visually, and dynamic Row-Level Security (e.g. `{{CURRENT_USER_ID}}`).
- **White-Labeling & Live Data:** Embed with multi-tenant custom CSS themes (logos, fonts, brand colors) and auto-refresh intervals.
- **Visual Dashboards:** Chart.js integration out-of-the-box via CDN.
- **Automated Cron Emails:** Schedule background workers to email Rich HTML or PDF exports.
- **WYSIWYG Print Templates:** Design corporate PDF and print templates.
- **Zero-Dependency:** Built entirely with native browser features and core SPP PHP.

## One-Line Integration

Integrating the BI suite into any page of your app is completely declarative. SPP provides two distinct components depending on your user's role.

### 1. The Builder (For Admins & Authors)
Give users the ability to build queries from scratch, configure columns, and design print templates.

```html
<div 
    data-spp-type="ux" 
    data-spp-path="/spp/modules/spp/sppreport/js/sppreport-ui.js">
</div>
```

### 2. The Viewer (For End-Users)
Embed a read-only viewer for a specific saved report. It hides the complex query builder and only exposes the data grid, runtime filters, and export buttons.

```html
<div 
    data-spp-type="ux" 
    data-spp-path="/spp/modules/spp/sppreport/js/sppreport-viewer.js"
    data-spp-props='{"reportName": "Monthly Sales"}'>
</div>
```

When the page loads, `spp-loader.js` automatically mounts the isolated interactive application into this div container.

## Enterprise BI Features (V3)

The SPPReport engine scales with your business by offering advanced SaaS-level capabilities zero-dependency.

### 1. Natural Language "Ask AI" 🤖
In the Builder (`sppreport-ui.js`), administrators will notice a **✨ Ask AI** bar. By typing an English request (e.g., *"Show me sum of sales grouped by region for 2024"*), the system contacts your local `SPPAI` driver, parses the database schema context, and automatically configures the Tables, Columns, Aggregates, and Joins in the visual builder instantly. 

### 2. Federated BI (Multi-Source Data) 🌐
You are no longer restricted to the internal SPP application database. In the **Settings Modal**, you can define an **External PDO DSN** (e.g., `mysql:host=live.logistics;dbname=shipping`). The engine will dynamically build a secure PDO wrapper, fetch the external schema, and allow you to build reports over an entirely separate system completely transparently.

### 3. Client-Side Pivot Tables 📊
When embedding the Viewer (`sppreport-viewer.js`), end-users can click the **Pivot View** toggle. This replaces the standard data grid with a powerful Drag-and-Drop style cross-tabulation table. Users select Row, Column, and Value fields (with dynamic aggregations like COUNT/SUM/AVG) to slice the dataset on the fly without issuing new queries to the server.

### 4. Multi-Tenant White Labeling & Live TV Dashboards 🎨
The Report Viewer and Dashboards are designed for multi-tenant SaaS embedding.
*   **Theming**: Set a custom Logo URL, Primary Hex Color, and Google Font (like `Inter`) via the Builder Settings. The viewer securely maps these as CSS custom properties (`--sppux-primary`) to adopt any brand visually.
*   **Auto-Refresh**: Set an interval in seconds (e.g., `30`). The viewer will silently poll the server to repaint the charts and grids, allowing you to project live data onto a big screen just like a TV Dashboard.

## Advanced Enterprise BI (V4)

With V4, SPPReport pushes into full-scale BI territory.

### 1. Interactive Drill-Downs 🔍
Users can interact directly with visualizations to see the raw underlying data. Clicking any bar, pie slice, or line point in a Chart.js dashboard, or any numerical cell inside a cross-tabulation Pivot Table, will instantly filter the dataset and pop up a modal showing the exact rows that contributed to that aggregate.

### 2. Automated AI Insights ✨
The Viewer features an **✨ AI Insights** button. When clicked, the engine sends a sample of the generated report dataset through `SPPAI`. The AI acts as an expert Data Analyst, returning a 3-bullet executive summary (identifying upward trends, anomalies, or outliers) directly into the UI.

### 3. Threshold Alerts & Webhooks 🚨
In the **Settings Modal**, authors can define a **Webhook URL** and an **Alert Condition** (e.g., `revenue < 10000`). During the background `sppreport_cron.php` execution, the engine evaluates the dataset against the condition. If breached, an HTTP POST request is instantly dispatched to the Webhook URL (e.g. Slack, MS Teams) containing the alert trigger and the top 5 rows.

### 4. Geospatial Mapping 🌍
The Dashboard component supports a **Map** chart type. It dynamically loads Leaflet JS from a CDN and automatically plots `lat/lng` or `latitude/longitude` columns onto an interactive OpenStreetMap.

### 5. Report Version Control & Audit Trail 🕒
Every time you save a report in the Builder, a timestamped `.yml.bak` copy is automatically generated in `etc/sppreports/history/`. A **🕒 History** button in the Builder toolbar allows one-click rollback to a safe state.

## Report Persistence & Scheduling

Reports and templates are persisted using YAML files in the `etc/sppreports/` directory, continuing our philosophy of database-free configuration portability.

When building a report, you can access the **Settings Modal** to configure:
- **Allowed Roles:** Comma-separated list to lock down report viewing (e.g. `admin,manager`).
- **Cron Scheduling:** A standard cron expression (e.g. `0 8 * * *`) that triggers the `sppreport_cron.php` worker to generate and email the report.
- **Export Formats:** Choose between a Rich HTML email with CSV attachment (zero dependencies) or a PDF attachment (requires installing `TCPDF` via Composer).

## Visual Dashboards

SPPReport also includes a Dashboard component to visually graph any saved report using Chart.js. Simply embed this component:

```html
<div 
    data-spp-type="ux" 
    data-spp-path="/spp/modules/spp/sppreport/js/sppreport-dashboard.js"
    data-spp-props='{"report": "Monthly_Sales", "type": "pie"}'>
</div>
```

## Enterprise Security & Access Control 🛡️

Reporting engines have direct access to sensitive database information, making security critical. The `sppreport` module employs multiple layers of defense:

1. **Strict API Authentication:** The `api.php` handler is strictly locked down using `SPPAuth`. Unauthenticated access is rejected, and destructive actions (e.g., saving queries, modifying templates, and version restoration) are hard-coded to require the **Admin** role.
2. **Role-Based Access Control (RBAC):** Every saved report can specify `allowed_roles`. When an end-user attempts to view or export a report, the backend engine validates their active session roles against the report's configuration.
3. **SSRF Protection:** The Federated BI functionality (External DSNs) is restricted entirely to Admin users. This prevents malicious actors from utilizing your server to scan or query internal network IP addresses via Server-Side Request Forgery.
4. **Export Sanitization:** All CSV and XLS exports are automatically sanitized on-the-fly. Any dataset values beginning with executable formula characters (`=`, `+`, `-`, `@`) are prefixed with a single quote (`'`), protecting administrators from spreadsheet macro injection attacks.
