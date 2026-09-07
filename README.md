# SPP Framework

A high-performance, modular PHP framework serving as a solid "wedge" for building robust web applications and APIs.

SPP focuses on a boringly solid kernel, providing an App Boot cycle, Modular architecture, Attribute-based Routing, View rendering, and SQL/XDB data layers out of the box.

## Table of Contents
1. [Installation](#installation)
2. [Architecture Overview](#architecture-overview)
3. [Your First Module](#your-first-module)
4. [XDB (Opt-In Database)](#xdb-opt-in-database)

---

## Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/your-repo/spp-framework.git
   cd spp-framework
   ```

2. **Install Dependencies:**
   Ensure you have PHP 8.2+ and Composer installed.
   ```bash
   composer install
   ```

3. **Start the Development Server:**
   ```bash
   php -S localhost:8000 -t public/
   ```
   Navigate to `http://localhost:8000`. You should see the default application.

---

## Architecture Overview

SPP is designed around a unified kernel (`public/index.php`) and a strictly modular ecosystem.

*   **App Boot Cycle**: Handled by `\SPP\App`. Implements PSR-11 for Dependency Injection.
*   **Routing**: Defined via PHP 8 attributes (e.g., `#[Route('/home')]`) or YAML configuration, parsed by the `spprouter` module.
*   **Views**: Supports BladeOne and Twig for template rendering via `sppview`. SPP encourages zero inline HTML in controllers, relying on strictly separated view partials.
*   **Database (PDO)**: Handled by `sppdb`. Strictly enforces prepared statements to eliminate string-built SQL injection risks.
*   **Security Defaults**: CSRF protection, HttpOnly/Lax Session Cookies, and strict Unserialize protections are enabled by default in the kernel.

---

## Your First Module

Modules encapsulate controllers, views, models, and routes.

**1. Create a module directory:**
```bash
mkdir -p spp/modules/school/hello
```

**2. Define the module (module.yml):**
Create `spp/modules/school/hello/module.yml`:
```yaml
name: hello
version: 1.0.0
description: A simple hello world module
enabled: true
```

**3. Create a Controller:**
Create `spp/modules/school/hello/class.hellocontroller.php`:
```php
<?php
namespace SPPMod\Hello;

use SPPMod\SPPRouter\Attributes\Route;

class HelloController {
    
    #[Route('/hello', method: 'GET')]
    public function index() {
        return "Hello from SPP!";
    }
}
```

**4. Dump Autoloader:**
Because SPP uses a highly optimized `classmap` autoloader to preserve legacy file-naming conventions:
```bash
composer dump-autoload
```

Navigate to `/hello` in your browser to see the result.

---

## XDB (Opt-In Database)

SPP ships with an optional, high-speed XML Database (XDB) engine designed for portability and edge deployments without requiring a separate SQL server daemon.

To use XDB:
1. Ensure the `sppxdb` module is enabled.
2. Initialize an XDB connection:
   ```php
   $db = get_xdb('my_database', 'users');
   
   // Insert
   $db->insert(['name' => 'Alice', 'role' => 'admin']);
   
   // Query
   $results = $db->query("//user[role='admin']");
   ```
   
XDB supports indexing, migrations, and event triggers (e.g., `xdb.before_insert`).
