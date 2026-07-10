# SPP Novice-First Guide: Multi-Tenant Base URL Routing & ViewRouter Integration

Welcome to the comprehensive, novice-first tutorial on the SPP Framework's multi-tenant web routing architecture and `ViewRouter` base URL calculation. If you are entirely new to SPP, this guide will provide you with a complete, end-to-end ("in and out") understanding of how context-aware base URLs are resolved and passed to your views and pages.

---

## 1. Foundational Concepts

### What is Multi-Tenant Web Routing?
In many traditional frameworks, an entire project represents a single web application running at the root domain (e.g., `http://localhost/`). However, the SPP Framework is engineered for multi-tenant, modular enterprise architectures where multiple separate applications (`samvaad`, `cms`, `crm`, `lekhak`, `sppmobile`) coexist under the same directory structure (`src/`).

Each application needs its own isolated base URL prefix (e.g., `http://localhost/school1/samvaad` vs `http://localhost/school1/cms`).

### What is `App::getBaseUrl()`?
`\SPP\App::getBaseUrl($appName)` is the centralized, context-aware routing engine in SPP. It intelligently combines the global web root (`APP_BASE_URI`), the active application context (`Scheduler::getContext()`), and any custom YAML configuration (`base_url` in `app.yml`) to calculate the exact, fully qualified root URI for the active application.

---

## 2. Lifecycle & Architecture

### The Base URL Resolution Lifecycle
When a user requests a page (such as `http://localhost/school1/samvaad?q=contact`), the framework goes through the following lifecycle to ensure correct navigation links:

1. **Context Detection**: `sppinit.php` invokes `Scheduler::detectAndEnforceContext()`, identifying `samvaad` as the active application.
2. **Route Matching**: `SPPRouter` inspects `src/Samvaad/etc/pages.yml` and finds the route definition for `contact` pointing to `pages/contact.php`.
3. **ViewRouter Dispatch**: `ViewRouter::showPage()` takes over to prepare `$pageData` for the view renderer.
4. **Base URL Injection**: `ViewRouter` calls `\SPP\App::getBaseUrl($app->getName())`, resolving the prefix to `/school1/samvaad`. This value is stored in `$pageData['base_url']`.
5. **View Rendering**: `DefaultViewRenderHandler` extracts `$pageData`, making `$base_url` directly available as a PHP variable inside `contact.php`.

### Interaction with Core Modules
- **Drishyam & SPPBlade**: In Blade templates, `{{ $base_url }}` correctly outputs the active app's root URL.
- **Native PHP Fallback**: In native PHP files (`contact.php`), `<?php echo $base_url; ?>` outputs the exact same root URL, ensuring navigation links like `?q=app` and `?q=home` maintain the active app context (`/school1/samvaad?q=app`).

---

## 3. Step-by-Step Tutorials

### Creating a Custom Navigation Menu in Native PHP
Here is how a novice developer can configure and output context-aware navigation links in any native PHP page.

#### Step 1: Define Your Page in `pages.yml`
In `src/Samvaad/etc/pages.yml`, register your custom page:

```yaml
mypage:
    url: pages/mypage.php
    title: "My Custom Page"
```

#### Step 2: Create the Native PHP Page with Context-Aware Links
Create `src/Samvaad/pages/mypage.php` and use `$base_url` to construct absolute navigation links:

```php
<?php
// $base_url is automatically injected by ViewRouter
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?php echo htmlspecialchars($title ?? 'My Page'); ?></title>
</head>
<body>
    <nav>
        <!-- Correctly points to /school1/samvaad?q=home -->
        <a href="<?php echo $base_url; ?>?q=home">Home</a>
        
        <!-- Correctly points to /school1/samvaad?q=app -->
        <a href="<?php echo $base_url; ?>?q=app">SPP-UX App</a>
    </nav>
    <main>
        <h1>Welcome to My Custom Page!</h1>
        <p>Active Base URL is: <code><?php echo htmlspecialchars($base_url); ?></code></p>
    </main>
</body>
</html>
```

---

## 4. Impact of Deletions/Modifications

### Legacy Behavior
Previously, `ViewRouter::showPage()` calculated `$pageData['base_url']` using `rtrim(APP_BASE_URI, '/') . '/' . ltrim($app->base_url ?? '', '/')`. Because `\SPP\App` inherits from `SPPObject` and does not define a public `$base_url` property, `$app->base_url` evaluated to `null`. Consequently, the application context name (`/samvaad`) was completely omitted, resulting in a base URL of `/school1`. This caused navigation menu links on native PHP pages to break, directing users to `http://localhost/school1?q=app` instead of `http://localhost/school1/samvaad?q=app`.

### Rationale Behind the Modification
To ensure absolute consistency between controllers, Blade views, and native PHP pages, `ViewRouter` was updated to delegate all base URL calculations directly to the authoritative static method `\SPP\App::getBaseUrl()`.

### Exact Modification Details
The base URL calculation in `ViewRouter::showPage()` was refactored in both the multi-engine paradigm block and the native PHP block:

```diff
- $pageData['base_url'] = rtrim((defined('APP_BASE_URI') ? APP_BASE_URI : ''), '/') . '/' . ltrim($app->base_url ?? '', '/');
- $pageData['base_url'] = rtrim($pageData['base_url'], '/');
+ $pageData['base_url'] = \SPP\App::getBaseUrl($app->getName());
```

This ensures flawless, context-aware multi-tenant routing across all view paradigms.
