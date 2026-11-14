# 03. Modular Architecture (Modularity 2.0)

SPP is built around a robust, flexible, enterprise-grade modular architecture. Every major subsystem in the framework — from database persistence (`sppdb`) and authentication (`sppauth`) to reactive UI rendering (`drishyam`) and test orchestration (`parikshak`) — is packaged as a self-contained **Module**.

---

## 1. What is an SPP Module?

In SPP, a module is an autonomous, reusable package that encapsulates domain logic, database tables, dependency injection services, event listeners, and public web assets.

A modern SPP module consists of:
1. **`module.yml`**: The declarative manifest declaring the module's name, version, namespace, dependencies, service provider, and asset mappings.
2. **`db.yml`**: Declarative database schema definitions synced automatically without writing manual SQL migrations.
3. **`events.yml`**: Zero-boot event listeners that are cached globally and loaded on-demand only when events fire.
4. **`src/<Name>ServiceProvider.php`**: The Dependency Injection container service provider implementing `\SPP\Core\Interfaces\ServiceProviderInterface`.
5. **`modinit.php`**: Runtime bootstrap logic executed during application boot when the module is active.
6. **`install.php` & `uninstall.php`**: Idempotent lifecycle routines executed during installation or removal.
7. **`public/`**: Web-accessible assets (CSS, JS, images) routed securely through `/sppasset/<modname>/main/`.

### Module Scopes & Locations
Modules can reside in four standardized tiers:
- **SPP Kernel (`spp/modules/spp/`)**: Foundational, compulsory modules (e.g. `sppdb`, `sppauth`, `drishyam`, `spprouter`).
- **Core Optional (`spp/modules/optional/`)**: High-performance optional enterprise modules (e.g. `parikshak`, `sppqueue`, `sppai`, `sppxdb`, `sppdeploy`, `sppdoc`).
- **Community Contrib (`spp/modules/contrib/`)**: Third-party or open-source community extensions.
- **Application (`src/<AppName>/modules/`)**: Application-specific business modules scoped directly to your app.

---

## 2. Declarative Module Manifest (`module.yml`)

Every module defines its identity and contracts in `module.yml`:

```yaml
module:
  name: blog
  version: '1.0.0'
  type: module
  compulsory: false
  publicname: 'Blog Engine'
  publicdesc: 'Publishing engine with articles, tags, and comments.'
  namespace: 'AppMod\School1\Blog'
  provider: 'AppMod\School1\Blog\BlogServiceProvider'
  dependencies:
    sppdb: ^1.2
    spprouter: ^1.0
  assets:
    main: /public
```

### Key Manifest Directives
- **`name` / `id`**: The unique internal identifier for the module (lowercase alphanumeric).
- **`version`**: Semantic version string (`1.0.0`).
- **`compulsory`**: When set to `true`, the framework strictly forbids deactivating or uninstalling this module to prevent application corruption.
- **`namespace`**: The PSR-4 namespace mapped automatically to the module's `src/` directory.
- **`provider`**: The fully qualified class name of the module's `ServiceProvider`.
- **`dependencies`**: Semantic version constraints for required upstream modules. The framework compiler performs topological sorting (DAG) to ensure prerequisites are booted first.
- **`assets`**: Maps URL slugs to physical asset folders. For example, `main: /public` allows client browsers to request `/sppasset/blog/main/style.css`.

---

## 3. Declarative Database Schemas (`db.yml`)

Instead of writing repetitive, error-prone SQL DDL scripts across different database engines (MySQL, PostgreSQL, SQLite), SPP uses declarative schema definitions in `db.yml`:

```yaml
# db.yml
tables:
  blog_articles:
    columns:
      id: "INTEGER PRIMARY KEY AUTOINCREMENT"
      title: "VARCHAR(255) NOT NULL"
      slug: "VARCHAR(255) UNIQUE NOT NULL"
      content: "TEXT NOT NULL"
      author_id: "INTEGER NOT NULL"
      published_at: "DATETIME"
      created_at: "DATETIME DEFAULT CURRENT_TIMESTAMP"
```

When you install or update a module (`php spp.php module:update blog`), `ModuleInstaller` inspects the active database, compares existing columns, and applies non-destructive incremental updates automatically.

---

## 4. The Service Provider (`ServiceProviderInterface`)

Service providers are the central point of integration with the SPP Dependency Injection container. They must implement `\SPP\Core\Interfaces\ServiceProviderInterface`:

```php
<?php

namespace AppMod\School1\Blog;

use SPP\Core\Interfaces\ServiceProviderInterface;
use SPP\App;

class BlogServiceProvider implements ServiceProviderInterface
{
    /**
     * Register services into the DI container.
     */
    public function register(): void
    {
        $container = App::getContainer();
        
        $container->singleton(ArticleRepository::class, function () {
            return new ArticleRepository();
        });
    }

    /**
     * Bootstrap services, register route bindings, or initialize hooks.
     */
    public function boot(): void
    {
        // Executed immediately after all providers have registered
    }

    /**
     * Optional update hook executed during module upgrades.
     */
    public function update(string $fromVersion, string $toVersion): void
    {
        // Custom data migrations or cache purges
    }
}
```

---

## 5. Zero-Boot Event Discovery (`events.yml`)

SPP modules avoid runtime overhead by declaring event subscribers in `events.yml`:

```yaml
# events.yml
article.published:
  - AppMod\School1\Blog\Listeners\NotifySubscribersListener::handle
user.deleted:
  - AppMod\School1\Blog\Listeners\CleanupAuthorArticlesListener::handle
```

The framework caches these event mappings globally. When `\SPP\SPPEvent::fireEvent('article.published', $data)` is invoked, SPP dynamically boots the module and executes the listener with zero performance penalty on standard requests.

---

## 6. Centralized Configuration Management (`ModuleConfigManager`)

Module configurations are managed by `\SPP\Core\ModuleConfigManager`. The framework provides dedicated, schema-backed configuration isolation:

```php
use SPP\Core\ModuleConfigManager;

// 1. Get a configuration setting for a module
$perPage = ModuleConfigManager::getConfig('posts_per_page', 'blog');

// 2. Set a configuration setting
ModuleConfigManager::setConfig('posts_per_page', 25, 'blog');

// 3. Retrieve application-specific overridden configuration
$config = ModuleConfigManager::getAppConfig('blog', 'school1');

// 4. Save application-specific configuration
ModuleConfigManager::saveAppConfig('blog', 'school1', [
    'posts_per_page' => 50,
    'enable_comments' => true
]);
```

All configurations are stored cleanly under `etc/modsconf/<modname>/config.yml` or within application contexts (`src/<app>/etc/modsconf/<modname>/config.yml`).

---

## 7. Working with Modules via CLI

The SPP command-line interface provides comprehensive tools to scaffold, inspect, and manage modules:

### 1. Scaffold a New Module
```bash
# Scaffold an application module for the active app
php spp.php make:module blog

# Scaffold an optional framework module
php spp.php make:module analytics --scope=optional
```

### 2. Tabulate and Inspect Modules
```bash
# List all active and available modules
php spp.php module:list

# Filter by operational status
php spp.php module:list --status=active

# Filter by keyword
php spp.php module:list --filter=db

# Export catalog as JSON
php spp.php module:list --json
```

### 3. Install, Update, and Synchronize
```bash
# Install a module (executes db.yml, install.php, and registers tracking)
php spp.php module:install blog

# Update a module (syncs schema updates and runs upgrade hooks)
php spp.php module:update blog

# Update and synchronize all active modules across the system
php spp.php module:update --all

# Enable or disable a module
php spp.php module:enable blog
php spp.php module:disable blog
```

### 4. Audit, Test, and Package Modules
```bash
# Audit module health, dependencies, and database schema drift
php spp.php module:doctor blog

# Automatically synchronize missing db.yml tables
php spp.php module:doctor blog --fix

# Run isolated Parikshak automated tests for a module
php spp.php test:module blog

# Package a module into a distributable archive
php spp.php module:pack blog --format=zip

# Inspect and update module settings
php spp.php module:setting:list blog
php spp.php module:setting:update blog posts_per_page 20
```

---

## 8. Verifying Module State in Code

In your controllers, models, or services, verify module state using `\SPP\Module`:

```php
// Check if a module is enabled and loaded
if (\SPP\Module::isEnabled('blog')) {
    $article = \AppMod\School1\Blog\Article::find(1);
}

// Retrieve an instantiated module object
$blogModule = \SPP\Module::getModule('blog');
$version = $blogModule->Version;
$modDir = \SPP\Module::getModuleDir('blog');
```

---

[**Next: Data Modeling with YAML Entities**](04_data_models.md)
