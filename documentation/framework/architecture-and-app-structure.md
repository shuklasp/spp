# SPP Architecture and App Structure

This document explains the architecture of the SPP framework and the expected structure of applications built on top of it.

It complements:

```text
documentation/framework/booting-and-app-loading.md
```

The booting guide explains how the framework starts. This guide explains what the framework is made of, how its parts relate, and where app files should live.

---

## 1. Architectural Summary

SPP is a modular PHP framework organized around:

```text
Core framework services
Application contexts
Modules
Events
Middleware
Registry state
Dependency injection
Config-driven views, entities, forms, and routes
```

At runtime, SPP behaves like an application orchestrator:

```text
Request
  -> Bootstrap
  -> Scheduler chooses app context
  -> App object resolves app directories and config
  -> Modules and events extend behavior
  -> Middleware wraps request
  -> Dispatcher/renderer/API/controller handles work
  -> Response
```

The active app is not hardcoded. It is chosen by request URI, app configuration, and scheduler events.

---

## 2. Main Directory Layout

At the project root, the repository is organized roughly like this:

```text
APP_ROOT/
  spp/
    core/
    etc/
    modules/
    admin/
    res/

  etc/
    apps/

  src/
    {app}/

  modules/

  resources/
  events/
  var/
  vendor/
  documentation/
```

Each area has a different architectural role.

---

## 3. Framework Core

The core framework lives in:

```text
APP_ROOT/spp/core
```

Important core classes:

```text
class.app.php                 Application object and app directory resolver
class.scheduler.php           Context detection and process switching
class.module.php              Module discovery, config, and loading
class.sppevent.php            Event registration and handler scanning
class.registry.php            Global hierarchical registry and shared state
class.container.php           PSR-11 style dependency injection container
class.middlewarekernel.php    Middleware stack loader
class.pipeline.php            Onion-style middleware executor
class.sppconfig.php           Framework and app settings access
class.sppsession.php          SPP session object
class.spperror.php            Error and exception handling
class.commandmanager.php      CLI command registry/runner
```

The core is intentionally small and extensible. Most feature behavior should live in modules or applications, not in `spp/core`.

---

## 4. Framework Configuration

Framework-level configuration lives in:

```text
APP_ROOT/spp/etc
```

Common files:

```text
global-settings.yml     Defines apps, base app, shared groups, debug settings
middleware.yml          Global middleware stack
modules.yml             Framework-level module list
sppver.yml              Framework version
apps/                   Framework-owned app-specific config
```

The most important file is:

```text
spp/etc/global-settings.yml
```

It defines app contexts:

```yaml
apps:
  lekhak:
    base_url: /lekhak
    table_prefix: lek_
    etc_path: src/lekhak/etc
    src_path: src/lekhak
    app_init: init.php

base_app: lekhak
```

SPP also discovers app definitions from:

```text
APP_ROOT/src/{app}/etc/app.yml
```

---

## 5. Application Contexts

An SPP application is an execution context with its own:

```text
name
base URL
source directory
config directory
module config directory
runtime directories
optional custom App class
optional app_init file
optional app middleware
```

The app context controls which config, modules, views, routes, services, and runtime paths are active.

Examples:

```text
lekhak
autodemo
sppadmin
sppmobile
MyBladeApp
PremiumApp
```

The active context is stored in:

```php
\SPP\Scheduler::getContext()
```

The active app object is available through:

```php
\SPP\App::getApp()
```

---

## 6. Two Supported App Structures

SPP supports two main app layouts.

### 6.1 Root Config + Source App

This layout keeps config under `APP_ROOT/etc/apps/{app}` and code under `APP_ROOT/src/{app}`.

Example:

```text
APP_ROOT/
  etc/
    apps/
      autodemo/
        manifest.yml
        middleware.yml
        modules.yml
        entities/
        forms/
        pages/
        modsconf/
          sppauth/
            config.yml

  src/
    autodemo/
      index.php
      init.php
      modules/
      resources/
      serv/
```

Configuration:

```yaml
apps:
  autodemo:
    base_url: /autodemo
    etc_path: etc/apps/autodemo
    src_path: src/autodemo
    app_init: init.php
```

Use this structure when app configuration should remain centralized under the project-level `etc/apps` tree.

### 6.2 Self-Contained Source App

This layout keeps most app files inside `src/{app}`.

Example:

```text
APP_ROOT/
  src/
    lekhak/
      etc/
        app.yml
        settings.yml
        middleware.yml
        drishyam.yml
      init.php
      LekhakApp.php
      commands/
      comp/
      entities/
      events/
      modules/
      resources/
        views/
        themes/
        admin/
      serv/
      services/
      tests/
      themes/
      ui/
      var/
      workflow/
```

Configuration can live in:

```text
src/lekhak/etc/app.yml
```

Example:

```yaml
base_url: /lekhak
app_init: init.php
etc_path: etc
var_path: var
modules_path: modules
theme: eduxpro
```

During discovery, relative paths like `etc`, `var`, and `modules` are treated as app-local and resolved under:

```text
APP_ROOT/src/lekhak
```

Use this structure for portable apps that should carry their own config, modules, resources, and runtime defaults.

---

## 7. App Directory Responsibilities

Inside a source app, common directories are:

```text
commands/     CLI command classes
comp/         Components or generated UI component code
entities/     Entity classes or entity-related code
etc/          App-local configuration
events/       App event handlers
modules/      App-local modules
resources/    Views, themes, JS, CSS, admin assets
serv/         Service/controller endpoints
services/     Application service classes
tests/        App tests
themes/       Theme adapters or app themes
ui/           UI definitions or SPPUX assets
var/          App-local runtime data/cache/log-like files
workflow/     Workflow definitions or workflow code
```

Not every app needs every directory. SPP loads what exists and what is referenced by config.

---

## 8. Core vs App vs Module

SPP separates responsibilities across three layers.

### Core

Core provides generic lifecycle and infrastructure:

```text
boot
autoload
context detection
registry
events
modules
middleware
container
config
session
errors
```

Core should not contain application-specific business logic.

### App

An app provides a specific site, product, admin interface, CMS, API, or workflow.

An app owns:

```text
base URL
app config
domain-specific services
views
routes/pages
entity definitions
app-level modules
app init code
theme selection
```

### Module

A module provides reusable behavior that can be loaded by one or more apps.

Modules commonly provide:

```text
API handlers
events
services
entity integrations
view/rendering features
auth
database support
admin tools
assets
config
```

The same module can be framework-level or app-local.

---

## 9. Module Structure

Framework modules live under:

```text
APP_ROOT/spp/modules/{bucket}/{module}
```

Examples:

```text
spp/modules/spp/sppauth
spp/modules/spp/sppdb
spp/modules/spp/sppview
spp/modules/spp/drishyam
spp/modules/spp/sppajax
```

App-local modules live under:

```text
APP_ROOT/src/{app}/modules/{module}
```

A module may contain:

```text
module.yml or module.xml
class.{module}.php
module.php
config.yml
events/
src/
api/
resources/
```

Legacy SPP modules often use:

```text
class.{module}.php
int.{name}.php
```

Newer modules may use namespaced classes under:

```text
src/
```

---

## 10. Module Configuration

Module configuration can come from several locations.

Framework-level:

```text
spp/etc/modules.yml
spp/etc/apps/{app}/modsconf/{module}/config.yml
spp/etc/modules/{module}/...
```

App-level:

```text
APP_ROOT/etc/apps/{app}/modsconf/{module}/config.yml
APP_ROOT/src/{app}/etc/modsconf/{module}/config.yml
```

Self-contained modules:

```text
APP_ROOT/src/{app}/modules/{module}/config.yml
```

The module system decides which config applies based on active app context and module loader rules.

---

## 11. Scheduler Architecture

The scheduler is the traffic controller.

Class:

```text
spp/core/class.scheduler.php
```

It owns:

```text
active app context
registered app process objects
context switching
context detection
active app lookup
```

Important methods:

```php
\SPP\Scheduler::detectAndEnforceContext()
\SPP\Scheduler::getContext()
\SPP\Scheduler::setContext($context)
\SPP\Scheduler::regProc($app)
\SPP\Scheduler::getActiveProc()
\SPP\Scheduler::withContext($context, $callback)
```

The scheduler lets SPP run multiple app contexts in one framework installation.

---

## 12. App Object Architecture

The app object is the runtime representation of an application.

Class:

```text
spp/core/class.app.php
```

Responsibilities:

```text
validate app name
read app settings
resolve directories
register app process
hold app container
load modules
initialize app session object
expose app paths
provide app-aware make/call helpers
calculate base URL
```

Important methods:

```php
\SPP\App::getApp()
\SPP\App::getGlobalSettings()
\SPP\App::getAppConf()
\SPP\App::getBaseUrl()
$app->getAppSrcDir()
$app->getAppConfDir()
$app->getModsConfDir()
$app->getModDir()
$app->getDataDir()
$app->getContainer()
$app->make()
$app->call()
```

The app object is intentionally context-aware. Many other framework systems consult it for paths and configuration.

---

## 13. Registry Architecture

The registry is a global hierarchical state store.

Class:

```text
spp/core/class.registry.php
```

It stores values using `=>` separated keys:

```php
\SPP\Registry::register('__apps=>lekhak=>status', \SPP\App::APP_EXEC);
\SPP\Registry::get('__apps=>lekhak=>status');
```

Common use cases:

```text
app status
middleware registration
shared state
module state
runtime lookup tables
class/function registries
```

The registry also provides a global container:

```php
\SPP\Registry::container()
\SPP\Registry::make($className)
```

---

## 14. Container Architecture

The container lives in:

```text
spp/core/class.container.php
```

It is a PSR-11 style dependency injection container.

It supports:

```php
bind($abstract, $concrete)
singleton($abstract, $concrete)
get($id)
has($id)
```

The container can recursively instantiate classes using constructor type hints.

Example:

```php
$container = new \SPP\Core\Container();
$service = $container->get(MyService::class);
```

Each app owns its own container:

```php
$app = \SPP\App::getApp();
$service = $app->make(MyService::class);
```

The registry owns a separate global container:

```php
$service = \SPP\Registry::make(MyService::class);
```

---

## 15. Event Architecture

Events are central to SPP's inversion of control.

Core class:

```text
spp/core/class.sppevent.php
```

Events let apps and modules customize framework behavior without changing core files.

Common lifecycle events:

```text
event_spp_context_enforce
event_spp_route_resolve
event_spp_app_init
event_spp_module_install
event_spp_kernel_boot
```

Event handlers can live in:

```text
APP_ROOT/events
APP_ROOT/src/{app}/events
APP_ROOT/src/{app}/modules/{module}/events
APP_ROOT/spp/modules/{bucket}/{module}/events
```

The app constructor calls:

```php
\SPP\SPPEvent::registerDirs();
\SPP\SPPEvent::scanHandlers();
```

This discovers handlers after app context and module paths are known.

---

## 16. Middleware Architecture

Middleware wraps request execution.

Core files:

```text
spp/core/class.middlewarekernel.php
spp/core/class.pipeline.php
spp/core/int.middlewareinterface.php
```

Middleware sources:

```text
Registry: __middleware=>global
Global:   spp/etc/middleware.yml
App:      {app config dir}/middleware.yml
```

Example global middleware:

```yaml
global:
  - SPP\Core\Middleware\CSRFMiddleware
  - SPP\Middleware\RequestLogger
```

Example app middleware:

```yaml
global:
  - SPP\Middleware\AppLogger
```

Middleware classes implement:

```php
\SPP\Core\MiddlewareInterface
```

with:

```php
public function handle($request, \Closure $next);
```

The pipeline executes middleware in an onion pattern:

```text
request -> middleware A -> middleware B -> destination -> middleware B -> middleware A -> response
```

---

## 17. Config Architecture

SPP is config-first in many systems.

Important config roots:

```text
spp/etc/global-settings.yml
spp/etc/middleware.yml
spp/etc/modules.yml
APP_ROOT/etc/apps/{app}
APP_ROOT/src/{app}/etc
APP_ROOT/src/{app}/etc/app.yml
```

Config can define:

```text
app paths
base URLs
module enablement
module settings
entities
forms
pages/routes
middleware
themes
shared groups
database prefixes
app init files
```

Access patterns:

```php
\SPP\App::getGlobalSettings()
\SPP\App::getGlobalSettings('apps.lekhak.src_path')
\SPP\App::getAppConf('theme', 'lekhak')
\SPP\SPPConfig::get(...)
```

---

## 18. Entity and Form Structure

SPP supports config-driven entities and forms through modules such as `sppentity` and `sppview`.

Common locations:

```text
APP_ROOT/etc/apps/{app}/entities
APP_ROOT/etc/apps/{app}/forms
APP_ROOT/src/{app}/etc/entities
APP_ROOT/src/{app}/etc/forms
APP_ROOT/src/{app}/entities
```

Entity config usually describes:

```text
fields
storage
labels
validation
relationships
permissions
```

Form config usually describes:

```text
fields
widgets
validation rules
layout
submit behavior
```

This lets an app define much of its data and UI behavior without hardcoding every form and model.

---

## 19. View, Page, and Rendering Structure

SPP has multiple rendering paths depending on the active modules.

Common systems:

```text
sppview
sppblade
drishyam
sppux
lekhak rendering modules
```

Common locations:

```text
APP_ROOT/etc/apps/{app}/pages
APP_ROOT/src/{app}/etc/pages
APP_ROOT/src/{app}/resources/views
APP_ROOT/src/{app}/resources/themes
APP_ROOT/resources/{app}/views
APP_ROOT/var/cache/{app}
```

The app source directory is important because renderers often derive view paths from:

```php
\SPP\App::getApp()->getAppSrcDir()
```

The app config directory is important because page and route declarations often derive from:

```php
\SPP\App::getApp()->getAppConfDir()
```

---

## 20. Service and Controller Structure

App service/controller code commonly lives in:

```text
APP_ROOT/src/{app}/serv
APP_ROOT/src/{app}/services
```

The app namespace autoloader supports classes under:

```text
App\{AppName}\Serv\...
App\{AppName}\Services\...
```

Legacy code may also include PHP service files directly from `serv/`.

Use `services/` for reusable domain services and `serv/` for request-facing handlers/controllers when following current repo conventions.

---

## 21. CLI Command Structure

Framework commands live under:

```text
spp/commands
```

App commands may live under:

```text
APP_ROOT/src/{app}/commands
```

The base command class is:

```text
spp/core/class.command.php
```

The command manager is:

```text
spp/core/class.commandmanager.php
```

Commands are useful for code generation, maintenance, migration, exports, and app-specific automation.

---

## 22. Runtime and Generated Files

Runtime files usually live under:

```text
APP_ROOT/var
APP_ROOT/tmp
APP_ROOT/src/{app}/var
```

Examples:

```text
var/logs
var/cache
var/data
var/shared
var/reports
```

Generated or mutable runtime files should not be treated as source-of-truth code unless the specific subsystem documents them as such.

---

## 23. Shared State and Polyglot Structure

SPP supports shared state for cross-language tooling through:

```text
APP_ROOT/var/shared
```

The registry can mirror shared values when keys use the `__shared=>` namespace.

Example:

```php
\SPP\Registry::register('__shared=>worker=>status', 'online');
```

This supports PHP plus external workers or tools in other languages.

---

## 24. App URL Structure

Every app should have a `base_url`.

Example:

```yaml
apps:
  lekhak:
    base_url: /lekhak
```

SPP uses this for:

```text
context matching
base URL generation
routing
asset URL generation
API links
admin navigation
```

Use:

```php
\SPP\App::getBaseUrl('lekhak')
```

to get an app-aware URL prefix.

---

## 25. Custom App Classes

An app can provide a custom app class.

Expected namespace pattern:

```text
App\{AppName}\{AppName}App
```

For app `lekhak`, the class is:

```php
App\Lekhak\LekhakApp
```

SPP checks for this class during boot. If it exists, SPP uses it instead of the base `\SPP\App`.

Use a custom app class when the app needs specialized initialization beyond config, modules, and `app_init`.

---

## 26. App Init Files

Each app can define:

```yaml
app_init: init.php
```

If the value is a simple filename, it is resolved from the app source directory:

```text
APP_ROOT/src/{app}/init.php
```

If the value contains a path separator, it is resolved from:

```text
APP_ROOT
```

Use `app_init` for app-level bootstrap that is too specific for global core initialization but does not need a custom App subclass.

---

## 27. Recommended App Structure

For new apps, prefer the self-contained layout:

```text
src/{app}/
  etc/
    app.yml
    middleware.yml
    settings.yml
    modules.yml
    entities/
    forms/
    pages/
    modsconf/
  init.php
  {AppName}App.php
  events/
  modules/
  resources/
    views/
    themes/
    admin/
  serv/
  services/
  commands/
  tests/
  var/
```

This keeps the app portable and easier to reason about.

Use root-level config under `etc/apps/{app}` when:

```text
the app is legacy
operations prefer all app config in one root
the app shares source code but has environment-specific config
the app is generated by older SPP tools
```

---

## 28. Recommended Module Structure

For new modules, prefer:

```text
modules/{module}/
  module.yml
  module.php
  config.yml
  src/
  events/
  api/
  resources/
  tests/
```

For app-local modules:

```text
src/{app}/modules/{module}/
  module.yml
  module.php
  config.yml
  src/
  events/
  api/
  resources/
```

Keep reusable framework-level modules under `spp/modules/spp` when they are generic. Keep business-specific modules under `src/{app}/modules`.

---

## 29. Architecture Boundaries

Good boundaries keep SPP maintainable.

Core should contain:

```text
lifecycle primitives
autoloading
context detection
generic module/event/middleware infrastructure
generic config/registry/container/session/error logic
```

Modules should contain:

```text
reusable features
domain-neutral integrations
rendering engines
auth/db/entity/view systems
API helpers
admin tools
```

Apps should contain:

```text
business workflows
site-specific services
app-specific views and themes
app-local modules
app config
domain entities
controllers
```

Runtime should contain:

```text
logs
cache
generated reports
temporary data
shared state
compiled templates
```

---

## 30. Example: Root Config App

For `autodemo`:

```text
etc/apps/autodemo/
  manifest.yml
  middleware.yml
  modules.yml
  entities/
  forms/
  pages/
  modsconf/

src/autodemo/
  init.php
  modules/
  resources/
```

Global settings:

```yaml
apps:
  autodemo:
    base_url: /autodemo
    etc_path: etc/apps/autodemo
    src_path: src/autodemo
    app_init: init.php
```

Resolved paths:

```text
source: APP_ROOT/src/autodemo
config: APP_ROOT/etc/apps/autodemo
modules: APP_ROOT/src/autodemo/modules
init:   APP_ROOT/src/autodemo/init.php
```

---

## 31. Example: Self-Contained App

For `lekhak`:

```text
src/lekhak/
  etc/
    app.yml
  init.php
  LekhakApp.php
  modules/
  resources/
  serv/
  services/
  events/
  var/
```

App config:

```yaml
base_url: /lekhak
etc_path: etc
var_path: var
modules_path: modules
app_init: init.php
```

Resolved paths:

```text
source: APP_ROOT/src/lekhak
config: APP_ROOT/src/lekhak/etc
modules: APP_ROOT/src/lekhak/modules
var:    APP_ROOT/src/lekhak/var
init:   APP_ROOT/src/lekhak/init.php
```

---

## 32. Mental Model

Think of SPP as a layered system:

```text
Core runtime
  owns boot, context, registry, events, modules, middleware, config

Modules
  add reusable capabilities to the runtime

Apps
  assemble modules, config, views, services, and domain logic

Entry points
  choose how a request is dispatched after boot
```

The app context is the key that ties everything together. Once the scheduler knows the context, every other system can resolve the right config, source directory, modules, events, views, and runtime paths.

---

## 33. Quick Checklist for a New App

Create:

```text
src/{app}/etc/app.yml
src/{app}/init.php
src/{app}/resources/views
src/{app}/serv
src/{app}/services
src/{app}/modules
```

Minimum `app.yml`:

```yaml
base_url: /{app}
etc_path: etc
src_path: src/{app}
modules_path: modules
app_init: init.php
```

Optional additions:

```text
middleware.yml
settings.yml
modules.yml
entities/
forms/
pages/
modsconf/
events/
commands/
tests/
```

Then verify:

```php
\SPP\Scheduler::getContext()
\SPP\App::getApp()->getAppSrcDir()
\SPP\App::getApp()->getAppConfDir()
\SPP\App::getApp()->getModDir()
\SPP\App::getBaseUrl()
```

---

[Back to Framework Index](index.md)
