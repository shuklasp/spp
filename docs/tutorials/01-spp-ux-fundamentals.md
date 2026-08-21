# SPP-UX Fundamentals: Zero-Build Reactive Web Components

Welcome to SPP-UX! If you've never used the SPP framework before, you are in the right place. This guide will take you from absolute zero to building blazing-fast, reactive user interfaces in the browser.

## 1. What is SPP-UX?
In the modern web, building a reactive interface usually requires installing Node.js, setting up Webpack or Vite, and compiling React or Vue code. 

**SPP-UX eliminates all of that.** It is a "Zero-Build" framework. You write pure modern JavaScript directly in your browser, and SPP-UX dynamically binds your data to the DOM. Behind the scenes, it uses **Signals** (for instantaneous O(1) performance) and automatically compiles your code into native standard **Web Components**.

## 2. Architecture & Lifecycle
When you write an SPP-UX component, you are essentially doing three things:
1. **Defining State:** Using `createStore()` to create a reactive Signal.
2. **Writing a Template:** Using the `html` tagged template literal to write HTML.
3. **Registering the Component:** Using `defineElement()` to turn it into a Custom HTML tag.

Because SPP-UX uses real Signals instead of a Virtual DOM (like React), when a variable changes, the framework doesn't re-render the whole page. It surgically updates the exact text node or attribute that changed.

## 3. Step-by-Step Tutorial: Building a Counter

Let's build a simple counter. Create a new JavaScript file (e.g., `counter.js`) and include it in your page as a module: `<script type="module" src="counter.js"></script>`.

### Step 1: Import the tools
```javascript
import { html, defineElement, createStore } from '/spp/modules/spp/drishyam/js/sppux.js';
import BaseComponent from '/spp/modules/spp/drishyam/js/BaseComponent.js';
```

### Step 2: Define your Component Class
```javascript
class MyCounter extends BaseComponent {
    constructor(app, element, props) {
        super(app, element, props);
        
        // 1. Create a reactive store (Signal)
        this.store = createStore({ count: 0 });
    }

    // 2. Define your actions
    increment() {
        this.store.count++;
    }

    // 3. Render the HTML
    render() {
        return html`
            <div class="counter-box">
                <!-- Use standard ${} to bind state -->
                <h2>Current Count: ${this.store.count}</h2>
                
                <!-- Bind events using @click -->
                <button @click="${() => this.increment()}">Add +1</button>
            </div>
        `;
    }
}
```

### Step 3: Register the Web Component
```javascript
// This tells the browser that <my-counter> is a valid HTML tag!
defineElement('my-counter', MyCounter);
```

### Step 4: Use it in your HTML
Now, anywhere in your standard HTML or PHP files, you can simply write:
```html
<my-counter></my-counter>
```
The browser will automatically render your reactive counter. It's completely encapsulated!

## 4. Advanced Directives
SPP-UX comes with powerful built-in tools for complex scenarios:

* **Two-Way Binding (`bind`)**: `<input ${bind(this.store, 'username')}>` automatically syncs the input box with `this.store.username`.
* **Async Resolution (`until`)**: `<div>${until(fetchDataPromise, html`<span>Loading...</span>`)}</div>` displays a loading spinner until the promise resolves, then morphs into the data.
* **Portals (`portal`)**: `${portal(html`<div>Modal Content</div>`, document.body)}` safely teleports a piece of your component out of the current layout and appends it to the `<body>` (perfect for z-index modal issues).

Welcome to the future of zero-build frontend development! Move on to **Tutorial 02** to see how SPPLive connects this frontend directly to your PHP backend.
