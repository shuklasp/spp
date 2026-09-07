# Novice Guide: Command Palette & Browser Accelerator Interception in SPPDocs

Welcome to the beginner-friendly guide to **Keyboard Accelerator Interception & Command Palette Architecture** in the SPP framework.

Even if you have never written a browser event listener or built a command palette before, this guide will walk you through the fundamental challenges of browser-level hotkeys (like `Ctrl+K` opening Google Chrome's Omnibox / address bar) and show you how SPPDocs reliably captures hotkeys, manages dynamic DOM lifecycles under HTMX, and provides seamless multi-modal navigation.

---

## 1. Foundational Concepts

### What is a Command Palette?
A **Command Palette** (popularized by VS Code, Linear, GitHub, and Sublime Text) is a spotlight-style modal that allows users to instantly search, jump between pages, execute actions, and query AI assistants without touching their mouse. In modern developer tools, the universal standard shortcut to invoke this palette is:
- **Windows / Linux**: `Ctrl + K`
- **macOS**: `Cmd + K` (⌘K)

### The Chrome Omnibox Problem
Web browsers—especially Chromium-based browsers like **Google Chrome**, **Microsoft Edge**, and **Brave**—assign hardcoded default behaviors to specific key combinations.
On Windows:
- `Ctrl + L` or `Alt + D` focuses the browser address bar (the **Omnibox**).
- `Ctrl + K` or `Ctrl + E` focuses the Omnibox in **search mode** (prepending a `?` character to search via Google or the default search engine).

When a web application attempts to listen for `Ctrl + K`:
```javascript
// Naive implementation:
window.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'k') {
        openCommandPalette();
    }
});
```
In Google Chrome on Windows, **this naive code fails completely**:
1. Chrome's browser process intercepts `Ctrl+K` before or during normal event dispatch.
2. The focus instantly leaves the web page and jumps into the Chrome Omnibox.
3. The command palette never opens, leaving the user frustrated.

---

## 2. Root Cause Analysis

Why did Chrome open the address bar instead of our palette? There are three distinct root causes:

### 1. The DOM Event Dispatch Lifecycle (Capture vs. Bubble)
In standard W3C DOM event dispatch, an event goes through three phases:
```
           1. Window (Capture Phase)
              │
              ▼
           2. Document (Capture Phase)
              │
              ▼
           3. <html> (Capture Phase)
              │
              ▼
           4. <body> (Capture Phase)
              │
              ▼
           5. Target Element (Target Phase)
              │
              ▲
           6. <body> (Bubble Phase)
              │
              ▲
           7. Document (Bubble Phase)
              │
              ▲
           8. Window (Bubble Phase)
```
- By default, `addEventListener('keydown', handler)` runs in the **Bubble Phase** (`useCapture = false`).
- Chromium checks whether any web page script has claimed the accelerator before finalizing its internal browser action. If listeners are only registered in the bubbling phase, or if an element stopped bubbling, Chrome evaluates the accelerator and opens the Omnibox.
- **Solution**: Register the listener in the **Capture Phase** at the absolute root (`window.addEventListener('keydown', handler, true)`). In the capture phase, our handler runs at Step 1—before any other script or default browser accelerator logic!

### 2. International Layouts and Key Representation
Naive code checks:
```javascript
if (e.key === 'k' || e.key === 'K')
```
On Windows with non-US keyboard layouts (e.g. English India, Hindi, French AZERTY, German QWERTZ), CapsLock active, or with specific Input Method Editors (IME):
- `e.key` can return non-standard values or uppercase letters with shift states.
- However, the physical hardware key is always identified by `e.code === 'KeyK'`!
- Legacy numeric keycodes are always `e.keyCode === 75` or `e.which === 75`.
- **Solution**: A bulletproof helper checking all three:
```javascript
function isKeyK(e) {
    if (!e) return false;
    return (e.key && e.key.toLowerCase() === 'k') ||
           e.code === 'KeyK' ||
           e.keyCode === 75 ||
           e.which === 75;
}
```

### 3. HTMX Body Swaps & Stale Closures
SPPDocs uses HTMX with `<body hx-boost="true">` for high-performance SPA-like page transitions without full page reloads.
- When HTMX navigates between `/admin/project/demo-app` and `/admin/roles`, HTMX swaps the contents of `<body>`.
- If a script executes at initial page load and saves a DOM node reference in a closure:
```javascript
const palette = document.getElementById('sppdocs-cmd-palette');
```
When HTMX swaps the page, that `palette` reference becomes **detached** from the active DOM tree.
- When the user presses `Ctrl+K`, the script sets `style.display = 'flex'` on the detached, invisible node. The newly swapped palette in the visible document remains `style.display = 'none'`!
- **Solution**: Always resolve DOM elements dynamically via live query functions (`getPalette()`, `getInput()`).

---

## 3. The Complete SPPDocs Capture-Phase Architecture

Here is the exact battle-tested architecture implemented in `src/SPPDocs/resources/views/partials/command_palette.blade.php`:

```javascript
(function() {
    'use strict';

    // 1. Dynamic Live DOM Accessors (100% HTMX Lifecycle Resilient)
    function getPalette() { return document.getElementById('sppdocs-cmd-palette'); }
    function getInput() { return document.getElementById('sppdocs-cmd-input'); }
    function getList() { return document.getElementById('sppdocs-cmd-list'); }

    // 2. Hardware-Level Key Detection
    function isKeyK(e) {
        if (!e) return false;
        return (e.key && e.key.toLowerCase() === 'k') ||
               e.code === 'KeyK' ||
               e.keyCode === 75 ||
               e.which === 75;
    }

    // 3. Omnibox Buster: Intercept at Capture Level
    function handleKeyDown(e) {
        const isCtrlOrCmd = (e.ctrlKey || e.metaKey) && !e.altKey;

        if (isCtrlOrCmd && isKeyK(e)) {
            // STOP CHROME IMMEDIATELY
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') {
                e.stopImmediatePropagation();
            }

            const p = getPalette();
            if (p && p.style.display === 'flex') {
                window.closeCommandPalette();
            } else {
                window.openCommandPalette('nav');
            }
            return false;
        }

        // Escape closes palette
        const p = getPalette();
        if (e.key === 'Escape' && p && p.style.display === 'flex') {
            e.preventDefault();
            window.closeCommandPalette();
            return false;
        }
    }

    // 4. Suppress KeyUp to guarantee Windows Chrome does not trigger Omnibox on release
    function handleKeyUp(e) {
        const isCtrlOrCmd = (e.ctrlKey || e.metaKey) && !e.altKey;
        if (isCtrlOrCmd && isKeyK(e)) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') {
                e.stopImmediatePropagation();
            }
            return false;
        }
    }

    // 5. Idempotent Registration with CAPTURE = TRUE
    if (!window._sppdocsCmdPaletteHotkeysBound) {
        window._sppdocsCmdPaletteHotkeysBound = true;
        window.addEventListener('keydown', handleKeyDown, true);
        window.addEventListener('keyup', handleKeyUp, true);
        window.addEventListener('keypress', function(e) {
            const isCtrlOrCmd = (e.ctrlKey || e.metaKey) && !e.altKey;
            if (isCtrlOrCmd && isKeyK(e)) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        }, true);
    }
})();
```

---

## 4. Visual Trigger Buttons (Dual-Affordance Design)

Keyboard shortcuts must never be the *only* way to invoke core functionality. Some users operate with a mouse, trackpad, or touch device, while others may not know the shortcut exists.

SPPDocs introduces a visual **Search Trigger Button** in the navigation header across all views:

### Admin Console Header (`layouts/admin.blade.php`)
```html
<button type="button" class="adm-header-search-btn" onclick="openCommandPalette('nav')" title="Search or jump to... (Ctrl+K)">
    <span class="adm-search-icon">🔍</span>
    <span class="adm-search-label">Search or jump to...</span>
    <kbd class="adm-search-kbd">Ctrl+K</kbd>
</button>
```

### Documentation Portal Header (`partials/header.blade.php`)
```html
<button type="button" class="sppdocs-header-search-btn" onclick="openCommandPalette('nav')" title="Search or jump to... (Ctrl+K)">
    <span class="sppdocs-search-icon">🔍</span>
    <span class="sppdocs-search-label">Search or jump to...</span>
    <kbd class="sppdocs-search-kbd">Ctrl+K</kbd>
</button>
```

### Zero Inline CSS Styling
Adhering strictly to framework rules, all styles are located in `admin.css` and `sppdocs.css`:
- Tactile hover feedback (`:hover` state with soft brand tint).
- Clean monospaced `<kbd>` keycap badge.
- Responsive breakpoints (`@media (max-width: 768px)` collapses labels to a sleek search icon).

---

## 5. Architectural Checklist for Novices

When implementing global keyboard accelerators in your own SPP components, follow this checklist:

| Rule | Implementation Requirement | Why It Matters |
|------|---------------------------|----------------|
| **Use Capture Phase** | `window.addEventListener('event', fn, true)` | Intercepts the event before browser-level accelerators (Omnibox) can seize focus. |
| **All-in-One Key Code Check** | Check `e.key`, `e.code`, and `e.keyCode` | Guarantees reliability across international keyboard layouts, CapsLock, and platform variations. |
| **Stop All Propagation** | Call `preventDefault()`, `stopPropagation()`, and `stopImmediatePropagation()` | Completely cancels default OS and browser event dispatch. |
| **Dynamic DOM Queries** | Use functions (`getPalette()`) instead of closure variables | Prevents stale detached node references during HTMX swaps (`hx-boost="true"`). |
| **Idempotent Binding Guard** | Use `window._sppdocsBoundFlag` check | Prevents attaching duplicate listeners when partials re-render or boost. |
| **Dual Affordance** | Provide clickable header buttons alongside hotkeys | Enables mouse/touch users and displays the hotkey to novices. |
| **Zero Inline HTML & CSS** | Use `<template>` tags and dedicated CSS files | Maintains clean separation of concerns and framework compliance. |
| **Cross-Layout `hx-boost="false"`** | Set `hx-boost="false"` and `window.location.href` on cross-boundary links | Prevents HTMX body swapping from dropping layout stylesheets (`admin.css` vs `sppdocs.css`). |

---

## 6. Cross-Layout Boundaries & HTMX `hx-boost` Traps

### The "Unstyled Page" Symptom
When navigating from a documentation or issue tracker view (`/issues` using `layouts/base.blade.php`) to the project admin settings (`/admin/project/demo-app` using `layouts/admin.blade.php`) via the Command Palette, users previously encountered a completely unstyled HTML page:
- Bare blue hyperlinks and default serif text
- Form inputs and textareas stacked vertically without cards or grids
- Tab navigation divs displayed as a raw bullet list
- Zero styling applied despite the server returning HTTP 200 OK

### Why This Occurred (The HTMX Layout Incompatibility Trap)
In SPPDocs, the user interface is split into two distinct layout archetypes:
1. **Public Portal Layout (`layouts/base.blade.php`)**: Provides the sidebar, content reader, issue tracker, and Kanban boards. Its `<head>` loads `sppdocs.css`.
2. **Admin Console Layout (`layouts/admin.blade.php`)**: Provides the multi-tab control center, user governance, project settings, media manager, and danger zone. Its `<head>` loads `admin.css`.

Both layout templates declare `<body hx-boost="true">` to achieve fast, SPA-like navigation without full browser reloads.

However, **HTMX `hx-boost` only swaps the `<body>` element**; it does not replace or inject `<link rel="stylesheet">` tags in the `<head>` of the active document.
When a user clicked "Project Administration" in the command palette:
1. HTMX intercepted the click on the `<a>` tag because the command palette was inside `<body hx-boost="true">`.
2. HTMX issued an AJAX request for `GET /school1/sppdocs/admin/project/demo-app`.
3. The server responded with the full HTML for `layouts/admin.blade.php`.
4. HTMX extracted only the `<body>` from the response and swapped `document.body.innerHTML`.
5. The browser retained the `<head>` of `layouts/base.blade.php`, which had `sppdocs.css` but **lacked `admin.css`**!
6. All the `.adm-*` styles were absent, causing the administration console to render completely unstyled.

### The Architectural Solution
To ensure flawless cross-layout navigation without stylesheet shedding:
1. **Container-Level Opt-Out**: Set `hx-boost="false"` on the root modal `#sppdocs-cmd-palette`:
   ```html
   <div id="sppdocs-cmd-palette" class="sppdocs-cmd-backdrop" hx-boost="false" ...>
   ```
2. **Item-Level Opt-Out**: Explicitly add `hx-boost="false"` to all command items:
   ```html
   <a href="{{ \SPP\App::url('admin/project/' . $id) }}" class="sppdocs-cmd-item" hx-boost="false">
   ```
3. **Hard Native Navigation via JavaScript**: When Enter is pressed or an item is clicked in the palette, force full browser navigation:
   ```javascript
   if (href && !href.startsWith('#') && item.target !== '_blank') {
       window.closeCommandPalette();
       window.location.href = href;
       e.preventDefault();
   }
   ```
4. **Global Single-Key Accelerator Jumps**: Updated `sppdocs-shortcuts.js` so pressing `A` (outside inputs) navigates directly to Project Administration via `window.location.href`, guaranteeing a complete `<head>` stylesheet initialization every time.

