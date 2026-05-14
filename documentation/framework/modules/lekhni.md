# Lekhni Editor: Ultimate Framework Contrib Generic Workspace Engine

**Lekhni** is an advanced, framework-level rich text block and code editing workspace housed natively inside the central contrib directory (`spp/modules/contrib/lekhni/`). Designed to enforce complete application neutrality, it provides a fully autonomous, zero-dependency engine capable of side-by-side dual-mode editing (VSCode-style IDE vs. Block-based Rich Text) out of the box across any application context.

This manual documents Lekhni from three distinct perspectives: **SPP Core Developers**, **SPP Application Developers**, and **End-Users / Content Creators**, complete with concrete code integrations and step-by-step usage tutorials.

---

## 🛠️ Perspective 1: SPP Core Developer Architecture

From the core framework layer, Lekhni operates as an isolated, self-contained sovereign workspace. It completely purges third-party external CDN requirements (`cdnjs`) by bundling all necessary syntax parsing, custom styles, and reactive components directly within its package space.

### Physical Package Layout (`spp/modules/contrib/lekhni/`)
```text
spp/modules/contrib/lekhni/
├── api/
│   └── class.lekhniapi.php     # Namespace SPPMod\Lekhni\Api; Handles uploads & get_settings
├── etc/
│   └── config.yml              # Local declarative configuration fallbacks
├── js/
│   ├── lekhni-editor.js        # The primary reactive UI shell inheriting from BaseComponent
│   └── monaco-engine.js        # Built-in lightweight multi-language syntax tokenizer
└── module.yml                  # Standard module declarative package manifest
```

### Component Inheritance & Internal Architecture
1. **`BaseComponent` Foundation**: `LekhniEditor` inherits directly from `spp/modules/spp/sppux/js/BaseComponent.js`, utilizing lightweight **lit-html** string directives to manage state reactivity symmetrically.
2. **Sovereign Code Engine**: The internal tokenizer (`monaco-engine.js`) intercepts plain code inputs, escaping raw characters and wrapping syntax structures (`const`, `function`, `<tags>`, `properties:`) in highly tailored inline CSS highlight sets.
3. **Storage Abstraction**: Inline features such as Gallery Grids, Monaco code snippets, and review Tooltips utilize pure HTML5 tags and descriptive `data-` attributes, ensuring the storage framework serializes documents cleanly as valid XHTML strings.
4. **Backend API Isolation**: Requests routing through `class.lekhniapi.php` parse global defaults and local `module.yml` settings dynamically, isolating database path logic securely within `\SPP\App::getApp()->getDataDir()` relative paths.

---

## 💻 Perspective 2: SPP Application Developer Integration Guide

Application developers can instantiate high-fidelity Lekhni editors dynamically on the fly within custom application layouts, forms, or standalone single-page views without introducing static cross-app dependencies.

### Example 1: Basic Custom Component Integration

To embed Lekhni inside a custom app view (e.g., `src/lekhak/comp/editor.js`), simply inherit from the generic contrib path:

```javascript
import LekhniEditor from '../../../../spp/modules/contrib/lekhni/js/lekhni-editor.js';

export default class CustomAppEditor extends LekhniEditor {
    // Inherits all core enterprise frameworks automatically
}
```

### Example 2: Mounting Inline on the Fly via Component Props

When mounting Lekhni dynamically inside another screen controller, use configuration props to enable inline mode and capture output payload strings continuously:

```javascript
import LekhniEditor from '../../../../spp/modules/contrib/lekhni/js/lekhni-editor.js';

class ScreenController {
    renderForm(container) {
        const editorHost = document.createElement('div');
        container.appendChild(editorHost);

        // Instantiate the generic workspace cleanly
        const editor = new LekhniEditor(editorHost, {
            id: 'doc_101',
            title: 'Quarterly Architecture Strategy',
            body: '<p>Initial layout draft strings...</p>',
            mode: 'document',             // Initialize in "document" mode or "code" mode
            language: 'html',             // Default tokenizer syntax target
            embedded: true,               // Disables fullscreen sidebars/nav overlays
            onChange: (outputPayload) => {
                console.log("Real-time serialized string capture:", outputPayload);
                // Dispatch update to custom controller layers
            }
        });
        
        editor.mount();
    }
}
```

### Configuring Default Behavior via App Manifests
Downstream apps can override initialization settings by appending declarative configurations directly inside their custom `module.yml` manifests:
```yaml
settings:
  editor:
    default_mode: "document"
    theme: "dark"
    auto_save_interval: 3000
    media_upload_path: "var/custom_app/media"
    categories:
      - "Internal Report"
      - "Technical Spec"
```

---

## ✍️ Perspective 3: End-User & Content Creator Guide

Lekhni delivers an intuitive, beautifully structured Notion/VSCode workspace combining immediate block flexibility with absolute document safety.

### Core Authoring Interface Features

#### 1. The Floating Slash Command Palette (`/`)
Simply type `/` anywhere inline inside a document paragraph to launch an instant popover menu floating immediately above your cursor. Use the arrow keys or keep typing to filter blocks instantly:
*   **Heading 1 (`/h1`)** & **Heading 2 (`/h2`)**: Structural document anchor points.
*   **Quote (`/quote`)**: Highlighted side-bordered italic callouts.
*   **Code Block (`/code`)**: Instantly injects an embedded programming canvas snippet directly inline.
*   **Web Card (`/card`)**: Creates interactive domain overview boxes.
*   **Image Grid (`/gallery`)**: Inserts layout array dropzones.
*   **AI Co-Pilot (`/ai`)**: Augments active writing with background synthesis strings.

#### 2. Native Markdown Shortcuts
Keep your fingers directly on your keys. Lekhni processes standard keyboard input actions continuously:
*   Type `# ` followed by space to convert a line into an **H1 Header**.
*   Type `## ` followed by space to create an **H2 Sub-header**.
*   Type `> ` followed by space to convert into a **Blockquote**.
*   Type ```` ``` ```` followed by space or Enter to embed an **Inline Monaco Code Block**.

#### 3. Contextual Formatting Bubble Menu
Highlight any text string snippet to instantly reveal a sleek glassmorphic popover providing one-click options for **Bold**, **Italic**, **Underline**, and inserting hyperlinked references seamlessly.

---

### 🏢 Ultimate Enterprise Capabilities Workflow Tutorials

#### Tutorial 1: Building a Multi-Image Grid Gallery
1. Open your document in **📝 Document Mode**.
2. Select multiple image files directly from your computer's native file explorer.
3. **Drag and drop** the entire batch straight onto the active editor writing area.
4. Lekhni displays an immediate real-time upload status notification. Once processed, it automatically wraps the entire array inside a beautiful responsive **CSS Flexbox Grid** block rather than messy line breaks.

#### Tutorial 2: Creating Glassmorphic OEmbed Web Cards
1. Locate any external webpage address (e.g., `https://github.com/spp-framework`).
2. Paste the full secure URL string directly into your document paragraph.
3. **Press the Spacebar**. Lekhni immediately intercepts the raw text, querying domain labels to replace the text with a gorgeous bounding preview box featuring automated launch action handles.

#### Tutorial 3: Adding Persistent Reviewer Annotations
1. Highlight a specific sentence or code string that requires collaboration or feedback.
2. In the resulting floating Bubble Menu, click the **💬 Annotate** button.
3. An input prompt requests your specific auditing notes. Enter your review string (e.g., *"Verify database connection retry logic here"*).
4. Lekhni immediately wraps the highlighted subset securely inside stylized permanent `<mark>` bounding structures carrying explicit review tooltips visible on hover.

#### Tutorial 4: Recovering Data from Offline Database Caches
1. Begin writing content normally. Lekhni captures background continuous snapshots directly into your browser's persistent **IndexedDB** (`LekhniEnterpriseStore`) on every keystroke.
2. Simulate a browser termination or network crash by reloading your browser window without clicking save.
3. Upon launching, Lekhni detects the orphaned draft snapshot buffer, rendering an elegant **yellow warning recovery bar** across the top of the interface.
4. Click **"Restore Data"** to immediately dump the captured offline strings safely back into the active editing view.

#### Tutorial 5: Using the Historical Revision Time Machine
1. Commit multiple consecutive server updates by clicking the **"Save Draft"** or **"Publish"** actions over time.
2. Click the **🕰️ History** top header button.
3. An immersive popover overlay opens, rendering a side-by-side snapshot browser.
4. Select any chronological timestamp item from the left-hand index list to inspect past file layouts. Click **"Restore This Revision Buffer"** to automatically rollback document states instantly.

#### Tutorial 6: Dual-Mode Workspace Pivoting
1. Start authoring standard rich sections visually inside **📝 Document Mode**.
2. Click the top header tab switcher labeled **💻 VSCode IDE Mode**.
3. Lekhni instantly maps your document markup strings straight into a sovereign code editor equipped with complete live syntax highlighters and line numbering frames. Edit deep document architectures, then pivot back instantaneously without breaking element nodes.
