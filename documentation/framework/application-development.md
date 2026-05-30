# SPP Application Development Guide

This guide explains how to create, configure, and develop applications on the SPP framework.

Read this after:

```text
architecture-and-app-structure.md
booting-and-app-loading.md
```

Those documents explain how SPP is built and how it boots. This document explains how a developer should build an app inside that architecture.

---

## 1. What Is an SPP Application?

An SPP application is a named runtime context.

Each app has:

```text
app name
base URL
source directory
configuration directory
optional custom App class
optional app_init file
optional middleware
modules
services
views
entities
forms
events
runtime paths
```

At request time, the scheduler chooses one active app context:

```php
\SPP\Scheduler::getContext();
```

Then the framework exposes the active app object:

```php
$app = \SPP\App::getApp();
```

Most app-aware development starts from that object.

---

## 2. Recommended App Layout

For new apps, prefer the self-contained app layout:

```text
APP_ROOT/
  src/
    myapp/
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
      MyappApp.php

      events/
      modules/
      resources/
        views/
        themes/
        admin/
        js/
        css/

      serv/
      services/
      commands/
      tests/
      var/
```

This keeps the application portable because config, code, app-local modules, views, and runtime defaults live together under `src/myapp`.

Legacy or generated apps may use the split layout:

```text
APP_ROOT/
  etc/
    apps/
      myapp/
        middleware.yml
        modules.yml
        entities/
        forms/
        pages/
        modsconf/

  src/
    myapp/
      init.php
      serv/
      services/
      resources/
      modules/
```

Both layouts are supported.

---

## 3. Creating a New App Manually

Create the source directory:

```text
src/myapp
```

Create core subdirectories:

```text
src/myapp/etc
src/myapp/events
src/myapp/modules
src/myapp/resources/views
src/myapp/resources/themes
src/myapp/serv
src/myapp/services
src/myapp/tests
src/myapp/var
```

Create:

```text
src/myapp/etc/app.yml
src/myapp/init.php
```

Minimum `app.yml`:

```yaml
base_url: /myapp
table_prefix: myapp_
shared_group: core
etc_path: etc
src_path: src/myapp
modules_path: modules
var_path: var
app_init: init.php
```

For a self-contained app, relative paths like `etc`, `modules`, and `var` are resolved under:

```text
APP_ROOT/src/myapp
```

Create a minimal `init.php`:

```php
<?php
// App-specific bootstrap code for myapp.
```

Once this exists, SPP can discover the app dynamically from:

```text
src/myapp/etc/app.yml
```

---

## 4. Registering an App in Global Settings

Dynamic discovery is enough for many apps, but you can also register the app explicitly in:

```text
spp/etc/global-settings.yml
```

Example:

```yaml
apps:
  myapp:
    base_url: /myapp
    table_prefix: myapp_
    shared_group: core
    etc_path: src/myapp/etc
    src_path: src/myapp
    app_init: init.php
```

Use global registration when:

```text
the app should be visible without dynamic discovery
the app has environment-specific settings
the app uses a split source/config layout
you need to override values from src/myapp/etc/app.yml
```

---

## 5. Choosing an App Layout

Use self-contained layout for most new apps:

```text
src/myapp/etc
src/myapp/modules
src/myapp/resources
```

Use split layout when app config should live in the project-level config tree:

```text
etc/apps/myapp
src/myapp
```

Split layout config:

```yaml
apps:
  myapp:
    base_url: /myapp
    etc_path: etc/apps/myapp
    src_path: src/myapp
    modules_path: modules
    app_init: init.php
```

Resolved paths:

```text
config: APP_ROOT/etc/apps/myapp
source: APP_ROOT/src/myapp
modules: APP_ROOT/src/myapp/modules
```

---

## 6. App Configuration Files

Common app config files:

```text
etc/app.yml             App identity, base URL, paths
etc/settings.yml        App settings
etc/middleware.yml      App middleware stack
etc/modules.yml         App module list
etc/entities/           Entity definitions
etc/forms/              Form definitions
etc/pages/              Page or route definitions
etc/modsconf/           Module-specific config
etc/drishyam.yml        Drishyam/rendering config
etc/locales.yml         Locale config
```

For split-layout apps, replace `src/myapp/etc` with:

```text
etc/apps/myapp
```

SPP resolves the active app config directory with:

```php
\SPP\App::getApp()->getAppConfDir();
```

---

## 7. App Path Configuration

Important app path keys:

```yaml
base_url: /myapp
etc_path: etc
src_path: src/myapp
modules_path: modules
var_path: var
data_path: var/data
log_path: var/logs
cache_path: var/cache
tmp_path: var/tmp
modsconf_path: etc/modsconf
app_init: init.php
```

You usually do not need all of them. A practical new app can start with:

```yaml
base_url: /myapp
etc_path: etc
src_path: src/myapp
modules_path: modules
var_path: var
app_init: init.php
```

Runtime paths can be inspected with:

```php
$app = \SPP\App::getApp();

echo $app->getAppSrcDir();
echo $app->getAppConfDir();
echo $app->getModDir();
echo $app->getModsConfDir();
echo $app->getDataDir();
echo $app->getLogDir();
echo $app->getCacheDir();
echo $app->getTmpDir();
```

---

## 8. Base URL and Context

Every app should define a `base_url`.

Example:

```yaml
base_url: /myapp
```

The scheduler matches request URIs against app base URLs:

```text
/myapp           -> myapp
/myapp/admin     -> myapp
/myapp/api       -> myapp
```

Get the active context:

```php
$context = \SPP\Scheduler::getContext();
```

Get the app URL prefix:

```php
$url = \SPP\App::getBaseUrl('myapp');
```

Inside app code:

```php
$url = \SPP\App::getBaseUrl();
```

---

## 9. App Init File

The app init file is loaded after the app object is created.

Config:

```yaml
app_init: init.php
```

For a self-contained app, this resolves to:

```text
src/myapp/init.php
```

Use `init.php` for:

```text
app-specific constants
lightweight service registration
event registration
module setup
small compatibility helpers
```

Example:

```php
<?php

use SPP\Registry;

Registry::register('__myapp=>booted', true);
```

Keep `init.php` light. Put larger logic in services, modules, or event handlers.

---

## 10. Custom App Class

You can create a custom app class when config and `init.php` are not enough.

Expected namespace:

```text
App\{AppName}\{AppName}App
```

For app `myapp`, create:

```text
src/myapp/MyappApp.php
```

Example:

```php
<?php
namespace App\Myapp;

class MyappApp extends \SPP\App
{
    public function __construct(string $appname = 'myapp', bool $handleerror = true, int $init_level = 4)
    {
        parent::__construct($appname, $handleerror, $init_level);

        $this->singleton(\App\Myapp\Services\SiteService::class);
    }
}
```

Use a custom app class for:

```text
binding app services
custom initialization rules
specialized app-level methods
alternate boot behavior
```

Avoid putting route/controller/view logic directly in the app class.

---

## 11. Services

Use services for reusable app business logic.

Recommended path:

```text
src/myapp/services
```

Example:

```text
src/myapp/services/SiteService.php
```

```php
<?php
namespace App\Myapp\Services;

class SiteService
{
    public function title(): string
    {
        return 'My App';
    }
}
```

Resolve a service:

```php
$service = \SPP\App::getApp()->make(\App\Myapp\Services\SiteService::class);
echo $service->title();
```

Use services for:

```text
business rules
data orchestration
integration logic
application workflows
email/export/report generation
```

---

## 12. Request Handlers and Controllers

SPP apps commonly use:

```text
src/myapp/serv
src/myapp/services
src/myapp/controllers
```

In this repo, `serv/` is commonly used for request-facing handlers and controllers.

Example:

```text
src/myapp/serv/HomeController.php
```

```php
<?php
namespace App\Myapp\Serv;

use App\Myapp\Services\SiteService;

class HomeController
{
    public function index(SiteService $site): string
    {
        return '<h1>' . htmlspecialchars($site->title(), ENT_QUOTES, 'UTF-8') . '</h1>';
    }
}
```

Call through the app container:

```php
echo \SPP\App::getApp()->call([\App\Myapp\Serv\HomeController::class, 'index']);
```

The app's `call()` helper resolves class-typed method parameters from the app container.

---

## 13. Middleware

App middleware lives in:

```text
src/myapp/etc/middleware.yml
```

or for split-layout apps:

```text
etc/apps/myapp/middleware.yml
```

Example:

```yaml
global:
  - App\Myapp\Middleware\RequireLogin
```

Create:

```text
src/myapp/Middleware/RequireLogin.php
```

```php
<?php
namespace App\Myapp\Middleware;

class RequireLogin implements \SPP\Core\MiddlewareInterface
{
    public function handle($request, \Closure $next)
    {
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            return 'Login required';
        }

        return $next($request);
    }
}
```

Global middleware lives in:

```text
spp/etc/middleware.yml
```

Middleware only runs when the entry point uses:

```php
\SPP\Core\MiddlewareKernel::run($destination);
```

---

## 14. Events

Use events to hook into framework lifecycle without modifying core.

Common app event directory:

```text
src/myapp/events
```

Common events:

```text
event_spp_context_enforce
event_spp_route_resolve
event_spp_app_init
event_spp_kernel_boot
event_spp_view_render_theme
```

Example event handler:

```php
<?php
namespace App\Myapp\Events;

class AppInitHandler extends \SPP\EventHandler
{
    public function afterHandler(&$params = [])
    {
        \SPP\Registry::register('__myapp=>event_init_seen', true);
    }
}
```

Event handler registration/discovery depends on SPP's event scanning conventions. Prefer following existing event handler patterns in `events/` and module event directories.

Use events for:

```text
context overrides
route adjustments
rendering overrides
module integration
app lifecycle hooks
cross-cutting behavior
```

---

## 15. App-Local Modules

App-local modules live under:

```text
src/myapp/modules/{module}
```

Example:

```text
src/myapp/modules/reporting
  module.yml
  module.php
  config.yml
  src/
  events/
  api/
  resources/
```

Use app-local modules when the behavior belongs to one app.

Use framework modules under:

```text
spp/modules/spp/{module}
```

when the behavior is reusable across apps.

---

## 16. Module Configuration

Module configuration can live under:

```text
src/myapp/etc/modsconf/{module}/config.yml
```

or split-layout:

```text
etc/apps/myapp/modsconf/{module}/config.yml
```

Example:

```text
src/myapp/etc/modsconf/sppauth/config.yml
```

```yaml
enabled: true
guards:
  default:
    provider: users
```

Read module config through the module APIs where possible rather than parsing YAML manually.

---

## 17. Views and Templates

Recommended view path:

```text
src/myapp/resources/views
```

Example:

```text
src/myapp/resources/views/home.blade.php
```

Depending on active modules, views may be rendered by:

```text
sppview
sppblade
drishyam
lekhak rendering modules
```

Many renderers use:

```php
\SPP\App::getApp()->getAppSrcDir()
```

to locate app resources.

Recommended convention:

```text
resources/views       App templates
resources/themes      Theme files/assets
resources/admin       Admin UI assets
resources/js          App JS
resources/css         App CSS
```

---

## 18. Pages and Routes

Page and route config commonly lives under:

```text
src/myapp/etc/pages
```

or:

```text
etc/apps/myapp/pages
```

Some SPP modules also support:

```text
pages.yml
routes.yml
```

The exact route format depends on the routing/rendering module in use. Use the existing app/module conventions for the renderer you enable.

Typical page config describes:

```text
path
title
view/template
controller/service
permissions
layout/theme
```

---

## 19. Entities

Entity definitions commonly live under:

```text
src/myapp/etc/entities
```

or:

```text
etc/apps/myapp/entities
```

Entity definitions are usually YAML files consumed by `sppentity` and related modules.

Typical entity config includes:

```text
entity name
storage table/collection
fields
labels
relationships
validation rules
permissions
```

Keep entity config close to app config unless the entity is provided by a reusable module.

---

## 20. Forms

Form definitions commonly live under:

```text
src/myapp/etc/forms
```

or:

```text
etc/apps/myapp/forms
```

Form config usually defines:

```text
fields
widgets
labels
validation
layout
submit action
permissions
```

Use forms for admin CRUD, app settings, workflow actions, and structured user input.

---

## 21. Commands

App CLI commands can live under:

```text
src/myapp/commands
```

Base command class:

```text
spp/core/class.command.php
```

Example:

```php
<?php
namespace App\Myapp\Commands;

class ReindexCommand extends \SPP\CLI\Command
{
    protected string $name = 'myapp:reindex';
    protected string $description = 'Rebuild myapp indexes.';

    public function execute(array $args): void
    {
        $this->info('Reindex complete.');
    }
}
```

Command discovery depends on the command manager and registration conventions in the active app/module.

---

## 22. Permissions and Auth

App permissions may be represented in:

```text
src/myapp/permissions
src/myapp/etc/permissions.yml
src/myapp/etc/modsconf/sppauth/config.yml
```

depending on the auth module in use.

For auth-protected apps:

```text
enable sppauth
configure providers/guards
protect routes or middleware
check permissions in services/controllers
```

Keep authorization checks close to the workflow that requires them. Middleware is good for broad protection; service-level checks are better for business rules.

---

## 23. Runtime Data

App runtime paths can be configured:

```yaml
var_path: var
data_path: var/data
log_path: var/logs
cache_path: var/cache
tmp_path: var/tmp
```

Default self-contained runtime paths:

```text
src/myapp/var/data
src/myapp/var/logs
src/myapp/var/cache
src/myapp/var/tmp
```

Project-level runtime paths also exist:

```text
APP_ROOT/var
APP_ROOT/tmp
```

Use runtime directories for generated data, caches, logs, compiled templates, reports, and temporary files.

Do not put source-of-truth app code in runtime directories.

---

## 24. Accessing Configuration

Global settings:

```php
$settings = \SPP\App::getGlobalSettings();
$baseApp = \SPP\App::getGlobalSettings('base_app');
```

App config:

```php
$theme = \SPP\App::getAppConf('theme');
$theme = \SPP\App::getAppConf('theme', 'myapp');
```

Current app directories:

```php
$app = \SPP\App::getApp();
$conf = $app->getAppConfDir();
$src = $app->getAppSrcDir();
```

Use structured config APIs where available. Avoid hardcoding `APP_ROOT/src/myapp` in services unless there is no app-aware API available.

---

## 25. Service Container Usage

Bind services in a custom app class or `init.php`.

```php
$app = \SPP\App::getApp();

$app->singleton(\App\Myapp\Services\SiteService::class);
```

Resolve:

```php
$site = $app->make(\App\Myapp\Services\SiteService::class);
```

Call a controller method with dependency injection:

```php
return $app->call([\App\Myapp\Serv\HomeController::class, 'index']);
```

Prefer constructor or method injection for services. Avoid building large global objects manually in `init.php`.

---

## 26. Using the Registry

Use the registry for runtime state that must be globally available inside the request.

```php
\SPP\Registry::register('__myapp=>status', 'ready');
$status = \SPP\Registry::get('__myapp=>status');
```

Use `__shared=>` only when state should be mirrored for external processes:

```php
\SPP\Registry::register('__shared=>myapp=>worker_status', 'online');
```

Avoid using the registry as a substitute for normal service dependencies.

---

## 27. Development Workflow

A practical development loop:

```text
1. Create src/myapp/etc/app.yml
2. Add init.php
3. Verify context detection with /myapp
4. Add services in src/myapp/services
5. Add request handlers in src/myapp/serv
6. Add views in src/myapp/resources/views
7. Add middleware if needed
8. Add entities/forms/pages config
9. Add app-local modules for larger features
10. Add tests or probe scripts
```

Quick PHP probes:

```bash
php -r "require 'spp/sppinit.php'; echo \SPP\Scheduler::getContext();"
```

```bash
php -r "require 'spp/sppinit.php'; echo \SPP\App::getApp()->getAppConfDir();"
```

```bash
php -r "require 'spp/sppinit.php'; print_r(\SPP\App::getGlobalSettings('apps.myapp'));"
```

---

## 28. Testing an App

Recommended app test directory:

```text
src/myapp/tests
```

Test categories:

```text
bootstrap tests
config path tests
service unit tests
controller/request handler tests
entity/form config validation
middleware behavior tests
module integration tests
```

Minimum bootstrap check:

```php
<?php
require __DIR__ . '/../../../spp/sppinit.php';

$app = new \SPP\App('myapp', false, 0);

assert(is_dir($app->getAppSrcDir()));
assert(is_dir($app->getAppConfDir()));
```

Syntax check changed files:

```bash
php -l src/myapp/init.php
```

---

## 29. Debugging and Troubleshooting

### App does not match URL

Check:

```text
base_url
REQUEST_URI
base_app
src/myapp/etc/app.yml
spp/etc/global-settings.yml
```

Probe:

```php
echo \SPP\Scheduler::getContext();
```

### App config directory is wrong

Probe:

```php
echo \SPP\App::getApp()->getAppConfDir();
```

Fix `etc_path`.

Common values:

```yaml
etc_path: etc
etc_path: src/myapp/etc
etc_path: etc/apps/myapp
```

### App source directory is wrong

Probe:

```php
echo \SPP\App::getApp()->getAppSrcDir();
```

Fix:

```yaml
src_path: src/myapp
```

### Middleware is not running

Check:

```text
spp/etc/middleware.yml
src/myapp/etc/middleware.yml
etc/apps/myapp/middleware.yml
```

Confirm the entry point calls:

```php
\SPP\Core\MiddlewareKernel::run(...)
```

### Service class does not autoload

Check namespace and path.

Expected:

```text
App\Myapp\Services\SiteService
src/myapp/services/SiteService.php
```

Also confirm app name casing in namespace matches the autoloader expectation.

### Module config is not found

Check:

```php
echo \SPP\App::getApp()->getModsConfDir();
```

Expected locations:

```text
src/myapp/etc/modsconf/{module}
etc/apps/myapp/modsconf/{module}
```

---

## 30. Best Practices

Keep app-specific business logic in:

```text
src/myapp/services
src/myapp/serv
src/myapp/modules
```

Keep framework-independent reusable features in:

```text
spp/modules/spp
```

Keep config in:

```text
src/myapp/etc
```

or:

```text
etc/apps/myapp
```

Use `init.php` for light bootstrap only.

Use a custom app class for app-level service binding or specialized lifecycle behavior.

Use services for business logic.

Use middleware for request-wide concerns.

Use events for lifecycle hooks and framework extension.

Use modules for reusable capability packages.

Use app-aware path helpers instead of hardcoded paths.

---

## 31. Minimal Self-Contained App Example

```text
src/demoapp/
  etc/
    app.yml
  init.php
  services/
    GreetingService.php
  serv/
    HomeController.php
  resources/
    views/
      home.blade.php
```

`src/demoapp/etc/app.yml`:

```yaml
base_url: /demoapp
table_prefix: demo_
shared_group: core
etc_path: etc
src_path: src/demoapp
modules_path: modules
var_path: var
app_init: init.php
```

`src/demoapp/init.php`:

```php
<?php
\SPP\Registry::register('__demoapp=>ready', true);
```

`src/demoapp/services/GreetingService.php`:

```php
<?php
namespace App\Demoapp\Services;

class GreetingService
{
    public function message(): string
    {
        return 'Hello from Demo App';
    }
}
```

`src/demoapp/serv/HomeController.php`:

```php
<?php
namespace App\Demoapp\Serv;

use App\Demoapp\Services\GreetingService;

class HomeController
{
    public function index(GreetingService $greeting): string
    {
        return $greeting->message();
    }
}
```

Call it:

```php
echo \SPP\App::getApp()->call([\App\Demoapp\Serv\HomeController::class, 'index']);
```

---

## 32. Minimal Split-Layout App Example

```text
etc/apps/demoapp/
  middleware.yml
  modules.yml
  entities/
  forms/
  pages/

src/demoapp/
  init.php
  services/
  serv/
  resources/
```

`spp/etc/global-settings.yml`:

```yaml
apps:
  demoapp:
    base_url: /demoapp
    table_prefix: demo_
    shared_group: core
    etc_path: etc/apps/demoapp
    src_path: src/demoapp
    modules_path: modules
    app_init: init.php
```

Use this when config needs to be managed separately from app source.

---

## 33. Developer Checklist

Before calling an app ready:

```text
app.yml or global app config exists
base_url is unique
src_path resolves to a real directory
etc_path resolves to a real directory
app_init file exists or is intentionally empty
middleware config is valid
module config is valid
services autoload correctly
controllers/request handlers resolve through App::call()
views are under resources/views
runtime directories are writable if used
permissions/auth are configured for protected areas
bootstrap probes pass
```

Useful probes:

```php
$app = \SPP\App::getApp('demoapp');

echo $app->getAppSrcDir();
echo $app->getAppConfDir();
echo $app->getModDir();
echo \SPP\App::getBaseUrl('demoapp');
```

---

[Back to Framework Index](index.md)
