# SPP-UX Framework (v13)

The **SPP-UX** framework is a zero-dependency, high-performance reactive UI toolkit for building premium administrative interfaces within the SPP ecosystem.

**v13** is a complete architectural rewrite that brings SPP-UX to parity with React and Vue while maintaining **100% backward compatibility** with all existing v11/v12 components.

## What's New in v13

| Feature | v11/v12 | v13 |
|---------|---------|-----|
| **Reconciliation** | Index-based O(N²) | Keyed LIS-based O(N log N) |
| **Reactivity** | Single global subscriber | Effect stack with dependency graph |
| **Event Delegation** | O(N) component iteration | O(1) WeakMap lookup |
| **Architecture** | 2300-line monolith | 10 focused ES modules |
| **State Updates** | Synchronous (DOM thrash) | Batched async (microtask) |
| **Error Handling** | None (crash = white screen) | Error boundaries with recovery |
| **Memory** | Global handler leaks | WeakMap auto-GC |
| **Build** | Zero-build only | Zero-build + optional esbuild |

## 🚀 Getting Started

### Zero-Build (Default)
```html
<link rel="stylesheet" href="css/sppux.css">
<script type="module" src="js/sppux.js"></script>   <!-- Core Engine -->
<script src="js/sppux-ui.js"></script>               <!-- UI Library -->
```

### Bundled (Optional)
```bash
node spp/modules/spp/drishyam/build.js
```
```html
<script type="module" src="js/sppux.bundle.min.js"></script>
```

## 🏗️ Architecture

```
js/
├── sppux.js              ← Facade (imports + re-exports all core modules)
├── core/
│   ├── reactive.js       ← Signal, Computed, effect(), batch(), createStore
│   ├── scheduler.js      ← Batched async update queue
│   ├── template.js       ← html`` tagged template, TrustedHTML
│   ├── events.js         ← O(1) WeakMap event delegation
│   ├── reconciler.js     ← Keyed LIS DOM diffing
│   └── error-boundary.js ← Error boundary mixin
├── directives.js         ← 18 HTML-First directives (lazy-loaded)
├── dnd.js                ← Draggable + Sortable
├── devtools.js           ← Dev-only inspector & profiler
├── sppux-ui.js           ← Visual component library
└── spp-loader.js         ← Component bootstrapper
```

## 🧩 Reactive Components

Create a component by extending `BaseComponent`:

```javascript
class Counter extends BaseComponent {
    onInit() { this.setState({ count: 0 }); }

    render() {
        return html`
            <div class="counter">
                <h2>Count: ${this.state.count}</h2>
                <button @click=${() => this.setState({ count: this.state.count + 1 })}>
                    Increment
                </button>
            </div>
        `;
    }
}
```

### Lifecycle Hooks

| Hook | When | Notes |
|------|------|-------|
| `onInit()` | Before first render | Set up initial state |
| `onMount()` | After first DOM insertion | Fetch data, start timers |
| `shouldUpdate(nextState)` | Before re-render | Return `false` to skip |
| `onBeforeUpdate()` | Before DOM reconciliation | Capture scroll position etc. |
| `afterUpdate()` | After DOM reconciliation | DOM queries safe here |
| `onError(error, info)` | When render/child throws | Error boundary hook |
| `onDestroy()` | On dispose | Clean up timers/subs |

### Batched State Updates (New in v13)

Multiple `setState()` calls are batched into a single render:

```javascript
// v11: 3 renders ❌
this.setState({ a: 1 });
this.setState({ b: 2 });
this.setState({ c: 3 });

// v13: 1 render ✅ (automatic batching)
this.setState({ a: 1 });
this.setState({ b: 2 });
this.setState({ c: 3 });

// Explicit batching for signals:
SPPUX.batch(() => {
    countSignal.value = 10;
    nameSignal.value = 'test';
}); // → one render
```

### Function Updater (New in v13)
```javascript
this.setState(prev => ({ count: prev.count + 1 }));
```

### Force Synchronous Update
```javascript
this.forceUpdate(); // Bypass scheduler, render immediately
```

## ⚡ Fine-Grained Reactivity

### Signals
```javascript
const count = SPPUX.signal(0);
count.value = 5;          // Triggers subscribers
console.log(count.value); // 5
console.log(count.peek()); // 5 (no tracking)
```

### Computed
```javascript
const doubled = SPPUX.computed(() => count.value * 2);
console.log(doubled.value); // 10 (lazy, cached)
```

### Effects
```javascript
const dispose = SPPUX.effect(() => {
    console.log(`Count is ${count.value}`);
});
// Automatically re-runs when count changes
dispose(); // Stop tracking
```

## 🔑 Keyed Reconciliation

Add `data-key` to list items for optimal DOM updates:

```javascript
render() {
    return html`<ul>
        ${this.state.items.map(item => html`
            <li data-key="${item.id}">${item.name}</li>
        `)}
    </ul>`;
}
```

Without keys → index-based fallback (v11 behavior, no regression).
With keys → LIS-based minimum moves (React/Vue parity).

## 🛡️ Error Boundaries

Wrap sections of your app to catch render errors:

```javascript
class AppBoundary extends SPPUX.ErrorBoundary.applyTo(BaseComponent) {
    renderFallback() {
        return html`<div>Something went wrong. <button @click=${() => this.recover()}>Retry</button></div>`;
    }
}
```

## 🎯 Event Modifiers (New in v13)

```html
<button @click.prevent=${handler}>No Default</button>
<button @click.stop=${handler}>Stop Propagation</button>
<input @keydown.enter=${handler}>
<input @keydown.escape=${handler}>
<div @click.self=${handler}>Only direct clicks</div>
```

## 🎨 Visual Library (`sppux-ui.js`)

### Key Helpers
- **Modal**: `SPPUX.Modal.open("Title", "Content", [{ label: "OK", fn: (m) => m.close() }])`
- **Toast**: `SPPUX.Notify.show("Record Saved!", "success")`
- **Spotlight**: `SPPUX.Spotlight.open([{ title: "Home", icon: "🏠" }], (item) => {})`
- **Drawer**: `SPPUX.Drawer.open("Title", "Side Panel Content", "right")`

## 🌈 Themes

```javascript
SPPUX.Theme.set('midnight');  // Indigo/Slate
SPPUX.Theme.set('saffron');   // Saffron/Deep Brown
SPPUX.Theme.set('emerald');   // Green/Emerald
SPPUX.Theme.set('cyberpunk'); // Neon Pink/Purple
SPPUX.Theme.set('day');       // Light mode
```

## 🧪 Testing

Open `js/__tests__/test-runner.html` in a browser to run the full test suite:
- Reconciler (LIS, keyed/unkeyed diffing, focus preservation)
- Reactivity (Signal, Computed, effect, batch)
- Events (WeakMap registration, cleanup, GC)
- Backward compatibility (all v11 APIs)

## 🔧 DevTools

```javascript
SPPUX.debug = true; // Enable debug logging

// In browser console:
__SPPUX_DEVTOOLS__.printTree();        // Component tree
__SPPUX_DEVTOOLS__.inspect(element);   // Inspect a component
__SPPUX_DEVTOOLS__.performanceReport(); // Render timing report
```

## 💎 Elite Features
- **File Dropzone**: `SPPUX.Dropzone.render((files) => upload(files))`
- **Drag & Drop**: `new SPPUX.Draggable(element, options)`
- **Sortable Lists**: `new SPPUX.Sortable(container, options)`
- **Smart Tooltips**: Add `data-spp-tooltip="Your hint"` to any element

---
*Built with ❤️ for the Point of Productivity.*
