# SPP Routing Engine: The Nerd's Guide 🧠

Welcome to the internal mechanics of the Satya Portal Pack (SPP) Routing Engine. This guide covers the context-aware resolution logic, module delegation, and asset directory mapping introduced in the Phase 5 "Universal Route" update.

## 1. Path Resolution Rules

The routing engine uses a deterministic three-tier resolution strategy based on the path prefix and the discovery context.

### A. Absolute Routes (The Root Override)
Any path starting with a forward slash (`/`) or backslash (`\`) is resolved relative to the `APP_BASE_DIR` (the physical root of your SPP installation).

*   **Syntax**: `/path/to/file.php`
*   **Resolved to**: `[ROOT]/path/to/file.php`
*   **Use Case**: Accessing shared system files, `etc` configurations, or cross-application common assets.

### B. Relative Routes (Context-Aware)
Paths that do **not** start with a slash are resolved based on where they are declared.

#### I. Module Context
If a route is declared in a `module.yml` file, it is considered "Module-Local".
*   **Syntax**: `views/dashboard.php`
*   **Resolved to**: `[ROOT]/modules/[MODULE_NAME]/views/dashboard.php`
*   **Nerd Note**: The router automatically calculates the relative offset from the root using `realpath` resolution to ensure symlinks don't break the pathing.

#### II. Application Context
If a route is declared in the application's `pages.yml` or via `App::getAppConf('routes')`, it is considered "App-Source".
*   **Syntax**: `profile.php`
*   **Resolved to**: `[ROOT]/src/[APP_NAME]/profile.php`
*   **Nerd Note**: This ensures application isolation while allowing simple relative file names.

---

## 2. Route Types

### Static URL Mapping
Maps a virtual URL to a physical PHP or template file.

```yaml
# pages.yml
pages:
  my-profile:
    url: user/profile.php   # -> src/user/profile.php
  system-conf:
    url: /etc/config.php    # -> [ROOT]/etc/config.php
```

### Asset Directory Mapping (Recursive Serving)
The `assets` key allows you to mount entire physical directories onto a virtual URL prefix.

```yaml
# module.yml
module:
  name: image_vault
  routes:
    gallery:
      assets: res/images    # -> modules/image_vault/res/images/*
```

**Request Flow**:
1. User hits `/gallery/icons/user.svg`.
2. Router matches prefix `gallery`.
3. Router appends the remainder (`icons/user.svg`) to the base (`res/images`).
4. Engine serves `modules/image_vault/res/images/icons/user.svg` with correct MIME types and cache headers.

---

## 3. The Discovery Pipeline

When a request arrives, the `SPPMod\SPPView\Pages` class executes the following pipeline:

1.  **Primary Source**: Check `pages.yml` (or DB) in the current application context.
2.  **Module Registry**: Iterates through all registered `SPP\Module` objects and checks their `Routes` property.
3.  **App Configuration**: Checks the dynamic `routes` array in the application runtime config.
4.  **Global Specials**: Checks for system-level special routes (e.g., `getResource`, `getFile`).

---

## 4. Advanced: Special Route Methods

You can invoke internal framework methods by setting the `special: 1` flag.

*   **`serveResource`**: Serves files from the configured `resdir`.
*   **`serveDirectory`**: The internal engine used for directory mapping. It handles:
    *   `Content-Type` detection for over 15 modern formats.
    *   `Cache-Control: public, max-age=31536000` for production performance.
    *   404 handling with graceful exits.

## 5. Zero-Direct-Access Infrastructure & Asset Routing

As part of the framework hardening campaign, SPP implements a mandatory **Zero-Direct-Access** security policy governing all external file deliveries.

### A. Centralized Security Manifest (`global-config.yml`)
Direct web access to framework, application, or module directories is completely blocked at the core boundary level. The access configuration is maintained centrally:
```yaml
security:
  block_direct_access: true
  authorized_asset_routes:
    - assets/core
    - assets/comp
    - assets/img
```
When `block_direct_access` is enabled, any HTTP requests aiming directly at physical disk path resources (e.g., `/src/lekhak/comp/lekhak.js`) are intercepted by the dispatcher and blocked with a `403 Forbidden` response.

### B. Autonomous Module Startup Discovery
During core system initialization, `SPP\Module::register()` scans each module manifest (`module.yml`) to ingest authorized internal asset mappings automatically:
```yaml
module:
  name: lekhak
  assets:
    - comp
    - js
    - img
```
The framework maps these declarations into its persistent internal virtual route map (`__asset_routes`). Requests for module assets are mapped cleanly via authorized paths:
*   **Virtual Requested Path**: `/assets/mod/lekhak/comp/lekhak.js`
*   **Resolved Disk Target**: `[ROOT]/src/lekhak/modules/lekhak/comp/lekhak.js`
*   **Security Outcome**: Safely delivered via controlled dispatcher streams while maintaining true disk location privacy.

## 6. Attribute-Based Routing (PHP 8)

The routing engine now fully supports PHP 8 `#[Route]` attributes, allowing developers to define routes directly alongside their controller logic, eliminating the need to maintain external `pages.yml` files for application logic.

### A. The Route Attribute
You can decorate any class method (usually extending `SPPObject` or `ResourceController`) with `#[Route]`:

```php
namespace App\Controllers;

use SPP\Core\Attributes\Route;

class UserController extends \SPP\SPPObject
{
    #[Route(path: '/api/v1/users', method: 'GET')]
    public function index()
    {
        // Route implementation...
    }
}
```

### B. The Attribute Router (`AttributeRouter`)
During the application boot sequence, `Pages::getYaml()` internally invokes the `AttributeRouter`.
This component recursively scans the `src/` directory (or specific controller namespaces), using PHP's native Reflection API to identify classes and methods decorated with `#[Route]`.

### C. Performance & Caching
To maintain the framework's strict performance guarantees, reflection parsing is **not** performed on every request.
1. In development (`app.debug` = true), routes are dynamically discovered.
2. The router caches the entire attribute map into `var/cache/routes.cache.php`.
3. In production, this cache is natively required, providing O(1) array access without any reflection overhead.

### D. Parallel Coexistence
Attribute routing seamlessly coexists with `pages.yml` and database-driven routing. The engine merges these definitions into a unified routing table, prioritizing them intelligently.

---

*Documentation maintained by the SPP Framework Team.*
