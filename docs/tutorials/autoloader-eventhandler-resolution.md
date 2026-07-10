# SPP Novice-First Guide: Autoloader & Event Handler Resolution

Welcome to the comprehensive, novice-first tutorial on the SPP Framework's Autoloader and Event Handler resolution architecture. If you are entirely new to SPP, this guide will provide you with a complete, end-to-end ("in and out") understanding of how classes are discovered, loaded, and managed within the framework.

---

## 1. Foundational Concepts

### What is an Autoloader?
In modern PHP development, manually writing `require` or `include` statements for every class file is tedious and error-prone. An **Autoloader** is a mechanism that automatically locates and loads the file containing a required class, interface, or trait at the exact moment it is first referenced in your code.

### Why does SPP have a Custom Autoloader?
The SPP Framework implements a high-performance, native classmap autoloader (`\SPP\Core\Autoloader`) to minimize external dependency on Composer and provide blazing-fast execution. It is specifically tailored to understand SPP's unique modular directory structure, including Core classes, Modules (`SPPMod`, `ContribMod`, `AppMod`), PSR polyfills, App namespaces, and `EventHandlers`.

### What are Event Handlers?
In SPP, core behaviors—such as rendering views, handling 404 errors, or enforcing contexts—are decoupled into **Event Handlers**. For instance, when a view is rendered, `\SPP\SPPEvent::fireEvent` dispatches `event_spp_view_render` with a default handler named `DefaultViewRenderHandler`.

---

## 2. Lifecycle & Architecture

### The Autoloading Lifecycle
When an unloaded class is referenced (e.g., `\EventHandlers\Defaults\DefaultViewRenderHandler`), PHP delegates the search to `Autoloader::loadClass($className)`. The lifecycle proceeds as follows:

1. **Cache Lookup**: The Autoloader first checks its in-memory `$classMap` (populated from `classmap.php`). If the class path is cached and the file exists, it is required immediately.
2. **Interface & Exception Aliasing**: Checks for backward-compatibility aliases (e.g., aliasing `SPP\SPPException` to custom exception types).
3. **Prefix Resolution**: Splits the class namespace to determine the category of the class:
   - `SPP\*`: Core framework files or PSR polyfills.
   - `SPPMod\*`, `ContribMod\*`, `AppMod\*`: Modular extensions.
   - `App\*`: Application-specific logic (Entities, Components, Services).
   - `EventHandlers\*`: Decoupled event handler classes located in the `spp/events` directory.
4. **Cache Persist**: If a file is successfully located, its path is stored in `$classMap`. On system shutdown, `saveMap()` persists the updated map to disk.

### Interaction with Core Modules
- **ViewRouter & ViewRenderer**: When `ViewRouter::showPage()` triggers view rendering, `ViewRenderer::renderFile()` dispatches the render event. The `SPPEvent` class attempts to instantiate `\EventHandlers\Defaults\DefaultViewRenderHandler`. The Autoloader intercepts this request, resolves the path to `spp/events/Defaults/DefaultViewRenderHandler.php`, and loads it seamlessly.

---

## 3. Step-by-Step Tutorials

### Configuring and Creating a Custom Event Handler
Here is how a novice developer can configure and deploy a custom event handler from scratch.

#### Step 1: Create the Event Handler Class
Create a file named `MyCustomHandler.php` inside `spp/events/Custom/`:

```php
<?php
namespace EventHandlers\Custom;

use SPP\EventHandler;

class MyCustomHandler extends EventHandler
{
    public function overrideHandler(mixed &$params = [])
    {
        // Custom event handling logic
        $params['message'] = 'Handled by MyCustomHandler!';
    }
}
```

#### Step 2: Dispatch the Event in Your Controller or Service
In your application logic, dispatch the event and specify your custom handler as the default fallback:

```php
<?php
namespace App\Samvaad\Controllers;

use SPP\EventParams;
use SPP\SPPEvent;

class DemoController
{
    public function index()
    {
        $data = ['message' => 'Initial state'];
        $params = new EventParams($data);
        
        // Dispatch event with our custom handler
        SPPEvent::fireEvent('my_custom_event', $params, 'Custom\\MyCustomHandler');
        
        echo $params->getPayload()['message']; // Outputs: Handled by MyCustomHandler!
    }
}
```

---

## 4. Impact of Deletions/Modifications

### Legacy Behavior
Previously, `Autoloader::resolveEventHandlersClass()` constructed the file path using `SPP_BASE_DIR . '/spp/events/'`. Because `SPP_BASE_DIR` is defined in several entry points as `__DIR__ . '/spp'`, this resulted in a duplicated directory structure (`.../spp/spp/events/...`). Consequently, `DefaultViewRenderHandler` could not be found, leading to silent rendering failures where pages appeared blank.

### Rationale Behind the Modification
To guarantee robust, environment-agnostic path resolution, `resolveEventHandlersClass()` was updated to inspect multiple potential root paths regardless of how `SPP_BASE_DIR` is initialized.

### Exact Modification Details
The resolution logic now checks an array of candidates:
```php
$search = [
    SPP_BASE_DIR . DIRECTORY_SEPARATOR . 'events' . DIRECTORY_SEPARATOR . $subPath . $class . '.php',
    SPP_BASE_DIR . DIRECTORY_SEPARATOR . 'spp' . DIRECTORY_SEPARATOR . 'events' . DIRECTORY_SEPARATOR . $subPath . $class . '.php',
    dirname(SPP_BASE_DIR) . DIRECTORY_SEPARATOR . 'spp' . DIRECTORY_SEPARATOR . 'events' . DIRECTORY_SEPARATOR . $subPath . $class . '.php'
];
foreach ($search as $file) {
    if (file_exists($file)) return $file;
}
```
This ensures flawless discovery of all `EventHandlers` across all CLI and Web SAPIs.
