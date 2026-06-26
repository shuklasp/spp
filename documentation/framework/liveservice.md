# 🚀 SPP LiveService: The "Zero-Boring-Code" Architecture

Welcome to the future of SPP development. We've eliminated the boilerplate, nuked the switch statements, and made JavaScript optional for 90% of your UI logic. This is the **Zero-Boring-Code** manifesto.

---

## 🧠 1. The Zen of Zero-Code
Traditional web dev is full of "boring code": registering routes, writing AJAX listeners, and manually updating the DOM. **LiveService** solves this by treating the server as the **Instruction Authority**.

The server doesn't just send data; it sends **Intent**. 

---

## 🕵️ 2. The Nerd's Secret Weapon: Dynamic Discovery
Forget adding `case 'MyAction':` to a giant 3,000-line file. SPP now uses **Dynamic Service Discovery**.

### How it works (The Technical Bits):
When you fire an action named `KillProcess`, `api.php` goes on a hunt:
1. It checks your app's service vault: `src/{app_name}/services/KillProcess.php`.
2. It automatically instantiates a `$la` (**LiveAction**) object for you.
3. It sucks in all your request variables into a `$params` array.
4. It executes your PHP and captures any `echo` output as a primary HTML payload.

**Example: `src/myapp/services/NukeCache.php`**
```php
<?php
// Zero boilerplate. No class. No registration.
$la->notify("Total destruction successful!", "warning")
   ->script("console.log('Cache evaporated');")
   ->send();
```

### 📦 Grouped Services (Advanced)
For complex apps like the **SPP Admin Panel**, you can group related actions into a single file to keep your directory clean.
*   **Request**: `action=Auth.Login`
*   **Resolution**: Looks for `live_Login($la, $params)` inside `src/sppadmin/services/Auth.php`.

This allows for logically grouping high-density service areas (Auth, DB Management, System Config) while maintaining the dynamic, instruction-driven workflow.

---

## 🛠️ 3. HTML-First Interactivity (The "No-JS" Manifesto)
We've baked common UI patterns directly into the **SPPUX** core. You can now build complex reactive interfaces using nothing but **HTML Attributes**.

### The Super-Power Attributes:
*   **`data-spp-live="Action"`**: The payload. Tells SPPUX to ping the server.
*   **`data-live-toggle="class"`**: Toggles a CSS class (perfect for sidebars/modals).
*   **`data-live-remove`**: Deletes the element from existence.
*   **`data-live-target="closest .selector"`**: Relative targeting. Target your parents or siblings without hardcoding IDs.
*   **`data-live-swap="morph"`**: Uses surgical DOM patching (preserves input focus).

---

## 🚀 4. Tutorial: Building a "God-Mode" Counter in 60 Seconds

### Step 1: The UI (HTML)
```html
<div id="counter-panel" style="padding: 20px; background: var(--glass);">
    <h3>Energy Level: <span id="val">9000</span></h3>
    
    <!-- One-line server trigger -->
    <button data-spp-live="Increment" 
            data-live-target="#val" 
            class="btn primary-btn">
        Power Up!
    </button>
</div>
```

### Step 2: The Logic (`src/myapp/services/Increment.php`)
```php
<?php
// Access the params sent by SPPUX automatically
$current = (int)$params['current_val'] ?? 9000;
echo ($current + 1);

$la->notify("Power increasing!", "success");
```

---

## 📊 5. Data Orchestration (GraphQL & SQL)
LiveAction isn't just about UI; it's a powerful data bridge. You can fire complex queries directly from the static helper methods to pre-populate your response data.

### 🧬 GraphQL Gateway
Fetch federated data across multiple databases or modules using the SPPInterDB engine.
```php
// Fetches data and returns a new LiveAction instance
$la = LiveAction::query('{ user(id: 42) { name email } }');
$la->notify("User fetched: " . $la->getData()['user']['name']);
```

### 🗄️ Raw SQL Interface
Fire direct SQL queries via SPPDB when you need raw power, performance, or specialized joins.
```php
// Executes SQL and returns a new LiveAction instance
$la = LiveAction::sql("SELECT * FROM logs WHERE type = ?", ['error']);
$la->notify("Found " . count($la->getData()['data']) . " system errors");
```

---

## 🛠️ 6. The Unified Instruction Set (The Command Reference)

| Instruction | PHP Method | Nerd Translation |
| :--- | :--- | :--- |
| **Morph** | `morph($sel, $html)` | Surgical DOM patching. Fast. Intelligent. |
| **Replace** | `replace($sel, $html)` | The classic `innerHTML` nuke. |
| **Assign** | `assign($sel, $prop, $val)`| Directly sets properties like `value` or `checked`. |
| **Call** | `call($func, ...$args)` | "Remote Procedure Call" for global JS functions. |
| **Script** | `script($js)` | Arbitrary JS execution. Use with caution/glory. |
| **Notify** | `notify($msg, $type)` | Dispatches a toast notification to the user. |
| **Dispatch** | `dispatch($evt, $data)` | Fires a `CustomEvent` for other JS to hear. |
| **Store Sync**| `syncStore($state)` | Hot-swaps the global `spp_root_store` data. |

---

## 🔒 7. Security & Context
Every LiveAction is **Context-Aware**. The server automatically knows which app and module initiated the request, enforcing security gates and loading the correct configuration automatically. 

**Pro-Tip**: Use `data-live-confirm="Are you sure?"` to add a security speed-bump to dangerous actions without writing a single line of JS.

---

## 📂 8. Self-Contained App Pattern
For high-portability, you can consolidate all app resources under your `src_path`. SPP will automatically find your services and config:

**`global-settings.yml`**
```yaml
apps:
  myapp:
    src_path: src/myapp
    etc_path: src/myapp/etc
    services_path: src/myapp/services # Optional, defaults to {src_path}/services
```

This structure ensures that "all other directories are under the selected src directory", making your application a single, deployable unit.

---

## ⚙️ 8. Behind the Scenes: The Core Engine
For the truly curious, the magic isn't in `api.php`. All discovery logic is housed in the **SPPAjax Core Module**.

*   **Entry Point**: `\SPPMod\SPPAPI\SPPAjax::resolveAndExecute($action, $params)`
*   **The Pipeline**:
    1.  **Resolution**: Checks for grouped services (`Group.Method`), then standalone files, then the `General.php` controller.
    2.  **Execution**: Wraps the include/function call in an output buffer to capture echoed HTML.
    3.  **Auto-Injection**: Automatically binds the `$la` and `$params` variables before execution.
    4.  **Finalization**: Merges captured HTML with any `LiveAction` instructions and terminates the request with a unified JSON response.

This architecture ensures that the **Zero-Boring-Code** experience is a native, high-performance feature of the SPP core engine.

---
[Back to Technical Wiki](index.md)
