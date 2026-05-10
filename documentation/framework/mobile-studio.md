# Mobile Studio Pro — Complete Documentation

> **Version**: 4.0 (Satya Studio Pro) | **Last Updated**: May 2026  
> **Audience**: Pro Users, Designers, & Elite Developers (Total Nerds)

---

## 0. The Unified Framework (Developer Special)

> [!IMPORTANT]
> **Breaking Change Alert**: As of v3.0, `BaseComponent.js` has been deprecated and merged into the core `sppux.js` framework. There is now only **one** source of truth for the reactive engine.

### Why the Unification?
Previously, the framework was fragmented between a global script (`sppux.js`) and an ES module (`BaseComponent.js`). This led to:
1. **Feature Drifts**: New features like `domFilter` or `guard` were added to one but not the other.
2. **Event Conflicts**: Two different event dispatchers fighting for the same click.
3. **Bloat**: Loading two versions of the same core logic.

### How it works now
`sppux.js` is now a dual-mode asset. It acts as a standard global script for legacy pages and as a modern ES module for new components. 

**The nerd way to extend it:**
```js
// Standard import from the framework directory
import { BaseComponent, html } from '../../../../spp/modules/spp/sppux/js/sppux.js';

export default class MyAdvancedComponent extends BaseComponent {
    // Look Ma, no duplication!
}
```

---

## Part 1: User Guide

### What is Mobile Studio Pro?

Mobile Studio Pro is a **visual drag-and-drop builder** for designing mobile app screens. Think of it like Figma or Canva, but specifically for creating real mobile app layouts that can be exported to Flutter or PWA.

### Getting Started

1. Open your browser and go to your SPP installation URL + `/sppmobile`
2. You'll see the **Project Portfolio** — a list of your mobile app projects
3. Click **Create New Project** to start a new app, or click an existing one to open it

### The Studio Interface

When you open a project, you see three panels:

```
┌──────────────────────────────────────────────────────────────┐
│  Header: App Name | Visual/Code/Assets | iOS/Android | Zoom │
├────────────┬────────────────────────┬────────────────────────┤
│            │                        │                        │
│  Left:     │  Center:               │  Right:                │
│  Navigator │  Device Preview        │  Inspector             │
│            │  (your phone screen)   │  (properties panel)    │
│  - Screens │                        │  - Colors, text, etc.  │
│  - Library │                        │  - Layout settings     │
│  - Search  │                        │  - Actions             │
│            │                        │                        │
├────────────┴────────────────────────┴────────────────────────┤
```

### Using the Library

The left sidebar has a **Library** section with two tabs:

- **Atoms** — Basic building blocks: buttons, text, images, containers, inputs, etc.
- **Blueprints** — Pre-built full-screen layouts: dashboards, login pages, checkout flows, etc.

**To add a component**: Click any Atom or Blueprint to add it to the current screen.

**To search**: Type in the search box. It filters items **instantly** as you type. Works in both Atoms and Blueprints tabs. Clear the search box to see everything again.

### Working with Screens

- Each app has multiple **screens** (like pages in a website)
- Click a screen name in the left panel to switch to it
- Click **+ Add Screen** to create a new one
- Each screen has a type (home, settings, profile, etc.)

### The Inspector (Right Panel)

When you click on a component in the device preview:

- **Properties tab** — Edit text, colors, sizes, padding, margins
- **Actions tab** — Set what happens when the user taps (navigate, API call, etc.)
- **Style tab** — Fine-tune appearance (border radius, elevation, opacity)

### 1.5 Pro Features: Data & Reactivity

Mobile Studio Pro introduces a full-stack data layer directly in the visual builder.

*   **DataManager**: Open the **Data** tab to manage Global State. Create variables (e.g., `userScore`, `themeColor`) that persist across the entire session.
*   **Property Binding**: Click the **🔗 Link** icon next to any property (Text, Image Source, Color) to bind it to a variable. The value will update **instantly** whenever the state changes.
*   **Conditional Actions**: Build branching logic in your button clicks. 
    *   *Example*: `If {{state.count}} > 10 → Navigate to WinScreen Else → Show Notification`.

### 1.6 Symbols & Reusability

Stop recreating the same header on every screen.
1. Right-click a component and select **✨ Convert to Symbol**.
2. Give it a name (e.g., "Main Header").
3. Drag your Symbol from the **Symbols Library** onto any other screen.
4. **The Magic**: Edit the master Symbol once, and every instance in your app updates automatically.

### 1.7 Responsive Design
Use the **Breakpoint Toolbar** at the top of the preview to test your designs on:
*   📱 **Mobile** (Portrait)
*   📟 **Tablet** (Android/iOS)
*   🖥️ **Desktop** (Wide)

Adjust **Zoom** (25% - 200%) to get pixel-perfect alignment.

### Loading Screen

When you first open a project, you see a **loading spinner** with "Orchestrating Workspace..." text. This means the studio is:
1. Loading your project configuration
2. Loading the blueprint library
3. Preparing the visual editor

This usually takes 2-5 seconds depending on your connection.

### 1.9 Pro-User Quick Start (Non-Developers)
If you're here to build an app without writing code, follow these three rules of the "Pro" workflow:
1.  **Select, then Inspect**: Click any element on the phone screen. Use the right panel (Inspector) to change its looks.
2.  **Bind for Live Data**: Use the 🔗 icon next to text fields to connect them to the **DataManager**. This makes your app reactive.
3.  **Use Symbols for Consistency**: If you have a navbar or footer, convert it to a **Symbol** (Right-click → Convert). It will then appear in the Symbols tab for use on other screens.

---

## Part 2: Developer Handbook

### Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                            Satya Studio Pro Shell                            │
│                                                                             │
│  ┌────────────────┐      ┌─────────────────────┐      ┌──────────────────┐  │
│  │  mobile-app.js │      │      mobile.js      │      │   Binding Engine │  │
│  │ (Project Mgmt) │◀────▶│   (View Engine)     │◀────▶│ (resolveValue)   │  │
│  └────────────────┘      └──────────┬──────────┘      └──────────────────┘  │
│                                     │                           ▲           │
│                                     ▼                           │           │
│                          ┌─────────────────────┐      ┌─────────┴────────┐  │
│                          │    Action Pipeline  │      │   Global State   │  │
│                          │  (Async execution)  │◀────▶│  (config.state)  │  │
│                          └─────────────────────┘      └──────────────────┘  │
│                                     │                                       │
│                                     ▼                                       │
│                          ┌─────────────────────┐                            │
│                          │    Symbol Resolver  │                            │
│                          │ (Recursive mapping) │                            │
│                          └─────────────────────┘                            │
└─────────────────────────────────────┬───────────────────────────────────────┘
                                      │
                               Visual Reconciler
                                      │
                                      ▼
                             [ DOM Tree Rendering ]
```

### Key Files

| File | Purpose |
|------|---------|
| `src/sppmobile/SppmobileApp.php` | Entry point. Routes requests, registers CSS/JS assets |
| `src/sppmobile/services/Mobile.php` | Backend API. Config CRUD + blueprint discovery |
| `src/sppmobile/js/mobile-app.js` | App shell. Project management, modal dialogs |
| `src/sppmobile/js/views/mobile.js` | Main component. The entire visual studio UI |
| `src/sppmobile/js/blueprints.js` | Built-in blueprint/layout registry |
| `src/sppmobile/css/mobile.css` | All studio styles |
| `spp/modules/spp/sppux/js/sppux.js` | SPP-UX framework (BaseComponent, html tag, reconciler) |

---

## Part 3: SPP-UX Framework Reference

Every studio component extends `BaseComponent` from the SPP-UX framework. Here's how it works.

### 3.1 Component Lifecycle

```
constructor(app, container, props)
    │
    ▼
onInit()          ← Load data, set initial state
    │
    ▼
render()          ← Return HTML template
    │
    ▼
update()          ← Reconcile DOM (called by setState)
    │
    ▼
afterUpdate()     ← Post-render hook (bind listeners, apply filters)
    │
    ▼
onMount()         ← Component is fully mounted
    │
    ...
    ▼
onDestroy()       ← Cleanup on disposal
```

### 3.2 The `html` Template Tag

The `html` tag creates safe HTML from template literals. It auto-escapes values and handles event bindings.

```js
render() {
    const name = this.state.userName;
    const count = this.state.itemCount;

    return html`
        <div class="greeting">
            <h1>Hello, ${name}!</h1>
            <p>You have ${count} items.</p>
            <button @click=${() => this.loadMore()}>Load More</button>
        </div>
    `;
}
```

### 3.3 State Management

```js
// Setting state triggers a re-render
this.setState({ loading: false, items: data });

// Reading state
const { loading, items } = this.state;

// IMPORTANT: Direct mutation does NOT trigger re-render
this.state.loading = false;  // ← No re-render! Use setState instead.
```

### 3.4 The Reconciler

When `setState()` is called:

1. `render()` creates a new HTML string
2. The string is parsed into a temporary DOM tree
3. `_reconcile()` **diffs** the old DOM against the new DOM
4. Only **changed** attributes and text nodes are updated
5. The actual DOM elements are preserved (not replaced)

### 3.5 `afterUpdate()` Hook

Called after every DOM reconciliation. Use it for post-render work:

```js
class MyView extends BaseComponent {
    afterUpdate() {
        // Re-apply search filter after any state change
        if (this.state.searchQuery) {
            this._applyFilter(this.state.searchQuery);
        }
        
        // Initialize third-party widgets
        this._initCharts();
    }
}
```

### 3.6 `renderLoading(message)` Utility

Returns a professional spinner + message template. Use it for loading states.

### 3.7 `BaseComponent.domFilter()` Utility

Filters DOM elements by showing/hiding them based on a text query. **Does not trigger re-render** — safe to call from input handlers.

### 3.8 `guard(key, fn)` Utility

Prevents the same async operation from running concurrently:

```js
async fetchData() {
    // If fetchData is already running, this call is silently ignored
    return this.guard('fetch', async () => {
        this.setState({ loading: true });
        const res = await this.api('get_data');
        this.setState({ data: res.data, loading: false });
    });
}
```

### 3.9 API Calls

```js
// Simple API call
const res = await this.api('get_items');

// Shorthand (camelCase → snake_case conversion)
const res = await this.api.getItems();  // calls 'get_items'
```

---

## Part 4: Step-by-Step — Building a Component

### Example: Building a "User List" Component

**Goal:** A searchable list of users with loading state.

#### Step 1: Create the file

Create `src/yourapp/comp/user-list.js`:

```js
/**
 * UserList — A searchable list of users
 */
export default class UserListView extends BaseComponent {

    async onInit() {
        this.state = {
            loading: true,
            users: [],
            searchQuery: ''
        };
        await this.fetchData();
    }

    async fetchData() {
        return this.guard('fetch', async () => {
            this.setState({ loading: true });
            try {
                const res = await this.api('get_users');
                if (res.success) {
                    this.setState({ users: res.data.users, loading: false });
                } else {
                    this.setState({ loading: false });
                    this.notify('Failed to load users', 'error');
                }
            } catch (e) {
                this.setState({ loading: false });
                this.notify('Network error: ' + e.message, 'error');
            }
        });
    }

    onSearch(e) {
        this.state.searchQuery = e.target.value;
        BaseComponent.domFilter(this.container, e.target.value, {
            itemSelector: '.user-card[data-search-name]',
            attrs: ['data-search-name']
        });
    }

    afterUpdate() {
        if (this.state.searchQuery) {
            BaseComponent.domFilter(this.container, this.state.searchQuery, {
                itemSelector: '.user-card[data-search-name]',
                attrs: ['data-search-name']
            });
        }
    }

    render() {
        const { loading, users } = this.state;

        if (loading) return this.renderLoading('Loading users...');

        return html`
            <div class="user-list">
                <h2>Users (${users.length})</h2>

                <input type="text" placeholder="Search users..."
                    value="${this.state.searchQuery || ''}"
                    @input=${(e) => this.onSearch(e)}>

                <div class="user-grid">
                    ${users.map(u => html`
                        <div class="user-card" data-search-name="${u.name.toLowerCase()}">
                            <strong>${u.name}</strong>
                            <span>${u.email}</span>
                        </div>
                    `)}
                </div>

                ${users.length === 0 ? html`<p>No users found.</p>` : ''}
            </div>
        `;
    }
}
```

---

## Part 5: Creating Custom Blueprints

### What is a Blueprint?

A blueprint is a **pre-built screen layout** — a JSON file that describes a complete mobile screen with all its components, styling, and structure. 

### Where Blueprints Live

The backend **recursively scans** `src/sppmobile/blueprints/`. Drop a `.json` file anywhere inside it and the studio will automatically discover it on next load.

### Blueprint JSON Format

1. **Set a unique `id`** — lowercase with underscores (e.g., `my_dashboard_v2`)
2. **Add `name` and `description`** — shown in the studio's blueprint list
3. **Define `components`** — array of component objects with `type`, `props`, and optional `children`
4. **Save the file** — the studio discovers it automatically on next page load

---

## Part 6: Creating Custom Layouts

### What is a Layout?

A layout is a **structural skeleton** — it defines the arrangement of sections on a screen without content. 

### Layout Definition (in blueprints.js)

```js
window.MobileBlueprints = {
    layouts: {
        'my_custom_layout': {
            name: 'My Custom Layout',
            sections: [
                { id: 'header', label: 'Header Area', height: '60px' },
                { id: 'content', label: 'Main Content', flex: 1 },
                { id: 'footer', label: 'Footer', height: '50px' }
            ]
        }
    },
    blueprints: [ /* ... */ ]
};
```

### Adding Layouts via JSON Files

You can also define layouts in JSON files in the `blueprints/` directory:

```json
{
    "type": "layout",
    "id": "split_view_layout",
    "name": "Split View",
    "sections": [
        { "id": "left", "label": "Left Panel", "width": "40%" },
        { "id": "right", "label": "Right Panel", "flex": 1 }
    ]
}
```

---

## Part 7: Troubleshooting

### Search bar not working / text disappears

**Fixed in SPP-UX v11.1.** The framework now:
- Does not call `e.preventDefault()` on `input` events
- Does not overwrite the value of a focused input during DOM reconciliation

If you're on an older version, update `sppux.js`.

### Infinite `[BaseComponent] Updating MobileView...` in console

This means a render loop. Common causes:
- An `@input` handler calling `setState()` which triggers re-render which triggers input
- A `render()` method that changes state as a side effect

**Fix:** Use `domFilter()` for search instead of `setState()`. Never modify state inside `render()`.

### Blank screen during loading

The studio now shows a skeleton placeholder with a spinner. If you still see blank, check:
- Browser console for JavaScript errors
- Network tab for failed API calls to `get_mobile_config`

### "BaseComponent is not defined" Error

If you see this in a module, it means you're trying to use the global `BaseComponent` before it's loaded.
**Fix**: Add an explicit import at the top of your file:
```js
import { BaseComponent } from '../../../../spp/modules/spp/sppux/js/sppux.js';
```

### Static vs Instance Methods

Remember:
- `this.setState()` is an **instance** method (use inside your class).
- `BaseComponent.domFilter()` is a **static** utility (call on the class itself).
- `this.api` is a **proxy** (call it like a function or a method).

---

## Part 8: The Nerd Core — Internal Specifications

### 8.1 The Binding Engine (`resolveValue`)
The core of Satya Pro's reactivity is a recursive regex parser that intercepts property values before they reach the DOM reconciler.

**Syntax**: `{{state.variableName}}`  
**Execution Flow**:
1. `renderComponent(c)` is called.
2. `props` are deep-cloned to prevent reference leakage.
3. Every string property is passed to `resolveValue(str)`.
4. The regex `/{{\s*(state\.[a-zA-Z0-9._]+)\s*}}/g` identifies tokens.
5. Tokens are resolved against `this.state.config.state`.
6. Results are cast back to the expected type (String/Number/Bool).

### 8.2 Async Action Pipeline
Unlike basic visual tools, Satya Pro uses a **Task Queue** for user interactions.

```js
async executeAction(step) {
    const { type, target, condition } = step;
    
    // 1. Condition Evaluation
    if (condition && !this.evalCondition(this.resolveValue(condition))) return;

    // 2. Deterministic Execution
    switch (type) {
        case 'setState': 
            // Atomic update via Proxy
            break;
        case 'navigate':
            // Screen stack management
            break;
    }
}
```

### 8.3 Symbol Resolution Logic
Symbols are handled via **Shallow Wrapping**.
- A `symbol_instance` component stores a `symbolId`.
- The renderer looks up the symbol definition in `config.symbols`.
- It renders the Symbol's `root` component, but wraps it in an `instance_wrapper`.
- **Selection Isolation**: Clicking the instance selects the instance on the current screen, but the properties being viewed are derived from the master definition unless overrides are present.

### 8.4 CSS Utility tokens
Developers can use these pro-tier utility classes in custom component plugins:
- `.glass-panel`: Standard frosted-glass background.
- `.bound-input`: Visual indicator for reactive inputs.
- `.active-bind`: Pulse animation for live data links.
- `.skeleton-pulse`: Standard placeholder effect.

---

## Part 9: Code Export API
You can programmatically trigger a project export by calling:
```js
mobileView.exportProject();
```
This produces a strictly-typed JSON schema that matches the `config.yml` structure, ready for consumption by the **Satya Native Flutter Wrapper**.

---

## Part 10: The Advanced Nerd Zone

### 10.1 Recursive DOM Reconciliation Deep-Dive
The `_reconcile` engine is a custom implementation designed for high-performance visual editing. Unlike generic VDOM libraries:
-   **Node Persistence**: It maintains the same DOM nodes across renders, which is critical for maintaining browser focus on inputs and preserving CSS transitions.
-   **Attribute Syncing**: Only attributes that actually change are updated via `setAttribute`, preventing layout thrashing.
-   **Focus Preservation**: It explicitly checks `document.activeElement` before updating input values, ensuring that a user's typing isn't interrupted by a background state update.

### 10.2 The Action Pipeline & Task Guarding
The studio uses an asynchronous action pipeline with a "Guard" mechanism.
-   **Concurrency Control**: The `guard(key, fn)` pattern ensures that slow network operations (like `save_project`) cannot be triggered multiple times if a user double-clicks.
-   **Atomic State Updates**: State changes via `setState` are queued and merged, ensuring the UI remains deterministic even during complex multi-step actions.

### 10.3 Symbol Context Isolation
When a Symbol is rendered, the studio performs **Context Wrapping**:
1.  The master definition is retrieved from the project config.
2.  A virtual scope is created for the symbol instance.
3.  **Property Overrides**: If an instance has a specific color override, the `resolveValue` engine prioritizes the instance data over the master symbol data.
4.  **Re-rendering**: Updating the master symbol triggers a `requestUpdate()` call across all active components, which then selectively re-renders only those components containing instances of that symbol.

### 10.4 CSS Variable Injection (Runtime Theming)
The Studio avoids full CSS reloads. Instead:
-   It injects a `<style id="spp-dynamic-theme">` block into the document head.
-   Themes are defined as a set of CSS Variables (`--primary-color`, `--panel-bg`, etc.).
-   Switching themes simply updates the `:root` variables, resulting in an instant, zero-latency visual update across the entire workspace.

