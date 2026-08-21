# Novice Guide: SPP-UX Frontend Reactivity & Component Architecture

Welcome to the SPP-UX Frontend Guide! If you are completely new to SPP and wondering how to build highly reactive, state-driven user interfaces without relying on heavy frontend frameworks like React or Vue, you are in the right place.

This guide will explain exactly what SPP-UX is, how its Virtual DOM engine works, and the simple, straightforward workflow you should follow to create rich, interactive applications.

## What is SPP-UX?

SPP-UX is the built-in frontend engine for the SPP framework. It is a lightweight, zero-dependency JavaScript framework that provides:
- **State Management**: Reactive signals and variables that automatically update the UI when they change.
- **Global Event Delegation**: Instead of manually writing `document.getElementById('btn').addEventListener(...)`, you can simply write `@click=${...}` right inside your HTML.
- **Virtual DOM Reconciliation**: When your data changes, SPP-UX calculates exactly which parts of the webpage need to update and *only* updates those tiny pieces, making it lightning-fast.

## The Core Concept: `BaseComponent`

Every interactive section of an SPP page is a "Component". 
A component manages its own `state`, handles user interactions, and renders HTML dynamically.

Here is a standard, fully functional SPP-UX Component:

```javascript
import { BaseComponent, html } from '/spp/admin/js/sppux.js';

export class CounterComponent extends BaseComponent {
    async onInit() {
        // 1. Initialize your starting state
        this.setState({ count: 0 });
    }

    // 2. Define actions that update state
    increment() {
        this.setState(prev => ({ count: prev.count + 1 }));
    }

    // 3. Render the UI
    render() {
        return html`
            <div class="counter-box">
                <h2>Current Count: ${this.state.count}</h2>
                <button @click="${() => this.increment()}">
                    Add 1
                </button>
            </div>
        `;
    }
}
```

### The Three Pillars of a Component Workflow

1. **State (`this.state`)**: Your component's memory. Always use `this.setState()` to change values. Do *not* mutate `this.state` directly, or the UI won't know it needs to update.
2. **Re-rendering (`render()`)**: The `render()` function should always return an `html` tagged template literal. It automatically interpolates your state into the layout.
3. **Events (`@eventName`)**: You can bind any standard browser event directly in the HTML string by prefixing it with `@`.

## A Deep Dive into Events

SPP-UX uses a highly optimized **Global Event Dispatcher**. This means you do not have to worry about cleaning up event listeners or memory leaks when components appear and disappear.

### Supported Events
Because the SPP-UX core was recently modernized, you can use **ANY** standard UI event natively!
- **Mouse**: `@click`, `@mousedown`, `@mouseup`, `@mousemove`, `@mouseenter`
- **Keyboard**: `@keydown`, `@keyup`, `@keypress`
- **Touch & Mobile**: `@touchstart`, `@touchend`, `@touchmove`
- **Forms**: `@submit`, `@input`, `@change`, `@blur`, `@focus`
- **Scrolling**: `@scroll`, `@wheel`

### Example: Handling Form Inputs
Notice how we seamlessly bind `@input` to update the state on every keystroke:

```javascript
render() {
    return html`
        <div class="user-card">
            <input 
                type="text" 
                value="${this.state.username || ''}" 
                @input="${(e) => this.setState({ username: e.target.value })}"
                placeholder="Enter your name"
            />
            <p>Hello, ${this.state.username}!</p>
        </div>
    `;
}
```

## Architectural Guidelines & Best Practices

To ensure your implementation remains simple and straightforward, adhere strictly to these guidelines:

### 1. Never Bypass the Event Dispatcher
**Rule:** Do NOT use native DOM attributes like `onclick="..."` or `onmousedown="..."`.
**Why?** Using native inline attributes bypasses the SPP-UX Virtual DOM lifecycle. The framework expects all interactions to flow through its global dispatcher using the `@event` syntax (e.g., `@click`).

### 2. Embrace Component Lifecycle Hooks
Components have built-in phases you can hook into:
- `onInit()`: Runs once when the component is created. Best place to fetch initial data (`this.api(...)`) or set default state.
- `onMount()`: Runs immediately after the first time the component renders to the screen. Perfect for attaching third-party libraries (like charts or maps).
- `afterUpdate()`: Runs every time the state changes and the UI finishes updating.
- `dispose()`: Automatically cleans up the component.

### 3. Let the Virtual DOM do the Heavy Lifting
**Rule:** NEVER manually change the DOM using `document.getElementById('...').innerText = 'new text'`.
**Why?** SPP-UX relies on its Virtual DOM engine to reconcile differences. If you manually mutate the DOM, the engine will overwrite your changes on the next state update. Always change `this.state`, and let the `render()` function naturally flow the new data into the HTML.

### 4. Automatic Handler Reclamation
When a component re-renders, SPP-UX automatically cleans up old event handlers and claims the new ones seamlessly. You never have to worry about dynamically generated `@mousedown` or `@scroll` events disappearing after a state update. The system protects your bindings autonomously!

## Summary

The entire philosophy of SPP-UX is **State-Driven UI**. 
1. Define your data (`this.state`).
2. Write a layout that visually represents that data (`render()`).
3. Write actions that change the data (`this.setState(...)`).
The framework securely handles everything else—from lightning-fast DOM patching to global event tracking—so your code remains clean, readable, and highly maintainable.
