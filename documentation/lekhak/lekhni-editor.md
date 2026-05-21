# Lekhni Editor: Enterprise WYSIWYG & Block Document Engine

Lekhni is the next-generation dual-mode visual publishing engine integrated directly within Lekhak CMS. It functions as both a rich block-based document editor and a raw source stream Monaco IDE, providing authors with offline database safety buffers, real-time revision timelines, and interactive document objects.

---

## 1. Interactive Block Elements & Slash Commands

Lekhni treats documents as structures of interactive components. Authors can invoke the floating **Slash Menu** by typing `/` on a new line or within any editable paragraph.

| Command | Element Name | Technical Implementation | Visual Purpose |
| :--- | :--- | :--- | :--- |
| `/table` | **Smart Spreadsheet Grid** | `.lekhni-smart-grid` with custom data formulas | In-document tabular calculations (`SUM`, `AVERAGE`, `PRODUCT`). |
| `/tasks` | **Tasks Checklist Board** | `.lekhni-tasks-container` row checklist | Dynamic project task tracking with strike-through visual states. |
| `/pdf` | **Adjustable PDF Frame** | `.lekhni-pdf-block-wrapper` housing an iframe | Interactive document viewer with built-in dimension sliders. |
| `/gallery`| **Adaptive Image Grid** | `.lekhni-gallery-grid` with flex-wrapping | Responsive multi-image arrays with drop-zone support. |
| `/code` | **Embedded Monaco Snippet**| `.lekhni-embedded-block` hosting Monaco IDE | Direct code snippet editing and rendering. |
| `/card` | **Rich Web Destination Card**| `.lekhni-web-card` with dynamic launch link | Previews external links with dynamic favicon and domains. |
| `/ai` | **AI Co-Pilot Composer** | Inline co-pilot overlay (activated via `++`) | Automated outline writing, summarization, and tone enhancement. |

---

## 2. In-Depth Component Specifications

### 📊 Smart Formula Spreadsheet Grids (`/table`)
The Smart Grid creates a fully functional tabular cell framework inside the content.

*   **Coordinate Architecture**: Generates cells tagged with IDs from `A1` to `E5`.
*   **Formula Parsing Engine**: Uses standard cell event observers to parse formula strings beginning with `=`. Supports range matching operations:
    *   `SUM(A1:A3)`: Automatically totals cell values inside the coordinate scope.
    *   `AVERAGE(B1:B5)`: Determines the mean of designated coordinates.
    *   `PRODUCT(A1:A2)`: Multiplies specified cells.
*   **Reactive Recalculation**: Binds custom blur and focus listeners to each cell. When a cell loses focus, the system automatically runs a cascading evaluation of all active formula blocks.

### ☑️ Tasks Board Checklist (`/tasks`)
Allows list arrays to function as interactive trackers.
*   **Dual-Mode Interaction**: Checks state both in edit mode and view mode.
*   **Visual Transitions**: Toggling the checkbox instantly updates the linked span element with strike-through text decorations and updates the color properties to a muted `#64748b` token.

### 📄 Adjustable PDF Document Frame (`/pdf`)
Provides embedded PDF viewing without leaving the page.
*   **Rich Control Interface**: Employs interactive CSS sliders within a header overlay.
*   **Dynamic Resizing**: Modulating the sliders directly updates the CSS `width` and `height` properties of the underlying `iframe` tag in real-time, providing immediate visual scaling feedback.

### 🎴 Adaptive Multi-Image Gallery (`/gallery`)
Enables quick layout construction of media grids.
*   **Drop-Zone Support**: Listeners watch standard browser drag-and-drop events. Dragging files directly over the editor triggers batch asset uploads via the framework's media APIs.
*   **CSS Flex Layout**: Organizes multiple uploads into responsive multi-column gallery grids using HSL dark background themes.

---

## 3. Offline Safety & IndexedDB Buffer Architecture

Lekhni implements high-reliability data protection to prevent work loss from session drops, power cuts, or offline transitions.

*   **IndexedDB Safety Store**: Configures an offline local database called `LekhniEnterpriseStore` containing the `offline_drafts` store.
*   **State Detection**: At boot time, Lekhni queries the document ID to find outstanding local snapshots. If it detects a mismatch between the server state and local storage, it triggers a recovery modal offering the author the choice to **Restore** or **Discard** the offline cached data.
*   **Continuous Buffering**: On every keystroke, Lekhni writes a debounced snapshot of the document body, title, and timestamp locally into the IndexedDB instance.

---

## 4. Revision Time Machine & Visual Diffs

Lekhni features a built-in interactive history engine:
*   **Automatic Snapshots**: Captures granular points in history (e.g. `Initial Launch`, `Loaded API Payload`, `Pre-Rollback`).
*   **Timeline Navigation**: Authors can step through historical state indexes.
*   **Safe Rolling Back**: Restoring a historical point captures the forward-state as `Pre-Rollback` first, guaranteeing that history steps are entirely non-destructive and reversible.

---

## 5. Read-Mode Client Interactive Binders

To preserve complete block interactivity for public page views, Lekhak's [node.blade.php](file:///c:/projects/apache/school1/src/lekhak/resources/views/node.blade.php) includes reader-mode interactive scripts:
1.  **PDF Resize**: Reads width and height parameters of `.lekhni-pdf-block-wrapper` and binds standard range sliders to permit public readers to customize viewer dimensions.
2.  **Interactive Checklists**: Unblocks `disabled` properties of checkboxes inside `.lekhni-tasks-container` elements and binds reactive change events to strike/unstrike task items dynamically.
3.  **Active Formulas**: Recalculates formula equations inside `.lekhni-smart-grid` tables instantly on public page cell blurs.

---
[Back to Index](index.md)
