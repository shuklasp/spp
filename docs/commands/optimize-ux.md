## optimize:ux

**Purpose**: Pre-compiles SPP-UX templates (html`...` literals) Ahead-Of-Time (AOT) to eliminate browser JIT parsing overhead.

### Synopsis
```bash
php spp.php optimize:ux [--app=AppName]
```

### Extended Usage
SPP-UX operates without a Node.js build step (like Webpack or Vite). By default, SPP-UX parses tagged template literals (html`...`) using Regex and `document.createTreeWalker` directly in the browser on the very first mount. While this is fast, large dashboards might experience a slight "First-Paint Penalty". 

The `optimize:ux` command statically analyzes `.js` files within the framework and your application, extracts the template strings, and computes the fine-grained `PartDescriptor` metadata on the server. It caches this in a globally available JS script, allowing the browser to skip the parsing phase entirely.

### Options Available
- `--app=AppName` (default: 'default'): Specify the application directory to scan in addition to the core framework components.

### Under the Hood Activity
- Scans `spp/modules/spp/drishyam/js/` and `src/<AppName>/` for `.js` files.
- Employs Regex to extract `html` template strings.
- Leverages PHP's `DOMDocument` and `DOMXPath` to compute accurate `TreeWalker` path metadata for dynamic template holes.
- Writes the compiled cache to `public/sppux-cache.js`, making it available to the SPP-UX runtime via `window.__SPP_UX_CACHE__`.
