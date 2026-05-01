# Lekhak System: Theme Engine

The Lekhak Theme Engine is a professional-grade layout management system that decouples application content from its visual presentation.

## 1. How It Works
The engine uses the SPP Event System to intercept the framework's rendering pipeline.

1.  **Event Hook**: The `ThemeEventHandler` listens for the `event_spp_view_render_theme` event fired by `ViewPage`.
2.  **Layout Selection**: It identifies the active theme from `app.yml`.
3.  **Content Wrapping**: It captures the raw page HTML and passes it to the `ThemeManager` for layout application.
4.  **Region Injection**: It injects the content into the theme's `layout.blade.php`.

## 2. Regions & Portals
Themes can define an unlimited number of regions (e.g., `header`, `sidebar_left`, `footer_scripts`).

**Populating a Region:**
```php
use SPPMod\SppTheme\Api\ThemeManager;

ThemeManager::setRegion('sidebar', '<div class="widget">Recent Posts</div>');
```

**Theme Layout (`layout.blade.php`):**
```html
<main>
    {!! $content !!}  <!-- Main page content automatically injected -->
</main>
<aside>
    {!! $sidebar !!}  <!-- Custom content injected via setRegion -->
</aside>
```

## 3. Theme Portability
Each theme is a folder within `src/lekhak/themes/`. It contains its own:
*   `theme.yml`: Metadata and region definitions.
*   `layout.blade.php`: The master template.
*   `css/`, `js/`, `images/`: Local theme assets.

Assets are resolved using the `$theme_path` variable, which points to the theme's public URL regardless of where the app is deployed.

---
[Back to Index](index.md)
