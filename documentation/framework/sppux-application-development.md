# SPPUX Application Development Guide

This guide explains how to build SPP applications with SPPUX, step by step, with practical code examples.

SPPUX is the SPP framework's zero-build frontend runtime. It lets you write reactive browser components as ES modules without Webpack, Vite, Babel, or `node_modules`.

Read this with:

```text
application-development.md
sppux.md
booting-and-app-loading.md
```

---

## 1. What SPPUX Adds to an SPP App

SPP gives you:

```text
app context
config
modules
services
middleware
events
PHP request handling
```

SPPUX adds:

```text
reactive browser components
component auto-mounting
HTML template literals
component state
event binding
frontend service calls
SPPUX UI helpers
optional SPPEX utilities
```

The core SPPUX files are:

```text
spp/modules/spp/sppux/js/sppux.js
spp/modules/spp/sppux/js/spp-loader.js
spp/modules/spp/sppux/js/sppux-ui.js
spp/modules/spp/sppux/js/sppux-bridge.js
spp/modules/spp/sppux/css/sppux.css
```

The PHP facade is:

```text
spp/modules/spp/sppux/class.sppux.php
```

Its class is:

```php
\SPPMod\SPPUX\SPPUX
```

---

## 2. SPPUX Runtime Model

SPPUX has three main pieces.

### 2.1 Runtime

```text
sppux.js
```

Provides:

```text
BaseComponent
html tagged template helper
TrustedHTML
state updates
event binding
signals/computed values
frontend API helpers
```

### 2.2 Loader

```text
spp-loader.js
```

Scans the DOM for:

```html
<div data-spp-component="1" data-spp-type="ux" data-spp-path="..."></div>
```

Then imports the JS component module and mounts it.

### 2.3 PHP Facade

```php
\SPPMod\SPPUX\SPPUX
```

Provides helpers:

```php
SPPUX::runtimePath()
SPPUX::loaderPath()
SPPUX::uiPath()
SPPUX::cssPath()
SPPUX::bridgePath()
SPPUX::componentBase()
SPPUX::componentPath()
SPPUX::component()
SPPUX::render()
SPPUX::boot()
```

---

## 3. Recommended App Structure for SPPUX

For a new SPPUX app, use:

```text
src/myuxapp/
  etc/
    app.yml
    modules.yml
    middleware.yml
    modsconf/
      sppux/
        config.yml

  init.php
  index.php

  comp/
    main.js
    Dashboard.js
    UsersList.js
    SettingsPanel.js

  serv/
    DashboardController.php
    UsersController.php

  services/
    DashboardService.php
    UserService.php

  resources/
    css/
      app.css
    views/
      shell.php

  tests/
  var/
```

Key convention:

```text
src/{app}/comp
```

is the default component directory used by the SPPUX module.

---

## 4. Step 1: Create the App

Create:

```text
src/myuxapp
src/myuxapp/etc
src/myuxapp/comp
src/myuxapp/serv
src/myuxapp/services
src/myuxapp/resources/css
src/myuxapp/var
```

Create:

```text
src/myuxapp/etc/app.yml
```

Example:

```yaml
base_url: /myuxapp
table_prefix: ux_
type: sppux
shared_group: core
etc_path: etc
src_path: src/myuxapp
modules_path: modules
var_path: var
app_init: init.php
```

The `type: sppux` value is useful for tooling and generated apps. The SPPUX runtime still depends on the SPPUX module and page assets being loaded.

---

## 5. Step 2: Enable the SPPUX Module

SPPUX is a framework module:

```text
spp/modules/spp/sppux/module.yml
```

It depends on:

```text
sppview
sppajax
```

Depending on your app's module-loading style, enable modules in one of these places:

```text
src/myuxapp/etc/modules.yml
etc/apps/myuxapp/modules.yml
spp/etc/modules.yml
```

Example app-level `modules.yml`:

```yaml
modules:
  sppview:
    enabled: true
  sppajax:
    enabled: true
  sppux:
    enabled: true
```

If your app uses the existing global module list, confirm `sppux` is enabled there.

---

## 6. Step 3: Configure SPPUX

Create:

```text
src/myuxapp/etc/modsconf/sppux/config.yml
```

Example:

```yaml
runtime_path: spp/modules/spp/sppux/js/sppux.js
loader_path: spp/modules/spp/sppux/js/spp-loader.js
ui_path: spp/modules/spp/sppux/js/sppux-ui.js
grid_path: spp/modules/spp/sppux/js/sppux-grid.js
bridge_path: spp/modules/spp/sppux/js/sppux-bridge.js
css_path: spp/modules/spp/sppux/css/sppux.css
component_base: src/{app}/comp
auto_mount: true
expose_bridge: true
```

Most apps can omit this config and use module defaults.

Useful keys:

```text
runtime_path      Core SPPUX runtime
loader_path       Auto-mount loader
component_base    Component directory, usually src/{app}/comp
auto_mount        Automatically mount data-spp-component nodes
expose_bridge     Expose frontend service bridge helpers
```

---

## 7. Step 4: Create `init.php`

Create:

```text
src/myuxapp/init.php
```

Example:

```php
<?php

\SPP\Registry::register('__myuxapp=>frontend', 'sppux');
```

Keep this file small. Use it for app-level boot flags, service binding, and light setup.

---

## 8. Step 5: Create an SPPUX Entry Page

Create:

```text
src/myuxapp/index.php
```

Minimal standalone SPPUX page:

```php
<?php
require_once __DIR__ . '/../../spp/sppinit.php';

$app = \SPP\App::getApp('myuxapp');
$baseUrl = \SPP\App::getBaseUrl('myuxapp');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My SPPUX App</title>

    <link rel="stylesheet" href="<?php echo \SPPMod\SPPUX\SPPUX::cssPath('myuxapp'); ?>">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/resources/css/app.css">

    <script type="module" src="<?php echo \SPPMod\SPPUX\SPPUX::runtimePath('myuxapp'); ?>"></script>
    <script type="module" src="<?php echo \SPPMod\SPPUX\SPPUX::loaderPath('myuxapp'); ?>"></script>
</head>
<body>
    <?php
    echo \SPPMod\SPPUX\SPPUX::component('main', [
        'title' => 'My SPPUX App',
        'apiBase' => $baseUrl . '/api.php'
    ], 'myuxapp');
    ?>
</body>
</html>
```

The important line is:

```php
SPPUX::component('main', [...], 'myuxapp')
```

It renders a mount node similar to:

```html
<div
  data-spp-component="1"
  data-spp-type="ux"
  data-spp-path="/school1/src/myuxapp/comp/main.js"
  data-spp-props="{...}">
</div>
```

The loader imports `main.js` and mounts it.

---

## 9. Step 6: Create the First Component

Create:

```text
src/myuxapp/comp/main.js
```

Example:

```javascript
export default class Main extends BaseComponent {
    async onInit() {
        this.setState({
            title: this.props.title || 'SPPUX App',
            count: 0,
            status: 'ready'
        });
    }

    render() {
        return html`
            <main class="app-shell">
                <header class="app-header">
                    <h1>${this.state.title}</h1>
                    <span class="status">${this.state.status}</span>
                </header>

                <section class="panel">
                    <p>Count: ${this.state.count}</p>
                    <button @click=${() => this.increment()}>Increment</button>
                    <button @click=${() => this.reset()}>Reset</button>
                </section>
            </main>
        `;
    }

    increment() {
        this.setState({ count: this.state.count + 1 });
    }

    reset() {
        this.setState({ count: 0 });
    }
}
```

Core concepts:

```text
extend BaseComponent
initialize with onInit()
update state with this.setState()
render with html`...`
bind events with @click=${handler}
read server props from this.props
read reactive state from this.state
```

---

## 10. Step 7: Add App CSS

Create:

```text
src/myuxapp/resources/css/app.css
```

Example:

```css
:root {
    --app-bg: #f6f8fb;
    --app-panel: #ffffff;
    --app-text: #172033;
    --app-muted: #64748b;
    --app-primary: #2563eb;
    --app-border: #dbe3ef;
}

body {
    margin: 0;
    font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    background: var(--app-bg);
    color: var(--app-text);
}

.app-shell {
    min-height: 100vh;
    padding: 24px;
}

.app-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 20px;
}

.app-header h1 {
    margin: 0;
    font-size: 28px;
}

.status {
    color: var(--app-muted);
    font-size: 14px;
}

.panel {
    background: var(--app-panel);
    border: 1px solid var(--app-border);
    border-radius: 8px;
    padding: 20px;
}

button {
    border: 0;
    border-radius: 6px;
    background: var(--app-primary);
    color: #fff;
    padding: 9px 14px;
    margin-right: 8px;
    cursor: pointer;
}
```

SPPUX can also use its own CSS:

```php
SPPUX::cssPath()
```

for framework UI helpers.

---

## 11. Component Lifecycle

Common lifecycle methods:

```javascript
export default class MyComponent extends BaseComponent {
    async onInit() {
        // Runs before first render.
    }

    async onMount() {
        // Runs after the component is rendered into the DOM.
    }

    render() {
        return html`<div>Hello</div>`;
    }
}
```

Typical use:

```text
onInit      initialize state, read props, fetch first data
render      return HTML
onMount     attach browser APIs, focus fields, measure DOM
```

Use `this.setState()` to update UI:

```javascript
this.setState({ loading: true });
```

---

## 12. Props

Server-side PHP passes props through `SPPUX::component()`.

PHP:

```php
echo \SPPMod\SPPUX\SPPUX::component('main', [
    'title' => 'Dashboard',
    'user' => [
        'id' => 10,
        'name' => 'Admin'
    ]
], 'myuxapp');
```

JavaScript:

```javascript
export default class Main extends BaseComponent {
    async onInit() {
        this.setState({
            title: this.props.title,
            user: this.props.user
        });
    }

    render() {
        return html`
            <h1>${this.state.title}</h1>
            <p>Welcome ${this.state.user.name}</p>
        `;
    }
}
```

Props are serialized to JSON and stored in:

```html
data-spp-props
```

---

## 13. Event Binding

SPPUX supports event binding in templates:

```javascript
render() {
    return html`
        <button @click=${() => this.save()}>Save</button>
        <input @input=${(event) => this.setState({ name: event.target.value })}>
    `;
}
```

Examples:

```javascript
@click=${handler}
@input=${handler}
@change=${handler}
@submit=${handler}
```

For forms, prevent default browser submit:

```javascript
render() {
    return html`
        <form @submit=${(event) => this.submit(event)}>
            <input name="title" .value=${this.state.title || ''}>
            <button type="submit">Save</button>
        </form>
    `;
}

async submit(event) {
    event.preventDefault();
    await this.save();
}
```

---

## 14. Conditional Rendering

Use JavaScript expressions inside `html`.

```javascript
render() {
    return html`
        ${this.state.loading
            ? html`<p>Loading...</p>`
            : html`<p>Loaded</p>`}
    `;
}
```

Empty state:

```javascript
renderUsers() {
    if (!this.state.users.length) {
        return html`<p class="empty">No users found.</p>`;
    }

    return html`
        <ul>
            ${this.state.users.map(user => html`
                <li>${user.name}</li>
            `)}
        </ul>
    `;
}
```

---

## 15. Lists

Render arrays with `map`.

```javascript
render() {
    return html`
        <section class="users">
            ${this.state.users.map(user => html`
                <article class="user-row">
                    <strong>${user.name}</strong>
                    <span>${user.email}</span>
                    <button @click=${() => this.editUser(user.id)}>Edit</button>
                </article>
            `)}
        </section>
    `;
}
```

Keep list item markup small. Move larger item rendering into helper methods:

```javascript
renderUser(user) {
    return html`
        <article class="user-row">
            <strong>${user.name}</strong>
        </article>
    `;
}
```

---

## 16. Component Composition

You can split UI into multiple component files.

```text
src/myuxapp/comp/main.js
src/myuxapp/comp/UserCard.js
src/myuxapp/comp/StatsPanel.js
```

Import child helpers or classes:

```javascript
import StatsPanel from './StatsPanel.js';

export default class Main extends BaseComponent {
    async onInit() {
        this.statsPanel = new StatsPanel(this.app, this.container, {
            title: 'Today'
        });
        await this.statsPanel.onInit?.();
    }

    render() {
        return html`
            <main>
                ${this.statsPanel.render()}
            </main>
        `;
    }
}
```

For many cases, simple render helper functions are enough:

```javascript
function StatCard(stat) {
    return html`
        <article class="stat-card">
            <strong>${stat.value}</strong>
            <span>${stat.label}</span>
        </article>
    `;
}
```

Then:

```javascript
${this.state.stats.map(StatCard)}
```

---

## 17. Frontend API Calls

SPPUX `BaseComponent` initializes helper methods for API calls.

Common options:

```javascript
await this.api('action_name', { id: 1 });
await this.apiPost('save_item', { title: 'Hello' });
await this.service('service_name', { id: 1 });
```

The exact backend endpoint depends on the bridge available on the page.

The SPPUX facade can expose a bridge:

```php
\SPPMod\SPPUX\SPPUX::boot('myuxapp');
```

which registers frontend helpers for:

```text
api
apiPost
callAppService
streamService
securePayload
```

---

## 18. Creating a PHP API Endpoint

Create:

```text
src/myuxapp/api.php
```

Example:

```php
<?php
require_once __DIR__ . '/../../spp/sppinit.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'dashboard_stats':
            echo json_encode([
                'success' => true,
                'data' => [
                    'users' => 42,
                    'orders' => 12,
                    'status' => 'online'
                ]
            ]);
            break;

        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Unknown action'
            ]);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
```

Then call it from SPPUX:

```javascript
async loadStats() {
    this.setState({ loading: true });

    const response = await fetch(`${this.props.apiBase}?action=dashboard_stats`, {
        credentials: 'same-origin'
    });

    const result = await response.json();

    this.setState({
        loading: false,
        stats: result.data
    });
}
```

---

## 19. Creating a PHP Service

Create:

```text
src/myuxapp/services/DashboardService.php
```

```php
<?php
namespace App\Myuxapp\Services;

class DashboardService
{
    public function stats(): array
    {
        return [
            'users' => 42,
            'orders' => 12,
            'status' => 'online'
        ];
    }
}
```

Use it in `api.php`:

```php
case 'dashboard_stats':
    $service = \SPP\App::getApp()->make(\App\Myuxapp\Services\DashboardService::class);
    echo json_encode([
        'success' => true,
        'data' => $service->stats()
    ]);
    break;
```

This keeps business logic out of the API script.

---

## 20. Loading Data in a Component

```javascript
export default class Dashboard extends BaseComponent {
    async onInit() {
        this.setState({
            loading: true,
            stats: null,
            error: ''
        });

        await this.loadStats();
    }

    async loadStats() {
        try {
            const response = await fetch(`${this.props.apiBase}?action=dashboard_stats`, {
                credentials: 'same-origin'
            });
            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message || 'Unable to load stats');
            }

            this.setState({
                loading: false,
                stats: result.data
            });
        } catch (error) {
            this.setState({
                loading: false,
                error: error.message
            });
        }
    }

    render() {
        if (this.state.loading) {
            return html`<p>Loading dashboard...</p>`;
        }

        if (this.state.error) {
            return html`<p class="error">${this.state.error}</p>`;
        }

        return html`
            <section class="dashboard-grid">
                <article>
                    <strong>${this.state.stats.users}</strong>
                    <span>Users</span>
                </article>
                <article>
                    <strong>${this.state.stats.orders}</strong>
                    <span>Orders</span>
                </article>
                <article>
                    <strong>${this.state.stats.status}</strong>
                    <span>Status</span>
                </article>
            </section>
        `;
    }
}
```

---

## 21. Forms

Component:

```javascript
export default class SettingsForm extends BaseComponent {
    async onInit() {
        this.setState({
            saving: false,
            message: '',
            form: {
                siteName: this.props.siteName || '',
                email: this.props.email || ''
            }
        });
    }

    updateField(field, value) {
        this.setState({
            form: {
                ...this.state.form,
                [field]: value
            }
        });
    }

    async submit(event) {
        event.preventDefault();
        this.setState({ saving: true, message: '' });

        const response = await fetch(this.props.apiBase, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'save_settings',
                ...this.state.form
            })
        });

        const result = await response.json();

        this.setState({
            saving: false,
            message: result.success ? 'Saved' : result.message
        });
    }

    render() {
        return html`
            <form class="settings-form" @submit=${event => this.submit(event)}>
                <label>
                    <span>Site name</span>
                    <input
                        type="text"
                        .value=${this.state.form.siteName}
                        @input=${event => this.updateField('siteName', event.target.value)}>
                </label>

                <label>
                    <span>Email</span>
                    <input
                        type="email"
                        .value=${this.state.form.email}
                        @input=${event => this.updateField('email', event.target.value)}>
                </label>

                <button type="submit" ?disabled=${this.state.saving}>
                    ${this.state.saving ? 'Saving...' : 'Save'}
                </button>

                ${this.state.message ? html`<p>${this.state.message}</p>` : ''}
            </form>
        `;
    }
}
```

Backend:

```php
case 'save_settings':
    $siteName = trim($_POST['siteName'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($siteName === '') {
        echo json_encode([
            'success' => false,
            'message' => 'Site name is required'
        ]);
        break;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'siteName' => $siteName,
            'email' => $email
        ]
    ]);
    break;
```

---

## 22. CSRF and Secure Requests

If global CSRF middleware is active, state-changing requests should include a CSRF token.

Typical token source:

```php
$csrf = \SPP\SPPSession::getCsrfToken();
```

Pass it to the component:

```php
echo \SPPMod\SPPUX\SPPUX::component('main', [
    'csrfToken' => \SPP\SPPSession::getCsrfToken(),
    'apiBase' => \SPP\App::getBaseUrl('myuxapp') . '/api.php'
], 'myuxapp');
```

Send it:

```javascript
const response = await fetch(this.props.apiBase, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
        'X-CSRF-TOKEN': this.props.csrfToken,
        'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: new URLSearchParams({
        action: 'save_settings',
        siteName: this.state.form.siteName
    })
});
```

---

## 23. SPPUX UI Helpers

If `sppux-ui.js` is loaded, you can use:

```javascript
SPPUX.Notify.show('Saved', 'success');
SPPUX.Modal.open('Title', 'Content', [
    { label: 'Close', fn: modal => modal.close() }
]);
SPPUX.Drawer.open('Details', html`<p>Panel content</p>`, 'right');
SPPUX.Spotlight.open([
    { title: 'Dashboard', icon: 'D' },
    { title: 'Users', icon: 'U' }
], item => console.log(item));
```

Example in a component:

```javascript
async save() {
    await this.submitData();
    if (window.SPPUX?.Notify) {
        SPPUX.Notify.show('Record saved', 'success');
    }
}
```

Load UI helpers:

```php
<script type="module" src="<?php echo \SPPMod\SPPUX\SPPUX::uiPath('myuxapp'); ?>"></script>
```

or through:

```php
\SPPMod\SPPUX\SPPUX::boot('myuxapp');
```

when using SPP view integration.

---

## 24. Routing Inside SPPUX

SPPUX can be used in two routing styles.

### 24.1 Server-Routed Pages

Each SPP route/page renders an SPPUX component.

Example:

```php
echo \SPPMod\SPPUX\SPPUX::component('Dashboard', [], 'myuxapp');
```

Use this when:

```text
SEO matters
routes are permission-heavy
pages are mostly independent
you want SPP/PHP to choose the page
```

### 24.2 Client-Side SPA Routing

One PHP shell loads one root component. SPPUX then switches views in browser state.

Example:

```javascript
export default class Main extends BaseComponent {
    async onInit() {
        this.setState({ route: location.pathname });

        window.addEventListener('popstate', () => {
            this.setState({ route: location.pathname });
        });
    }

    go(path) {
        history.pushState({}, '', path);
        this.setState({ route: path });
    }

    render() {
        return html`
            <nav>
                <button @click=${() => this.go('/myuxapp')}>Dashboard</button>
                <button @click=${() => this.go('/myuxapp/settings')}>Settings</button>
            </nav>

            ${this.state.route.endsWith('/settings')
                ? this.renderSettings()
                : this.renderDashboard()}
        `;
    }

    renderDashboard() {
        return html`<section>Dashboard</section>`;
    }

    renderSettings() {
        return html`<section>Settings</section>`;
    }
}
```

Use this when:

```text
the app is dashboard-like
transitions should feel instant
the shell owns most navigation
SEO is not the main concern
```

---

## 25. Declarative Template Components

The loader supports declarative templates:

```html
<template data-spp-ux="WelcomeCard">
    <section class="welcome-card">
        <h2>${props.title}</h2>
        <p>${props.message}</p>
    </section>
</template>

<div
    data-spp-component="WelcomeCard"
    data-spp-props='{"title":"Hello","message":"Loaded from a template"}'>
</div>
```

The loader compiles the template into a `BaseComponent` subclass.

Use declarative templates for small islands. Use JS component files for larger interactive features.

---

## 26. Server-Side Rendering Placeholder

`SPPUX::component()` supports `__ssr` content.

```php
echo \SPPMod\SPPUX\SPPUX::component('main', [
    '__ssr' => '<p>Loading app...</p>',
    'title' => 'Dashboard'
], 'myuxapp');
```

This content appears before the component mounts.

Use this for:

```text
loading states
SEO fallback
progressive enhancement
accessibility fallback text
```

---

## 27. Partial Hydration Islands

`SPPUX::component()` supports an island mode:

```php
echo \SPPMod\SPPUX\SPPUX::component('Chart', [
    '__island' => 'visible',
    'dataset' => 'sales'
], 'myuxapp');
```

The output includes:

```html
data-spp-island="visible"
```

Use island metadata to describe lazy hydration strategies such as:

```text
visible
idle
media
```

Implementation depends on the active loader/runtime behavior.

---

## 28. Using SPPEX Utilities

SPPEX modules are optional add-ons:

```text
sppex.js
sppex-pro.js
sppex-ultra.js
```

Load them after SPPUX:

```html
<script type="module" src="/school1/spp/modules/spp/sppux/js/sppex.js"></script>
<script type="module" src="/school1/spp/modules/spp/sppux/js/sppex-pro.js"></script>
<script type="module" src="/school1/spp/modules/spp/sppux/js/sppex-ultra.js"></script>
```

Use SPPEX for:

```text
query/cache patterns
forms
motion
drag and drop
virtual lists
infinite scroll
selects
date pickers
data grids
tree views
dropzones
context menus
pagination
breadcrumbs
clipboard helpers
websocket helpers
```

Only load the tiers your app needs.

---

## 29. Separate HTML/PHP Shell Files

SPPUX apps do not have to mount everything through `SPPUX::component()` in a single central page. A second supported pattern is the Lekhak-style standalone shell:

```text
server-rendered HTML/PHP/Blade shell
  -> loads app CSS and SPP/SPPUX scripts
  -> defines a frontend config object
  -> loads a shell JavaScript module
  -> shell JavaScript imports view components on demand
  -> components render into a known container
  -> API calls go to a separate PHP endpoint
```

This is useful for admin workspaces, CMS backends, and large dashboards where the surrounding navigation/layout is server-rendered but the main workspace is reactive.

Lekhak uses this shape:

```text
src/lekhak/resources/views/standalone-admin.blade.php
src/lekhak/resources/admin/standalone-shell.js
src/lekhak/resources/admin-api.php
src/lekhak/comp/lekhak.js
src/lekhak/comp/content.js
src/lekhak/comp/settings.js
```

### 29.1 Directory Layout

For a new app:

```text
src/myuxapp/
  resources/
    views/
      standalone-admin.php
    admin/
      shell.js
      admin.css
    admin-api.php

  comp/
    dashboard.js
    content.js
    settings.js

  services/
    DashboardService.php
```

You can use `.php`, `.blade.php`, or another server-rendered template format. The key idea is that the shell file is a normal server-rendered HTML document.

### 29.2 Server-Rendered Shell File

Create:

```text
src/myuxapp/resources/views/standalone-admin.php
```

Example:

```php
<?php
$baseUrl = rtrim(defined('APP_BASE_URI') ? APP_BASE_URI : '', '/');
$appBase = \SPP\App::getBaseUrl('myuxapp');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MyUX Admin</title>

    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/spp/res/css/spp.css">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/src/myuxapp/resources/admin/admin.css">

    <script type="module" src="<?php echo \SPPMod\SPPUX\SPPUX::runtimePath('myuxapp'); ?>"></script>
</head>
<body>
    <aside class="sidebar">
        <a href="#dashboard" data-view="dashboard">Dashboard</a>
        <a href="#content" data-view="content">Content</a>
        <a href="#settings" data-view="settings">Settings</a>
    </aside>

    <main class="workspace">
        <header class="workspace-header">
            <h1 id="view-title">Dashboard</h1>
            <div id="header-actions"></div>
        </header>

        <section id="view-container">
            <p>Loading workspace...</p>
        </section>

        <div id="view-loader" class="view-loader">Loading...</div>
    </main>

    <script>
        window.MYUX_CONFIG = {
            app: 'myuxapp',
            baseUrl: '<?php echo $baseUrl; ?>',
            appBase: '<?php echo $appBase; ?>',
            apiBase: '<?php echo $appBase; ?>/resources/admin-api.php',
            componentBase: '<?php echo $baseUrl; ?>/src/myuxapp/comp',
            debug: <?php echo defined('SPP_DEBUG') && SPP_DEBUG ? 'true' : 'false'; ?>
        };
    </script>

    <script type="module" src="<?php echo $baseUrl; ?>/src/myuxapp/resources/admin/shell.js"></script>
</body>
</html>
```

This page does not need a `data-spp-component` mount node. Instead, `shell.js` owns the workspace container and imports components directly.

### 29.3 Rendering the Shell from a Route

You can render the shell from a controller, page callback, or a plain entry file.

Plain entry file:

```text
src/myuxapp/admin.php
```

```php
<?php
require_once __DIR__ . '/../../spp/sppinit.php';

new \SPP\App('myuxapp');

require __DIR__ . '/resources/views/standalone-admin.php';
```

If your app uses a renderer such as Blade or Drishyam, point the route/page config to the shell view instead.

### 29.4 Shell JavaScript

Create:

```text
src/myuxapp/resources/admin/shell.js
```

Example:

```javascript
class MyUxAdminShell {
    constructor() {
        this.config = window.MYUX_CONFIG || {};
        this.container = document.getElementById('view-container');
        this.loader = document.getElementById('view-loader');
        this.titleEl = document.getElementById('view-title');
        this.headerActions = document.getElementById('header-actions');
        this.activeComponent = null;
        this.version = '2026_05_25_v1';

        window.admin = this;
        window.spp_admin = this;
    }

    async init() {
        this.bindNavigation();
        this.loadFromHash();
        window.addEventListener('hashchange', () => this.loadFromHash());
    }

    bindNavigation() {
        document.addEventListener('click', event => {
            const link = event.target.closest('[data-view]');
            if (!link) return;

            event.preventDefault();
            location.hash = link.dataset.view;
        });
    }

    loadFromHash() {
        const view = location.hash.replace('#', '') || 'dashboard';
        this.loadView(view);
    }

    async loadView(view) {
        this.showLoader(true);

        if (this.activeComponent?.dispose) {
            this.activeComponent.dispose();
        }

        const viewMap = {
            dashboard: 'dashboard',
            content: 'content',
            settings: 'settings'
        };

        const componentName = viewMap[view] || 'dashboard';
        const cache = this.config.debug ? `t=${Date.now()}` : `v=${this.version}`;
        const modulePath = `${this.config.componentBase}/${componentName}.js?${cache}`;

        try {
            const module = await import(modulePath);
            const Component = module.default;

            this.container.innerHTML = '';
            this.headerActions.innerHTML = '';

            this.activeComponent = new Component(this, this.container, {
                view,
                apiBase: this.config.apiBase
            });

            await this.activeComponent.onInit?.();
            this.titleEl.textContent = this.titleFor(view);
            this.activeComponent.update();
            await this.activeComponent.onMount?.();
        } catch (error) {
            console.error(error);
            this.container.innerHTML = `
                <section class="error-panel">
                    <h2>View failed to load</h2>
                    <p>${error.message}</p>
                </section>
            `;
        } finally {
            this.showLoader(false);
        }
    }

    async api(action, params = {}) {
        const response = await fetch(this.config.apiBase, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, ...params })
        });

        return response.json();
    }

    async apiPost(action, params = {}) {
        return this.api(action, params);
    }

    notify(message, type = 'info') {
        console.log(`[${type}] ${message}`);
    }

    showLoader(show) {
        if (!this.loader) return;
        this.loader.hidden = !show;
    }

    titleFor(view) {
        return view.charAt(0).toUpperCase() + view.slice(1);
    }
}

const shell = new MyUxAdminShell();
shell.init();

export default shell;
```

The shell becomes the `admin` object passed to each component:

```javascript
new Component(this, this.container, props);
```

That means components can call:

```javascript
this.admin.api(...)
this.api(...)
this.admin.notify(...)
```

depending on how the component is written.

### 29.5 Component for Shell-Loaded Views

Create:

```text
src/myuxapp/comp/dashboard.js
```

Example:

```javascript
import BaseComponent from '../../../spp/modules/spp/sppux/js/BaseComponent.js';
import { html } from '../../../spp/modules/spp/sppux/js/sppux.js';

export default class DashboardView extends BaseComponent {
    async onInit() {
        this.setState({
            loading: true,
            stats: null,
            error: ''
        });
    }

    async onMount() {
        await this.loadStats();
    }

    async loadStats() {
        try {
            const result = await this.api('dashboard_stats', {}, { lock: false });

            if (!result.success) {
                throw new Error(result.message || 'Unable to load dashboard');
            }

            this.setState({
                loading: false,
                stats: result.data
            });
        } catch (error) {
            this.setState({
                loading: false,
                error: error.message
            });
        }
    }

    render() {
        if (this.state.loading) {
            return html`<p>Loading dashboard...</p>`;
        }

        if (this.state.error) {
            return html`<p class="error">${this.state.error}</p>`;
        }

        return html`
            <section class="dashboard">
                <article class="metric">
                    <strong>${this.state.stats.users}</strong>
                    <span>Users</span>
                </article>
                <article class="metric">
                    <strong>${this.state.stats.pages}</strong>
                    <span>Pages</span>
                </article>
                <button @click=${() => this.loadStats()}>Refresh</button>
            </section>
        `;
    }
}
```

This import style is common in Lekhak components because the shell imports components directly rather than using the generic loader.

### 29.6 Separate Admin API File

Create:

```text
src/myuxapp/resources/admin-api.php
```

Example:

```php
<?php
if (!defined('SPP_BASE_DIR')) {
    define('SPP_BASE_DIR', dirname(__DIR__, 3) . '/spp');
}

require_once SPP_BASE_DIR . '/sppinit.php';

$appname = 'myuxapp';
try {
    try {
        \SPP\Scheduler::getProcObj($appname);
    } catch (\Throwable $e) {
        new \SPP\App($appname, false, 1);
    }
    \SPP\Scheduler::setContext($appname);
} catch (\Throwable $e) {
    error_log('MyUX API context failed: ' . $e->getMessage());
}

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$json = json_decode($raw, true);
$request = is_array($json) ? $json : $_REQUEST;
$action = $request['action'] ?? '';

function send_json(bool $success, array $data = [], string $message = ''): void {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

try {
    switch ($action) {
        case 'dashboard_stats':
            send_json(true, [
                'users' => 42,
                'pages' => 18
            ]);

        default:
            http_response_code(404);
            send_json(false, [], 'Unknown action');
    }
} catch (\Throwable $e) {
    http_response_code(500);
    send_json(false, [], $e->getMessage());
}
```

This mirrors the Lekhak pattern: the API file is self-contained, forces the correct app context, authenticates if needed, and emits JSON.

### 29.7 Navigation Pattern

The shell can use hash navigation:

```html
<a href="#dashboard" data-view="dashboard">Dashboard</a>
<a href="#content" data-view="content">Content</a>
<a href="#settings" data-view="settings">Settings</a>
```

The shell listens for:

```javascript
hashchange
click on [data-view]
```

and dynamically imports:

```text
src/myuxapp/comp/dashboard.js
src/myuxapp/comp/content.js
src/myuxapp/comp/settings.js
```

This keeps the first page load small and lets the app load views on demand.

### 29.8 When to Use This Pattern

Use separate shell files when:

```text
you need a rich admin workspace
navigation/sidebar/header should be server-rendered
many SPPUX views should load on demand
the app needs a standalone backend API file
you want a CMS-like architecture similar to Lekhak
you want the shell to manage compatibility helpers like window.admin
```

Use `SPPUX::component()` direct mounting when:

```text
the page has one or a few isolated components
the generic SPPUX loader is enough
you do not need a custom shell/router
you want PHP to render each component mount point
```

### 29.9 Shell Pattern Checklist

```text
server shell file exists
shell file loads SPP/SPPUX assets
shell file defines window.{APP}_CONFIG
shell file has a stable view container
shell JS imports components by view name
components export default BaseComponent subclasses
API endpoint forces correct app context
navigation updates hash or route state
previous components are disposed when switching views
errors render into the view container
```

---

## 30. A Complete Small SPPUX App

Directory:

```text
src/todoapp/
  etc/
    app.yml
  init.php
  index.php
  api.php
  services/
    TodoService.php
  comp/
    main.js
  resources/
    css/
      app.css
```

`src/todoapp/etc/app.yml`:

```yaml
base_url: /todoapp
type: sppux
table_prefix: todo_
shared_group: core
etc_path: etc
src_path: src/todoapp
modules_path: modules
var_path: var
app_init: init.php
```

`src/todoapp/index.php`:

```php
<?php
require_once __DIR__ . '/../../spp/sppinit.php';

$baseUrl = \SPP\App::getBaseUrl('todoapp');
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Todo App</title>
    <link rel="stylesheet" href="<?php echo \SPPMod\SPPUX\SPPUX::cssPath('todoapp'); ?>">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/resources/css/app.css">
    <script type="module" src="<?php echo \SPPMod\SPPUX\SPPUX::runtimePath('todoapp'); ?>"></script>
    <script type="module" src="<?php echo \SPPMod\SPPUX\SPPUX::loaderPath('todoapp'); ?>"></script>
</head>
<body>
    <?php
    echo \SPPMod\SPPUX\SPPUX::component('main', [
        'apiBase' => $baseUrl . '/api.php',
        '__ssr' => '<p>Loading todos...</p>'
    ], 'todoapp');
    ?>
</body>
</html>
```

`src/todoapp/services/TodoService.php`:

```php
<?php
namespace App\Todoapp\Services;

class TodoService
{
    public function all(): array
    {
        return [
            ['id' => 1, 'title' => 'Create SPPUX app', 'done' => true],
            ['id' => 2, 'title' => 'Add API endpoint', 'done' => false]
        ];
    }
}
```

`src/todoapp/api.php`:

```php
<?php
require_once __DIR__ . '/../../spp/sppinit.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

try {
    if ($action === 'todos') {
        $service = \SPP\App::getApp()->make(\App\Todoapp\Services\TodoService::class);
        echo json_encode([
            'success' => true,
            'data' => $service->all()
        ]);
        return;
    }

    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Unknown action'
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
```

`src/todoapp/comp/main.js`:

```javascript
export default class Main extends BaseComponent {
    async onInit() {
        this.setState({
            loading: true,
            error: '',
            todos: []
        });

        await this.loadTodos();
    }

    async loadTodos() {
        try {
            const response = await fetch(`${this.props.apiBase}?action=todos`, {
                credentials: 'same-origin'
            });
            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message || 'Unable to load todos');
            }

            this.setState({
                loading: false,
                todos: result.data
            });
        } catch (error) {
            this.setState({
                loading: false,
                error: error.message
            });
        }
    }

    toggle(id) {
        this.setState({
            todos: this.state.todos.map(todo => {
                if (todo.id !== id) return todo;
                return { ...todo, done: !todo.done };
            })
        });
    }

    render() {
        if (this.state.loading) {
            return html`<main class="todo-shell"><p>Loading...</p></main>`;
        }

        if (this.state.error) {
            return html`<main class="todo-shell"><p class="error">${this.state.error}</p></main>`;
        }

        return html`
            <main class="todo-shell">
                <header>
                    <h1>Todos</h1>
                    <button @click=${() => this.loadTodos()}>Refresh</button>
                </header>

                <section class="todo-list">
                    ${this.state.todos.map(todo => html`
                        <label class="todo-row">
                            <input
                                type="checkbox"
                                ?checked=${todo.done}
                                @change=${() => this.toggle(todo.id)}>
                            <span class="${todo.done ? 'done' : ''}">${todo.title}</span>
                        </label>
                    `)}
                </section>
            </main>
        `;
    }
}
```

`src/todoapp/resources/css/app.css`:

```css
body {
    margin: 0;
    font-family: system-ui, sans-serif;
    background: #f4f7fb;
    color: #172033;
}

.todo-shell {
    max-width: 720px;
    margin: 0 auto;
    padding: 32px;
}

.todo-shell header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.todo-list {
    background: #fff;
    border: 1px solid #dbe3ef;
    border-radius: 8px;
    overflow: hidden;
}

.todo-row {
    display: flex;
    gap: 10px;
    padding: 14px 16px;
    border-bottom: 1px solid #edf2f7;
}

.todo-row:last-child {
    border-bottom: 0;
}

.done {
    color: #64748b;
    text-decoration: line-through;
}

.error {
    color: #b91c1c;
}
```

---

## 31. Debugging SPPUX Apps

### Component does not mount

Check the mount node:

```html
data-spp-component="1"
data-spp-type="ux"
data-spp-path="..."
```

Check browser console for dynamic import errors.

Check that the component file exports a default class:

```javascript
export default class Main extends BaseComponent {}
```

### `BaseComponent` or `html` is undefined

Confirm `sppux.js` is loaded before `spp-loader.js`.

```html
<script type="module" src=".../sppux.js"></script>
<script type="module" src=".../spp-loader.js"></script>
```

### Props are empty

Inspect the rendered node:

```html
data-spp-props="..."
```

If writing by hand, ensure valid JSON. Prefer `SPPUX::component()` so PHP escapes props safely.

### API calls fail

Check:

```text
apiBase prop
CSRF token for POST
same-origin credentials
JSON response format
PHP errors in server logs
```

### State changes but UI does not update

Use:

```javascript
this.setState({ key: value });
```

Do not mutate nested state silently without calling `setState`.

Instead of:

```javascript
this.state.todos.push(todo);
```

use:

```javascript
this.setState({
    todos: [...this.state.todos, todo]
});
```

---

## 32. Verification Checklist

Before calling an SPPUX app ready:

```text
app context resolves correctly
sppux module is enabled
runtimePath() returns a reachable URL
loaderPath() returns a reachable URL
component_base points to src/{app}/comp
main.js exists and exports default class
SPPUX mount node has data-spp-component
props are valid JSON
API endpoints return JSON
POST requests include CSRF token when required
browser console has no import/runtime errors
component state updates through setState()
```

Useful probes:

```php
echo \SPP\App::getBaseUrl('myuxapp');
echo \SPPMod\SPPUX\SPPUX::runtimePath('myuxapp');
echo \SPPMod\SPPUX\SPPUX::loaderPath('myuxapp');
echo \SPPMod\SPPUX\SPPUX::componentPath('main', 'myuxapp');
```

---

## 33. Best Practices

Use SPPUX for:

```text
dashboards
admin panels
interactive forms
settings screens
data tables
workflow UIs
SPA-like app shells
progressive enhancement islands
```

Keep components small.

Put business logic in PHP services.

Put request logic in `api.php`, `serv/`, or controllers.

Pass only necessary data through props.

Use `fetch` or the bridge for fresh server data.

Use `this.setState()` for all reactive UI changes.

Use `SPPUX::component()` instead of hand-writing mount nodes when rendering from PHP.

Load SPPEX tiers only when needed.

Keep app CSS scoped with app-specific class names.

Prefer self-contained app layout for new SPPUX apps.

---

[Back to Framework Index](index.md)
