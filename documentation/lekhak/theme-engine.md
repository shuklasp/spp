# Lekhak System: Drishyam Theme Engine

Drishyam is the advanced, polyglot theme engine for Lekhak and other SPP applications. It supports dual-format rendering (Blade & SPPUX) and provides application-level theme isolation.

## 1. Core Architecture
Drishyam decouples themes from the core application, allowing multiple themes to coexist and be switched dynamically based on context.

*   **Registry**: Automatically scans theme directories defined by the application.
*   **Negotiation**: Uses context rules (e.g., URL paths) to decide which theme to use (e.g., `admin` vs `site`).
*   **Dual Formats**: Supports traditional server-side Blade templates and modern client-side SPPUX components.

## 2. Configuration (`drishyam.yml`)
Configure theme behavior at the application level:

```yaml
default_theme: premium
contexts:
  admin: glass_admin  # Use this theme for administrative paths
  site: premium       # Use this for public-facing paths
hooks:
  drishyam.theme_init: \SPPMod\Lekhak\Hooks\ThemeInit::handle
```

## 3. Creating a Theme
Themes live in the application's `resources/themes/` folder. Each theme requires a `theme.yml`:

```yaml
name: "Professional Theme"
type: site
JS_DRIVER: sppux  # Prefers SPPUX for dynamic components
```

### Supported Templates:
*   **Blade**: `views/*.blade.php` (Server-side)
*   **SPPUX**: `comp/*.sppux.js` (Client-side components)

## 4. Hook System
Extend theme behavior through event hooks:

*   **`drishyam.boot`**: Fired when the engine starts.
*   **`drishyam.theme_init`**: Fired when a theme is loaded; ideal for injecting global variables.
*   **`drishyam.before_render`**: Fired before the rendering starts, allows data modification.

---
[Back to Index](index.md)
