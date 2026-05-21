# SPPUX Next & SPPEX: The Native UI Framework

SPPUX Next is the SPP Framework's proprietary, zero-build frontend UI ecosystem. It provides the power of modern enterprise JS frameworks (like React and Vue) directly in the browser using ES6 Modules and native tagged template literals.

By using SPPUX, developers can completely bypass Webpack, Vite, Babel, and `node_modules` while still leveraging advanced Single Page Application (SPA) features.

---

## 1. Core Architecture (SPPUX Next)

Located at `spp/modules/spp/sppux/js/sppux.js`, the core runtime provides:

### A. Proxy-Based Global State (`SPPUX.createStore`)
A highly optimized, fine-grained reactivity store using ES6 Proxies (similar to Vue's Pinia). Any component that references a proxy store value automatically subscribes to updates.
```javascript
const store = SPPUX.createStore({ theme: 'dark' });
store.theme = 'light'; // Triggers instant DOM patch across all subscribed components
```

### B. Client-Side Routing (`SPPUX.Router`)
A native HTML5 History API router to enable SPA transitions without full page reloads.
```javascript
SPPUX.Router.init([
    { path: '/user/:id', component: UserView }
]);
// Navigates without reload:
SPPUX.Router.push('/user/42');
```

### C. Native Suspense (`SPPUX.await`)
Declarative asynchronous rendering directly within template literals, eliminating the need for manual `isLoading` state management.
```javascript
html`
    ${SPPUX.await(
        fetchData(), 
        (data) => html`<div>${data}</div>`, 
        html`<spinner></spinner>`
    )}
`
```

### D. Web Components Compiler (`SPPUX.defineElement`)
Wraps any proprietary `BaseComponent` class into a standard HTML5 `<custom-element>`, meaning SPPUX components can be embedded natively inside standard HTML files or other frameworks.

---

## 2. SPPEX (The Extended Ecosystem)

Located at `spp/modules/spp/sppux/js/sppex.js`, this plugin module natively implements the core behavior of the most popular React ecosystem packages natively into SPP.

1. **`SPPEX.Query`**: A port of React Query. Handles data fetching, caching, and stale-while-revalidate background polling.
2. **`SPPEX.Form`**: A port of React Hook Form. Manages complex validation schemas, tracks touched/dirty states, and isolates render cycles to prevent heavy form keystroke lags.
3. **`SPPEX.Motion`**: A port of Framer Motion. Attach `data-sppex-motion="slide-in"` to any DOM node to trigger hardware-accelerated, cubic-bezier entrance animations via an automatic `MutationObserver`.
4. **`SPPEX.Helmet`**: A port of React Helmet. Dynamically updates `<title>` and `<meta>` tags during client-side route changes for SEO compliance.
5. **`SPPEX.DnD`**: A port of dnd-kit. Provides an HTML5 drag-and-drop controller for complex sortable lists, handling visual states and array reordering automatically.

---

## 3. SPPEX Pro (Structural Primitives)

Located at `spp/modules/spp/sppux/js/sppex-pro.js`, this expansion provides 10 heavyweight structural modules that are too critical to leave out of a production SPA:

1. **`SPPEX.VirtualList`**: Port of `react-window`. Renders only the visible items in lists of 100,000+ rows, preventing DOM crashes.
2. **`SPPEX.InfiniteScroll`**: Port of `react-infinite-scroll-component`. Auto-loads data via `IntersectionObserver` when the user scrolls near the bottom.
3. **`SPPEX.StoreSync`**: Port of `useLocalStorage`. Automatically persists Proxy store state to `localStorage` across reloads.
4. **`SPPEX.Machine`**: Port of `xstate`. A native Finite State Machine to model complex component states (`idle → loading → success`).
5. **`SPPEX.Carousel`**: Port of `react-slick`. A CSS `scroll-snap` based slider with Next/Prev/Auto-play controls.
6. **`SPPEX.Floating`**: Port of `@floating-ui/react`. Edge-aware tooltip positioning that auto-flips when clipping the viewport.
7. **`SPPEX.Select`**: Port of `react-select`. Typeahead multi-select dropdown with visual pill tags and search filtering.
8. **`SPPEX.DatePicker`**: Port of `react-datepicker`. Progressive enhancement over native `<input type="date">`.
9. **`SPPEX.Markdown`**: Port of `react-markdown`. A regex-based micro-parser converting Markdown to sanitized HTML.
10. **`SPPEX.i18n`**: Port of `react-i18next`. Reactive frontend translation engine with `changeLanguage()` broadcasting.

---

## 4. SPPEX Ultra (Advanced UI & Utilities)

Located at `spp/modules/spp/sppux/js/sppex-ultra.js`, this expansion adds 20 more enterprise-grade modules:

### Data & Layouts
1. **`SPPEX.DataGrid`**: Sortable, searchable data tables (port of `ag-grid`).
2. **`SPPEX.Masonry`**: Pinterest-style staggered column layouts (port of `react-masonry-css`).
3. **`SPPEX.Resizable`**: Draggable resizable panels (port of `react-resizable`).
4. **`SPPEX.Tree`**: Hierarchical folder-tree with collapse/expand (port of `react-treebeard`).

### Specialized UI
5. **`SPPEX.Dropzone`**: Drag-and-drop file upload area (port of `react-dropzone`).
6. **`SPPEX.ContextMenu`**: Custom right-click context menus (port of `react-contexify`).
7. **`SPPEX.ColorPicker`**: Stylable color input with swatches (port of `react-color`).
8. **`SPPEX.RangeSlider`**: Range input with dynamic value labels (port of `rc-slider`).
9. **`SPPEX.Rating`**: Interactive star rating with hover states (port of `react-rating`).

### Content & Presentation
10. **`SPPEX.Skeleton`**: Shimmering CSS loading placeholders (port of `react-loading-skeleton`).
11. **`SPPEX.Accordion`**: Exclusive-open collapsible panels (port of `react-accessible-accordion`).
12. **`SPPEX.Timeline`**: Vertical timeline with dot connectors (port of `react-vertical-timeline`).
13. **`SPPEX.Highlight`**: JSON/HTML syntax highlighting (port of `react-syntax-highlighter`).
14. **`SPPEX.AvatarGroup`**: Overlapping circular avatar stacks with +N overflow (port of MUI AvatarGroup).
15. **`SPPEX.ProgressBar`**: Linear and circular progress indicators (port of `react-circular-progressbar`).
16. **`SPPEX.Badge`**: Notification dot/counter anchored to elements (port of MUI Badge).

### Navigation & Utilities
17. **`SPPEX.Pagination`**: Page number rendering with ellipses and callbacks (port of `react-paginate`).
18. **`SPPEX.Breadcrumbs`**: Slash-separated path trail generator (port of `react-breadcrumbs`).
19. **`SPPEX.CopyToClipboard`**: Safe clipboard write with visual feedback (port of `react-copy-to-clipboard`).
20. **`SPPEX.WebSocket`**: Auto-reconnecting WebSocket client with exponential backoff (port of `react-use-websocket`).

---

## The SPPUX Advantage
- **Total Modules**: 35 native implementations across 3 tiers (SPPEX, SPPEX Pro, SPPEX Ultra).
- **Combined Footprint**: ~45KB total (all 4 files). Compare to React + ecosystem at 500KB+.
- **External Dependencies**: Zero. Fully air-gapped and sovereign.
- **Compilation**: None. Runs directly via `<script>` tags.
- **Synergy**: Integrated with SPP's `LiveActions` backend protocol for seamless PHP-to-JS communication.
