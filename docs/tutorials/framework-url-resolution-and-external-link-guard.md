# SPP Framework Architecture: Centralized URL Engine & External Link Guard

## 1. Foundational Concepts: Understanding URLs in SPP

When developing web applications, few bugs are as frustrating and common as broken hyperlinks. In multi-tenant, sub-directory routed environments (such as `http://localhost/school1/sppdocs/`), hyperlinks generally fall into two distinct categories:

1. **Internal Application Routes**: Relative or application-scoped paths (e.g. `issues`, `milestones/view`, `admin/login`) that must be routed relative to the application base URI (`/school1/sppdocs/issues`).
2. **External Web Resources**: Third-party domains, repositories, documentation sites, and community portals (e.g. `https://github.com/shuklasp/spp`, `https://satyalab.com`).

### The "Relative URL Trap"
In HTML, if a link target is rendered without a leading protocol scheme (such as `http://` or `https://`), every modern web browser treats the target as a **relative path** against the current directory!

For instance, consider a user or administrator entering `github.com/shuklasp/spp` into a project settings form:
```html
<!-- The browser sees NO scheme, so it resolves relative to the current path! -->
<a href="github.com/shuklasp/spp">Source Code</a>
```
When clicked from `http://localhost/school1/sppdocs/project/demo-app`, the browser navigates to:
```
http://localhost/school1/sppdocs/project/github.com/shuklasp/spp
```
The framework's router receives this request, finds no controller matching `github.com/shuklasp/spp`, and triggers a 404 **"Page not found"** exception.

Previously, each application had to manually write ad-hoc regexes or string checks to prevent this. With the centralized SPP URL engine, **URL resolution and external link guarding are promoted directly to the framework core**.

---

## 2. Framework Architecture & The `\SPP\Core\Url` Engine

The SPP URL subsystem is anchored by `\SPP\Core\Url` (aliased universally as `\SPP\Url`). It sits in the core framework pipeline and provides a unified interface across PHP controllers, CLI commands, view models, and Blade templates.

```
                    ┌──────────────────────────────┐
                    │      Incoming Link Target     │
                    │ (e.g. 'github.com/shuklasp') │
                    └──────────────┬───────────────┘
                                   │
                     Is Absolute Protocol/Scheme?
                 (http://, https://, mailto:, //, #)
                                  / \
                                 /   \
                             YES/     \NO
                               /       \
      ┌───────────────────────┐         ┌───────────────────────────────┐
      │ Keep Protocol Intact  │         │ External or Internal Target?  │
      └───────────┬───────────┘         └───────────────┬───────────────┘
                  │                                     │
                  │                    ┌────────────────┴───────────────┐
                  │                    │                                │
                  │            Internal Route                   External Domain
                  │         ('issues', '/admin')            ('satyalab.com', 'foo.org')
                  │                    │                                │
                  │       Prepend Application Base            Prepend Default Scheme
                  │     (/school1/sppdocs/issues)             (https://satyalab.com)
                  │                    │                                │
                  └────────────────────┼────────────────────────────────┘
                                       │
                         ┌─────────────▼───────────────┐
                         │ Fully Qualified, Safe Target │
                         └─────────────────────────────┘
```

### Recognized Absolute Protocols
The engine automatically protects absolute protocols from being prepended with internal application base paths:
- Web: `http://`, `https://`, `//` (protocol-relative)
- Communication: `mailto:`, `tel:`, `sms:`
- Navigation: `#` (in-page anchor hash)
- Special: `ftp://`, `javascript:`, `data:`

---

## 3. Core Framework APIs

### A. `\SPP\Core\Url::to()` (Internal & Smart Routing)
Generates an application URL or preserves external URLs with optional query parameters.

```php
use SPP\Core\Url;

// 1. Internal application route (resolves to '/school1/sppdocs/issues')
$route = Url::to('issues', 'SPPDocs');

// 2. Route with automatic query string building (resolves to '/school1/sppdocs/issues?status=open&page=2')
$filtered = Url::to('issues', 'SPPDocs', ['status' => 'open', 'page' => 2]);

// 3. Absolute external link passed to to() (intact: 'https://github.com/shuklasp/spp')
$external = Url::to('https://github.com/shuklasp/spp');
```

### B. `\SPP\Core\Url::external()` (External Normalization & Scheme Guard)
Ensures any external domain or link has a valid protocol prefix (`https://` by default).

```php
// Missing protocol is automatically prefixed with https://
echo Url::external('github.com/shuklasp/spp');
// Output: 'https://github.com/shuklasp/spp'

// Existing protocol is preserved untouched
echo Url::external('http://legacy-intranet.local');
// Output: 'http://legacy-intranet.local'

// Empty inputs and in-page hashes remain safe
echo Url::external('#features');
// Output: '#features'

echo Url::external(null);
// Output: ''
```

### C. `\SPP\Core\Url::isExternal()`
Inspects whether a string points outside the application or matches an external domain pattern.

```php
Url::isExternal('https://google.com');       // true
Url::isExternal('github.com/shuklasp/spp'); // true
Url::isExternal('/school1/sppdocs/issues');  // false
Url::isExternal('#table-of-contents');       // false
```

### D. `\SPP\Core\Url::current()` and `Url::previous()`
Helper methods to inspect the current HTTP request URL or referer.

```php
$currentUrl = Url::current();             // Full request URL with query
$cleanUrl   = Url::current(false);        // Strips ?query=...
$referer    = Url::previous('/dashboard');// Fallback to /dashboard if referer empty
```

---

## 4. Universal Framework Helpers & Blade Directives

### Direct Integration in `\SPP\App`
The framework facade `\SPP\App` natively exposes these methods:

```php
// Generate routed application URL:
$url = \SPP\App::url('milestones/view', 'SPPDocs', ['id' => 'ms_123']);

// Normalize external link:
$repo = \SPP\App::externalUrl('github.com/shuklasp/spp');
```

### Universal Global PHP Helpers
Included automatically during framework boot (`sppinit.php`):

```php
// url($path, $appName, $queryParams)
$admin = url('admin/roles', 'SPPDocs');

// external_url($url, $defaultScheme)
$site = external_url('satyalab.com');
```

### SPPBlade Directives
In any Blade template (`.blade.php`), developers can write clean directives without boilerplate PHP string concatenation:

#### 1. `@url('route')`
Renders an application route or leaves external URLs intact.
```blade
<!-- Internal route -->
<a href="@url('issues/board?projectId=' . $projectId)">Kanban Board</a>

<!-- External URL passed dynamically (will not prepend app base) -->
<a href="@url($item['url'])">Learn More</a>
```

#### 2. `@external_url($target)` or `@externalUrl($target)`
Guarantees a safe external hyperlink with automatic `https://` prefixing:
```blade
<!-- User-provided repository URL from YAML or database -->
<li>
    <a href="@external_url($project['links']['source'])" target="_blank" rel="noopener">
        💻 Source Code &rarr;
    </a>
</li>

<!-- User-provided official website -->
<a href="@external_url($project['links']['website'])" target="_blank" rel="noopener">
    Visit Website
</a>
```

---

## 5. Step-by-Step Practical Walkthrough

### Scenario: Building a Project Settings Form with Safe Links

#### Step 1: Controller Data Ingestion & Normalization
In your controller action handling settings form submissions, use `Url::external()` or `\SPP\App::externalUrl()` to sanitize user inputs before persistence:

```php
namespace App\MyModule\Controllers;

use SPP\Core\Url;
use SPPMod\SPPView\ViewController;

class SettingsController extends ViewController
{
    public function saveSettings()
    {
        $rawWebsite = $_POST['website'] ?? '';
        $rawRepo    = $_POST['source_code'] ?? '';

        // Store cleaned, normalized links
        $cleanConfig = [
            'website' => Url::external($rawWebsite),
            'source'  => Url::external($rawRepo),
        ];

        // Save to database or project YAML...
    }
}
```

#### Step 2: Rendering in Views
In your Blade view, render the links safely:

```blade
<div class="project-links">
    @if(!empty($project['links']['website']))
        <a href="@external_url($project['links']['website'])" target="_blank" rel="noopener">
            🌐 Website
        </a>
    @endif

    @if(!empty($project['links']['source']))
        <a href="@external_url($project['links']['source'])" target="_blank" rel="noopener">
            💻 Source Code
        </a>
    @endif
</div>
```

---

## 6. Backward Compatibility & Impact Analysis

- **Zero Breaking Changes**: Existing calls to `\SPP\App::url('path')` behave exactly as before for internal routes.
- **Bug Fix for External Protocol URLs**: Previously, passing `https://example.com` to `\SPP\App::url()` returned `/school1/sppdocs/https://example.com`. The framework now correctly recognizes absolute schemes and returns them without base URL mangling.
- **Universal Availability**: `\SPP\Core\Url`, `\SPP\Url`, `\SPP\App::externalUrl()`, `external_url()`, and `@external_url` are available to all modules, apps, and plugins across the entire SPP ecosystem.
