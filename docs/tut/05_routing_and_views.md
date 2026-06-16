# 05. Routing and Views

Routing in SPP is simple and flexible, allowing you to define static routes, use modern PHP Attributes, or dynamic pages with AJAX fragment loading.

---

## Attribute-Based Routing (Modern Approach)

SPP supports modern PHP attribute-based routing directly on your controller methods. This keeps your routing logic tightly coupled with your implementation.

```php
namespace App\Controllers;

use SPPMod\SPPView\Attributes\Route;
use SPPMod\SPPView\Attributes\Middleware;
use App\Middleware\AuthMiddleware;

#[Middleware(AuthMiddleware::class)]
class UserController {
    
    #[Route('/users/{id}', method: 'GET', name: 'user.show')]
    #[Middleware(LogMiddleware::class)]
    public function show(string $id) {
        // Render user view
    }
}
```
The framework automatically scans `controllers/` and `src/Controllers/` directories and aggressively caches the routes for high performance. It will also queue and execute any Middleware classes defined via attributes.

---

## Defining Routes in `pages.yml` (Legacy/Configuration Approach)

The `etc/pages.yml` file allows you to define routes centrally. A typical page definition looks like this:

```yaml
pages:
  - name: home
    url: /home.php
  - name: about
    url: /about.php
  - name: profile
    url: /user_profile.php
```

### Static vs. Dynamic Paths

If a request URL (e.g., `?q=profile/123/edit`) matches a registered page name (`profile`), the remaining parts of the URL are automatically passed as positional parameters.

```php
$pageData = \SPPMod\SPPView\Pages::getPage();
$userId = $pageData['params'][0]; // This retrieves "123"
```

---

## High-Performance View Engine & Pre-compilation

SPPView utilizes a lightning-fast, AST-based DOM compiler (`ViewCompiler`). It parses HTML and custom elements down to highly optimized, natively cached `.php` files in `var/cache/views`.

To ensure maximum performance in production, you can pre-compile all views using the SPP CLI:
```bash
php spp.php view:cache
```

### AST-Based Control Structures
SPPView provides custom HTML elements that compile directly to native PHP control structures, making your templates cleaner:

```html
<spp-if condition="isset($user)">
    <p>Welcome, <?= $user->name ?></p>
</spp-if>

<ul>
<spp-foreach loop="$users as $u">
    <li><?= $u->name ?></li>
</spp-foreach>
</ul>

<!-- Flash Messages -->
<spp-flash key="success"></spp-flash>
```

### 1. Show the Page
```php
\SPPMod\SPPView\ViewPage::showPage();
```
This is the core rendering method. It handles routing and includes the appropriate compiled PHP file.

### 2. Include Assets Dynamically
```php
\SPPMod\SPPView\ViewPage::addCssIncludeFile('res/custom.css');
\SPPMod\SPPView\ViewPage::addJsIncludeFile('res/custom.js');
```

---

## SPA "Drop and Play"

SPP can automatically "augment" your static PHP/HTML pages to behave like a Single Page Application (SPA). When enabled, the framework:
1.  **Intercepts** link clicks (`<a>` tags) and form submissions.
2.  **Fetches** only the necessary HTML content (fragments) using AJAX.
3.  **Updates** the page content without a full reload.

To enable this globally, set the following in your `sppview` configuration:
```yaml
auto_page_augmentation: true
auto_js_injection: true
```

---

## Passing Data to Views

Use the `SPPGlobal` class to store and retrieve data across your application's lifecycle:

```php
\SPP\SPPGlobal::set('user_name', 'John');
//... in your view file:
echo \SPP\SPPGlobal::get('user_name');
```

---

[**Next: Forms & Validation**](06_forms_and_validation.md)
