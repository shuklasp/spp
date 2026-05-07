# SPP LiveService: Architectural Comparison & Roadmap

This document compares the **SPP LiveService** architecture with industry-standard reactive frameworks and suggests strategic improvements to reach parity with enterprise-grade ecosystems.

---

## 📊 1. Comparison Matrix

| Feature | **SPP LiveService** | **Laravel Livewire** | **Phoenix LiveView** | **htmx** |
| :--- | :--- | :--- | :--- | :--- |
| **Communication** | AJAX (XHR) | AJAX (XHR) | WebSockets | AJAX (XHR) |
| **Philosophy** | **Instruction-Based** | Component-State | Process-State | Declarative Hypermedia |
| **Data Engine** | **GraphQL / XDB** | Eloquent (SQL) | Ecto (SQL) | Any |
| **Update Strategy** | DOM Replacement / Multi-Target | DOM Morphing | Binary Diffing | DOM Swap (Single Target) |
| **State Location** | Server (via Store Sync) | Encrypted Client Payload | Server (OTP Process) | Stateless |
| **Complexity** | Low (SAJAX-like) | High | High | Very Low |

### Key Differentiators
*   **Multi-Instruction Payloads**: Unlike htmx, SPP LiveService can update **multiple unrelated DOM elements**, show a notification, and sync a global store in a **single atomic request**.
*   **Native GraphQL Integration**: SPP is built with GraphQL as a first-class citizen, allowing UI updates to be driven directly by graph data.

---

## 💡 2. Comparison with specific projects

### vs. Laravel Livewire
Livewire focuses on "Components" that hold state. SPP LiveService is more lightweight—it doesn't require serializing the entire component state back and forth. However, Livewire's **Morphing** (preserving input focus) is superior for complex forms.

### vs. htmx
htmx is excellent for "swapping" a single div. SPP LiveService is better for "Orchestration"—where one click might update a sidebar, a badge, and clear a form simultaneously.

---

## 🚀 3. Suggested Improvements

### 1. Smart DOM Morphing (Priority: High)
**The Problem**: `innerHTML` destroys the element and its children, losing focus, scroll position, and active transitions.
**The Fix**: Integrate a morphing algorithm (like `idiomorph` or `morphdom`) to transform the existing DOM into the new state without total destruction.

### 2. Polling & Heartbeats (Priority: Medium)
**The Problem**: UI only updates on user interaction.
**The Fix**: Add `data-live-poll="ms"` to allow elements to automatically refresh themselves from a service periodically (e.g., a dashboard widget).

### 3. Stateful Components
**The Problem**: Every request must pass all necessary data (IDs, filters) via `data-*` attributes.
**The Fix**: Implement a "Component Session" where the server remembers the "view state" of a specific component instance.

### 4. XDB "Live" Subscriptions
**The Problem**: Changes made by one user aren't visible to others until they refresh.
**The Fix**: Leverage `SPPXDB` triggers to push instructions to clients when a document changes (Real-time reactivity).

---

## 🛠️ 4. Immediate Roadmap Items

1.  **Implement Polling**: Support for auto-refreshing elements.
2.  **Implement Partial Morphing**: A lightweight version of DOM morphing for better UX.
3.  **Enhanced Error Boundaries**: Automatic "Offline" detection and retry logic.
