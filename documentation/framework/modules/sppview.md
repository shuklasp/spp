# Core Module: SppView

SppView is the primary presentation and resource management module of the SPP framework. It transforms data and templates into fully augmented HTML pages.

---

## 1. Basic Philosophy
SppView follows the **"Augmented View"** philosophy. Instead of just rendering a template, it treats the resulting HTML as a living document that can be further processed (augmented) to inject scripts, styles, and framework-level UI components.

---

## 2. Architecture
The module is centered around the `\SPP\ViewPage` class.

### Key Lifecycle Stages:
1.  **Preparation**: Gathering data and setting page metadata (title, breadcrumbs).
2.  **Resource Queueing**: Collecting CSS and JS files required for the page.
3.  **Rendering**: Executing the template engine (via overridable events).
4.  **Augmentation**: Post-processing the HTML to inject queued resources and resolve custom tags like `<php-comp>`.
5.  **Theming**: Wrapping the final output in an application-level theme.

---

## 3. API & Usage

### Core Methods
*   `addJsIncludeFile(string $url)`: Queues a JavaScript file for the current page.
*   `addCssIncludeFile(string $url)`: Queues a CSS file.
*   `render(string $filename, array $data)`: Initiates the full rendering and augmentation pipeline.
*   `setPageTitle(string $title)`: Sets the `<title>` and `<h1>` of the page.

### Example Usage
```php
use \SPP\ViewPage;

ViewPage::setPageTitle("Welcome to SPP");
ViewPage::addCssIncludeFile("/res/my-style.css");

ViewPage::render("home.blade.php", [
    'user' => 'Satya',
    'items' => $itemList
]);
```

---

## 4. Events
SppView is highly extensible via the following hooks:
*   `event_spp_view_pre_render`: Modify data before rendering.
*   `event_spp_view_render`: (Overridable) The actual template execution.
*   `event_spp_view_post_render`: Modify raw HTML output.
*   `event_spp_view_before_augment`: Last chance to modify HTML before resource injection.

---
[Back to Modules Index](index.md)
