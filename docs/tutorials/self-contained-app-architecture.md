# Tutorial: Self-Contained Application Architecture in SPP

## 1. Foundational Concepts (Novice-First Guide)

If you are new to the **SPP Framework**, one of the first design principles to understand is how applications organize their code and configuration.

### What is a Self-Contained Application?
Traditionally in legacy SPP installations, application files were split across the filesystem:
* Your source code, controllers, and views lived in: `src/<AppName>/`
* Your route definitions, workflows, entities, and forms lived in: `etc/apps/<AppName>/`

While this split worked for monolithic deployments, modern modular architectures benefit from **Self-Contained Applications**. In a self-contained application, **100% of the application assets, logic, and configuration reside in a single directory**:
```text
src/<AppName>/
├── Controllers/         # Controller classes
├── Serv/                # Services & specialized action handlers
├── resources/           # Blade/Twig views, themes, static assets
├── etc/                 # ALL configuration for this application
│   ├── app.yml          # Application manifest and URL mappings
│   ├── pages.yml        # Unified URL route table
│   ├── workflows/       # YAML workflow & state machine definitions
│   ├── entities/        # SPPEntity ORM data schemas
│   └── forms/           # Form Augmentor schema definitions
```

### Why does this exist and what problem does it solve?
1. **Portability**: You can copy or version-control `src/<AppName>` as a standalone package or submodule without hunting down orphaned configuration files in the root `etc/apps/` folder.
2. **Mental Clarity**: A novice developer opening `src/<AppName>` immediately sees everything the app does—its routes, entities, workflows, forms, and controllers—all in one place.
3. **No Cross-Folder Drift**: Eliminates sync bugs where code in `src/` expects routes or schemas that were edited or deleted in `etc/apps/`.

---

## 2. Lifecycle & Architectural Resolution

### How the Framework Resolves Configuration Paths
When an HTTP request or CLI command boots the SPP framework, the core bootstrap class (`\SPP\App`) resolves paths through the following unified pipeline:

```text
HTTP Request / CLI Invocation
       │
       ▼
\SPP\App::getAppConfDir()
       │
       ├──► 1. Checks 'etc_path' in src/<AppName>/etc/app.yml (Auto-discovered)
       │       Resolves to: src/<AppName>/etc/
       │
       ├──► 2. Checks 'src_path' fallback
       │       Resolves to: src/<AppName>/etc/
       │
       └──► 3. Global Legacy Fallback
               Resolves to: etc/apps/<AppName>/
```

### Core Subsystems Updated for Self-Contained Discovery

| Subsystem | Core Class | Resolution Order |
|---|---|---|
| **Routing** | `\SPPMod\SPPRouter\SPPRouter` | `src/<AppName>/etc/pages.yml` → `etc/apps/<AppName>/pages.yml` |
| **Route Mutators** | `SPPRouter::savePage()`, `removePage()` | Dynamically targets resolved `getAppPagesFile()` rather than hardcoding legacy paths |
| **Workflow Engine** | `\SPPMod\SPPWorkflow\WorkflowManager` | Scans `src/<AppName>/etc/workflows/` recursively → then legacy `etc/apps/` |
| **ORM & Entities** | `\SPPMod\SPPDB\SPPEntity` | Scans `src/<AppName>/etc/entities/` and `schemas/` → then legacy `etc/apps/` |
| **Form Augmentation** | `\SPPMod\SPPView\Forms`, `FormAugmentor` | Scans `src/<AppName>/etc/forms/` → then legacy `etc/apps/` |

---

## 3. Step-by-Step Tutorial: Building a Self-Contained App

Follow this quick guide to build a self-contained application from scratch:

### Step 1: Create your application directory
Create the base source directory and configuration folder:
```bash
mkdir -p src/MyApp/etc/workflows
mkdir -p src/MyApp/etc/entities
mkdir -p src/MyApp/etc/forms
mkdir -p src/MyApp/Controllers
mkdir -p src/MyApp/resources/views
```

### Step 2: Define your App Manifest (`src/MyApp/etc/app.yml`)
Create `src/MyApp/etc/app.yml` so SPP's auto-discovery registers your app automatically:
```yaml
# Self-contained application descriptor
base_url: "/myapp"
table_prefix: "MyApp_"
type: "mixed"
shared_group: "core"
```

### Step 3: Define your Routes (`src/MyApp/etc/pages.yml`)
Place your route table directly inside `src/MyApp/etc/pages.yml`:
```yaml
defaults:
  home: home
  pagedir: /src/MyApp

pages:
  home:
    controller: \App\MyApp\Controllers\HomeController@index
  api/ping:
    controller: \App\MyApp\Controllers\HomeController@ping
```

### Step 4: Create your Controller (`src/MyApp/Controllers/HomeController.php`)
```php
<?php

namespace App\MyApp\Controllers;

use SPPMod\SPPView\ViewController;

class HomeController extends ViewController
{
    public function index()
    {
        return $this->render('home', ['message' => 'Hello from Self-Contained App!']);
    }

    public function ping()
    {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'pong', 'timestamp' => time()]);
        exit;
    }
}
```

### Step 5: Test the Application
Open your browser or run curl:
```bash
curl http://localhost/school1/myapp/home
curl http://localhost/school1/myapp/api/ping
```
Both routes immediately resolve from `src/MyApp/etc/pages.yml` with zero configuration in root `etc/`.

---

## 4. Impact of Modifications & Migration Guide

### What was changed?
Previous framework versions contained hardcoded references in:
* `class.spprouter.php`: `savePage()` and `removePage()` wrote exclusively to `APP_ETC_DIR` (`etc/apps/<AppName>/pages.yml`).
* `WorkflowManager.php`: State machine definitions in `src/<AppName>/etc/workflows` were ignored.
* `class.sppentity.php`: Data schemas in `src/<AppName>/etc/entities` were ignored.
* `class.forms.php` & `class.formaugmentor.php`: Form YAML files in `src/<AppName>/etc/forms` were ignored.

### Backward Compatibility Guarantee
100% backward compatible. The resolution order is:
1. **Self-Contained (`src/<AppName>/etc/`)**: Inspected first.
2. **Legacy (`etc/apps/<AppName>/`)**: Used as fallback if self-contained config does not exist.

Existing apps with files in `etc/apps/` will continue functioning without changes.

### Migrating an Existing App to Self-Contained
To migrate an existing app (such as `SPPDocs`):
1. Move `etc/apps/<AppName>/pages.yml` to `src/<AppName>/etc/pages.yml`.
2. Move any directories (`workflows/`, `entities/`, `forms/`) into `src/<AppName>/etc/`.
3. Verify routes via curl:
   ```bash
   curl -I http://localhost/school1/<appname>/home
   ```
4. You can safely remove or archive the old `etc/apps/<AppName>/` directory.
