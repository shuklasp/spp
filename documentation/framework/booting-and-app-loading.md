# SPP Framework Booting and App Loading

This document explains the full SPP framework boot sequence, from the first entry file through context detection, application creation, module loading, middleware booting, and request execution.

The central bootstrap file is:

```text
spp/sppinit.php
```

Most web entry points eventually include this file. Once included, the framework initializes constants, autoloading, sessions, scheduler context, the active application object, events, modules, and runtime helpers.

---

## 1. High-Level Boot Flow

The complete runtime path is:

```text
Entry file
  -> spp/sppinit.php
    -> Define framework constants
    -> Load Composer autoloader
    -> Register SPP autoloaders
    -> Register compatibility aliases
    -> Configure debug/error handling
    -> Start or prepare session
    -> Scheduler::detectAndEnforceContext()
    -> Instantiate active application
      -> App::__construct()
        -> Load global app settings
        -> Resolve app directories
        -> Register app process
        -> Fire app init event
        -> Load modules
        -> Initialize app session
        -> Register and scan event handlers
    -> Load app_init file if configured
    -> Register final framework events/helpers
    -> Request dispatch, middleware, routing, rendering
```

The most important classes involved are:

```text
spp/sppinit.php
spp/core/class.app.php
spp/core/class.scheduler.php
spp/core/class.middlewarekernel.php
spp/core/class.pipeline.php
spp/core/class.module.php
spp/core/class.sppevent.php
spp/core/class.registry.php
spp/core/class.container.php
```

---

## 2. Entry Point Responsibility

An SPP request usually starts from one of the public PHP entry files, for example:

```text
index.php
spp/admin/index.php
spp/admin/api.php
src/{app}/index.php
src/{app}/api.php
```

The entry file is responsible for including the framework bootstrap:

```php
require_once __DIR__ . '/spp/sppinit.php';
```

or an equivalent relative path.

After `sppinit.php` completes, the entry file can dispatch a page, API service, admin controller, renderer, or middleware pipeline.

---

## 3. Single-Boot Guard

`sppinit.php` begins with:

```php
if (!defined('SPP_VER')) {
    ...
}
```

This prevents duplicate initialization. If another file includes `sppinit.php` later in the same request, constants, autoloaders, and application objects are not rebuilt.

---

## 4. Debug Mode and Version Resolution

Before the framework is fully loaded, `sppinit.php` reads:

```text
spp/etc/global-settings.yml
```

It uses a lightweight text check to determine whether debug mode should be enabled:

```yaml
settings:
  debug: true
```

Then it resolves the framework version from:

```text
spp/etc/sppver.yml
```

and defines:

```php
SPP_VER
SPP_DEBUG
```

These are needed early because later exception handling depends on them.

---

## 5. Core Constants

The bootstrap defines the directory and URI constants used throughout the framework.

Key constants:

```text
SPP_BASE_DIR     Path to the framework directory, normally APP_ROOT/spp
SPP_APP_DIR      Path to the application root, normally APP_ROOT
SPP_ROOT_DIR     Parent of APP_ROOT
APP_BASE_DIR     Alias-style root for the current app tree
APP_BASE_URI     Web URI prefix for the installation
SPP_CORE_DIR     spp/core
SPP_MODULES_DIR  spp/modules
SPP_ETC_DIR      spp/etc
APP_ETC_DIR      APP_ROOT/etc/apps
SPP_LOG_DIR      APP_ROOT/var/logs
```

Example:

```text
APP_ROOT
  spp/
    core/
    etc/
    modules/
  etc/
    apps/
  src/
  var/
```

The framework uses `/` as `SPP_DS` even on Windows so path strings are portable across web and CLI contexts.

---

## 6. Composer Autoloading

After constants are ready, SPP loads Composer from:

```text
APP_ROOT/vendor/autoload.php
```

This makes third-party libraries available, including:

```text
symfony/yaml
psr/container
eftec/bladeone
```

SPP's own autoloaders are then layered on top of Composer.

---

## 7. Framework Autoloaders

SPP registers multiple autoloaders. Each one handles a different naming convention.

### 7.1 Core Class Autoloader

Core classes are loaded by lowercasing the class name and looking for files like:

```text
spp/core/class.{class}.php
spp/core/int.{class}.php
spp/core/interface.{class}.php
spp/core/middleware/class.{class}.php
```

Examples:

```text
\SPP\App                         -> spp/core/class.app.php
\SPP\Scheduler                   -> spp/core/class.scheduler.php
\SPP\Core\Container              -> spp/core/class.container.php
\SPP\Core\Middleware\CSRFMiddleware -> spp/core/middleware/class.csrfmiddleware.php
```

### 7.2 Exception Autoloader

Classes ending in `Exception` load:

```text
spp/core/class.sppexception.php
spp/core/sppsystemexceptions.php
```

If a named exception class does not exist after loading framework exceptions, it falls back to an alias of:

```text
\SPP\SPPException
```

This lets old code keep throwing specific exception names while still preserving real framework exception classes when they are declared.

### 7.3 SPPMod Autoloader

Module classes under:

```text
SPPMod\...
```

are resolved from framework and app module directories.

The module autoloader checks common module buckets such as:

```text
spp/modules/spp/{module}
spp/modules/school/{module}
APP_ROOT/src/{active-app}/modules/{module}
```

It supports legacy files such as:

```text
class.{name}.php
int.{name}.php
```

and PSR-style files under:

```text
src/
```

### 7.4 App Namespace Autoloader

Application classes under:

```text
App\{AppName}\...
```

are resolved from the app's configured `src_path`.

For example:

```text
App\Lekhak\Serv\AdminController
```

resolves under:

```text
APP_ROOT/src/lekhak/serv/AdminController.php
```

depending on the app's configured source path.

---

## 8. Compatibility Aliases

SPP creates aliases for older class/interface names:

```text
\SPP\CacheInterface      -> \SPP\Core\CacheInterface
\SPP\MiddlewareInterface -> \SPP\Core\MiddlewareInterface
\SPP\iModule             -> \SPP\Core\iModule
```

This keeps older modules compatible while newer code can use the `SPP\Core` namespace.

---

## 9. Error and Debug Handling

When `SPP_DEBUG` is enabled and `\SPP\SPPError` exists, the framework installs:

```php
set_exception_handler('\SPP\SPPError::exceptionHandler');
```

It also starts debug tracking through:

```php
\SPP\Core\Debug::start();
```

Debug mode can also cause extra trace files to be written under:

```text
APP_ROOT/var/logs
```

---

## 10. Session Initialization

In web mode, SPP starts a PHP session if one is not already active.

If the Redis module is enabled and Redis is available, SPP installs:

```php
\SPP\Core\RedisSessionHandler
```

as the session save handler.

In CLI mode, SPP does not start a browser session. Instead it ensures:

```php
$_SESSION = [];
```

exists so framework code that expects session storage does not fail.

---

## 11. Context Detection

The active app is determined by:

```php
\SPP\Scheduler::detectAndEnforceContext();
```

This method lives in:

```text
spp/core/class.scheduler.php
```

### 11.1 URI Normalization

The scheduler reads:

```php
$_SERVER['REQUEST_URI']
```

and removes query string data. It also normalizes the URI when SPP is installed in a subdirectory.

### 11.2 App Definitions

The scheduler loads app definitions from:

```php
\SPP\App::getGlobalSettings('apps')
```

These come from:

```text
spp/etc/global-settings.yml
```

Example:

```yaml
apps:
  lekhak:
    base_url: /lekhak
    etc_path: src/lekhak/etc
    src_path: src/lekhak
    app_init: init.php
```

### 11.3 Dynamic App Discovery

The scheduler also scans:

```text
APP_ROOT/src/*/etc/app.yml
```

Any folder with an `etc/app.yml` can become a self-contained app.

Example:

```text
src/lekhak/etc/app.yml
src/spp_docs/etc/app.yml
```

Dynamic app configuration is merged with global configuration.

### 11.4 Context Events

The scheduler registers and fires:

```text
event_spp_context_enforce
event_spp_route_resolve
```

These events allow modules to override or adjust context detection.

### 11.5 Base URL Matching

Each app has a `base_url`. The scheduler compares the normalized request URI to each app's `base_url`.

Example:

```text
/lekhak/admin     -> lekhak
/autodemo         -> autodemo
/spp/docs         -> spp_docs
```

If no app matches, it falls back to:

```yaml
base_app: lekhak
```

or finally:

```text
default
```

The detected app name is stored as the active scheduler context.

---

## 12. App Class Selection

After context detection, `sppinit.php` decides which application class to instantiate.

It builds a possible custom app class:

```php
$appClass = "\\App\\" . ucfirst($context) . "\\" . ucfirst($context) . "App";
```

Then it chooses:

```text
If app type is drupal:
  new \SPP\DrupalApp(...)

Else if custom app class exists:
  new App\{Context}\{Context}App(...)

Else:
  new \SPP\App(...)
```

This means apps can either use the base framework app or provide their own subclass.

---

## 13. App Constructor

The base application class is:

```text
spp/core/class.app.php
```

The constructor signature is:

```php
public function __construct(
    string $appname = '',
    bool $handleerror = true,
    int $init_level = 4
)
```

### 13.1 App Name Validation

If no app name is passed, SPP uses:

```text
default
```

App names must match:

```text
a-z A-Z 0-9 _ -
```

This prevents unsafe names from being used in path and registry operations.

### 13.2 Container Creation

Each app gets its own container:

```php
$this->container = new \SPP\Core\Container();
```

The app exposes:

```php
$app->getContainer()
$app->make(...)
$app->bind(...)
$app->singleton(...)
$app->call(...)
```

The global registry also has its own container via:

```php
\SPP\Registry::container()
```

### 13.3 Global Settings

The app loads global settings through:

```php
self::getGlobalSettings()
```

This method parses:

```text
spp/etc/global-settings.yml
```

and discovers dynamic apps from:

```text
src/*/etc/app.yml
```

### 13.4 App Type

The app type comes from:

```yaml
apps:
  appname:
    type: native
```

If no type is configured, the default is:

```text
native
```

Special types, such as `drupal`, can alter app selection earlier in `sppinit.php`.

---

## 14. Init Levels

The `init_level` controls how much of the app lifecycle runs.

```text
0  Minimal app object and directories
1  Register app process with Scheduler
2  Load modules
3  Initialize SPP session object
4  Attach SPPError handling object
```

The constructor checks levels progressively:

```text
init_level >= 1  Scheduler registration
init_level >= 2  Module loading
init_level >= 3  Session setup
init_level >= 4  Error object setup
```

Lightweight operations can instantiate an app with a lower init level to avoid loading modules or sessions.

---

## 15. App Directory Resolution

During construction, the app calls:

```php
$this->initializeDirs();
```

This resolves:

```text
src dir
config dir
module dir
data dir
log dir
cache dir
tmp dir
modsconf dir
```

Important methods:

```php
$app->getAppSrcDir()
$app->getAppConfDir()
$app->getModsConfDir()
$app->getModDir()
$app->getDataDir()
$app->getLogDir()
$app->getCacheDir()
$app->getTmpDir()
```

### 15.1 Source Directory

If configured:

```yaml
src_path: src/lekhak
```

then:

```text
APP_ROOT/src/lekhak
```

is used.

If not configured, the fallback is:

```text
APP_ROOT/src/{appname}
```

### 15.2 Config Directory

If configured:

```yaml
etc_path: src/lekhak/etc
```

then SPP resolves it from `APP_ROOT`.

If configured:

```yaml
etc_path: etc/apps/autodemo
```

then SPP also resolves it from `APP_ROOT`.

If `etc_path` is app-local, SPP resolves it from the app source directory.

If no `etc_path` exists but `src_path` exists, SPP uses:

```text
{app src dir}/etc
```

If neither is configured, SPP uses:

```text
APP_ROOT/etc/apps/{appname}
```

### 15.3 Module Directory

The app module directory comes from:

```yaml
modules_path: modules
```

For self-contained apps, this resolves to:

```text
APP_ROOT/src/{app}/modules
```

For rooted paths such as `src/lekhak/modules`, it resolves from `APP_ROOT`.

### 15.4 Runtime Directories

Runtime paths default under the app source directory:

```text
var/data
var/logs
var/cache
var/tmp
```

If `var_path` is configured, it is used as the base for those defaults.

---

## 16. App Registration

After directory setup, the app registers itself in two places.

### 16.1 Static App Instance Registry

```php
self::$instances[$appname] = $this;
```

This supports:

```php
\SPP\App::getApp($appname)
```

### 16.2 Global Registry Status

```php
\SPP\Registry::register('__apps=>' . $appname . '=>status', self::APP_EXEC);
```

The app status can be:

```text
APP_EXEC
APP_WAITING
APP_STOPPED
APP_ERROR
```

### 16.3 Scheduler Process Registry

When `init_level >= 1`:

```php
\SPP\Scheduler::regProc($this);
```

The scheduler can then switch between app processes using:

```php
\SPP\Scheduler::setContext($appname)
```

---

## 17. App Initialization Event

The app constructor registers and fires:

```text
event_spp_app_init
```

This gives modules and handlers a chance to react to app creation.

The default handler does nothing:

```php
\SPP\SPPEvent::fireEvent('event_spp_app_init', $this, function($app) {
    // Default init: do nothing
});
```

---

## 18. Module Loading

When `init_level >= 2`, the app calls:

```php
$this->loadModules();
```

This method:

1. Saves the old scheduler context.
2. Switches context to the app being loaded.
3. Calls:

```php
\SPP\Module::loadAllModules();
```

4. Restores the old scheduler context.
5. Marks modules as loaded.

Module loading uses app and framework configuration from locations such as:

```text
spp/etc/modules.yml
spp/etc/apps/{app}/modsconf
APP_ROOT/etc/apps/{app}/modsconf
APP_ROOT/src/{app}/modules
spp/modules
```

The exact module resolver lives in:

```text
spp/core/class.module.php
```

---

## 19. App Session Object

When `init_level >= 3`, the app ensures an SPP session object exists.

The session key is:

```text
__{appname}_sppsession
```

For example:

```text
__lekhak_sppsession
```

The object is serialized into `$_SESSION`.

Useful methods:

```php
\SPP\App::initSession()
\SPP\App::killSession()
\SPP\App::getSessionName()
```

---

## 20. Event Directory Registration

At the end of app construction:

```php
\SPP\SPPEvent::registerDirs();
\SPP\SPPEvent::scanHandlers();
```

This lets the framework discover event handlers from framework, app, and module locations.

Event handler discovery allows modules and apps to override framework lifecycle behavior without editing core files.

---

## 21. App Init File

After the app object is created, `sppinit.php` checks:

```yaml
apps:
  appname:
    app_init: init.php
```

If `app_init` contains a path separator, SPP resolves it from `APP_ROOT`.

If it is a simple filename, SPP resolves it from the app source directory:

```text
APP_ROOT/{src_path}/{app_init}
```

Example:

```yaml
apps:
  lekhak:
    src_path: src/lekhak
    app_init: init.php
```

loads:

```text
APP_ROOT/src/lekhak/init.php
```

This is the app's final custom bootstrap hook.

---

## 22. Middleware Booting

Middleware is handled by:

```text
spp/core/class.middlewarekernel.php
spp/core/class.pipeline.php
```

Middleware is not necessarily executed during `sppinit.php` itself. It runs when the request entry point calls:

```php
\SPP\Core\MiddlewareKernel::run($destination);
```

### 22.1 Middleware Sources

The kernel loads middleware from three places.

#### Registry

```php
\SPP\Registry::get('__middleware=>global')
```

#### Global Config

```text
spp/etc/middleware.yml
```

Example:

```yaml
global:
  - SPP\Core\Middleware\CSRFMiddleware
  - SPP\Middleware\RequestLogger
```

#### App Config

```text
{app config dir}/middleware.yml
```

Example:

```text
APP_ROOT/etc/apps/autodemo/middleware.yml
```

```yaml
global:
  - SPP\Middleware\AppLogger
```

### 22.2 Pipeline Execution

The kernel sends:

```php
$_REQUEST
```

through:

```php
(new Pipeline())
    ->send($_REQUEST)
    ->through($middleware)
    ->then($destination);
```

The pipeline uses an onion model:

```text
Request
  -> Middleware 1
    -> Middleware 2
      -> Destination
    <- Middleware 2
  <- Middleware 1
Response
```

Each middleware implements:

```php
\SPP\Core\MiddlewareInterface
```

with:

```php
public function handle($request, \Closure $next);
```

---

## 23. Registry Role During Boot

The registry provides shared runtime state.

Core uses include:

```text
App status
Middleware registration
Shared polyglot state
Context-aware values
Class and function registries
```

The registry is implemented in:

```text
spp/core/class.registry.php
```

It also owns a global service container:

```php
\SPP\Registry::container()
```

and service resolver:

```php
\SPP\Registry::make($className)
```

---

## 24. Service Container Role During Boot

SPP has a PSR-11 style container in:

```text
spp/core/class.container.php
```

It supports:

```php
bind()
singleton()
get()
has()
```

It can auto-resolve class dependencies by reading constructor type hints.

App objects also provide convenience methods:

```php
$app->make()
$app->call()
$app->bind()
$app->singleton()
```

This is used by app code and can also support middleware, controllers, and service classes.

---

## 25. Request Lifecycle After Boot

Once `sppinit.php` finishes, the active app and framework services are available.

The rest of the request depends on the entry point:

```text
Admin page       -> admin controller/rendering
Admin API        -> action router/service handler
App page         -> route/view/page renderer
App API          -> service/API dispatch
Static resource  -> resource controller or direct serving
CLI command      -> command manager
```

Common later-stage components include:

```text
spp/core/class.middlewarekernel.php
spp/core/class.pipeline.php
spp/modules/spp/sppview
spp/modules/spp/drishyam
spp/modules/spp/sppajax
spp/core/class.commandmanager.php
```

---

## 26. Example: Request to `/autodemo`

Given:

```yaml
apps:
  autodemo:
    base_url: /autodemo
    etc_path: etc/apps/autodemo
    src_path: src/autodemo
    app_init: init.php
```

Boot proceeds as:

```text
Request URI: /autodemo
  -> Scheduler matches base_url /autodemo
  -> Active context becomes autodemo
  -> SPP creates \SPP\App('autodemo')
  -> Source dir becomes APP_ROOT/src/autodemo
  -> Config dir becomes APP_ROOT/etc/apps/autodemo
  -> App init file becomes APP_ROOT/src/autodemo/init.php
  -> Global middleware loads from spp/etc/middleware.yml
  -> App middleware loads from etc/apps/autodemo/middleware.yml
```

The final middleware stack is:

```text
SPP\Core\Middleware\CSRFMiddleware
SPP\Middleware\RequestLogger
SPP\Middleware\AppLogger
```

---

## 27. Example: Request to `/lekhak`

Given:

```yaml
apps:
  lekhak:
    base_url: /lekhak
    etc_path: src/lekhak/etc
    src_path: src/lekhak
    app_init: init.php
```

Boot proceeds as:

```text
Request URI: /lekhak
  -> Scheduler matches base_url /lekhak
  -> Active context becomes lekhak
  -> SPP creates \SPP\App('lekhak')
  -> Source dir becomes APP_ROOT/src/lekhak
  -> Config dir becomes APP_ROOT/src/lekhak/etc
  -> App init file becomes APP_ROOT/src/lekhak/init.php
```

Because `lekhak` is self-contained under `src/lekhak`, most app-level resources are loaded from inside the app source tree.

---

## 28. Important Extension Points

SPP booting is intentionally overridable through events, app classes, modules, and config.

Common extension points:

```text
Custom app class:
  App\{AppName}\{AppName}App

Context override:
  event_spp_context_enforce

Route adjustment:
  event_spp_route_resolve

App initialization:
  event_spp_app_init

App custom bootstrap:
  app_init

Request protection/transformation:
  middleware.yml

Module behavior:
  module config and event handlers
```

---

## 29. Troubleshooting Boot Problems

### App context is wrong

Check:

```text
spp/etc/global-settings.yml
src/{app}/etc/app.yml
```

Confirm `base_url` matches the request URI.

### App config is missing

Check:

```php
\SPP\App::getApp()->getAppConfDir()
```

Common valid config paths:

```text
APP_ROOT/etc/apps/{app}
APP_ROOT/src/{app}/etc
```

### Modules are not loading

Check:

```php
\SPP\App::getApp()->getModsConfDir()
\SPP\App::getApp()->getModDir()
```

Also check:

```text
spp/etc/modules.yml
spp/etc/apps/{app}/modsconf
APP_ROOT/etc/apps/{app}/modsconf
APP_ROOT/src/{app}/modules
```

### Middleware is not running

Check:

```text
spp/etc/middleware.yml
{app config dir}/middleware.yml
```

Then confirm the entry point calls:

```php
\SPP\Core\MiddlewareKernel::run(...)
```

### Specific exception catches are not firing

Confirm the exception class exists in:

```text
spp/core/sppsystemexceptions.php
```

or in the module that declares it. If the class does not exist, SPP may fall back to `\SPP\SPPException`.

---

## 30. Boot Sequence Summary

```text
1. Entry file includes spp/sppinit.php
2. SPP defines constants and paths
3. Composer and SPP autoloaders are registered
4. Debug and session systems are prepared
5. Scheduler detects active app context
6. SPP chooses custom app, Drupal app, or base SPP\App
7. App resolves directories and registers with Scheduler
8. App init event fires
9. Modules load
10. Session object is initialized
11. Event handlers are scanned
12. app_init file is included
13. Middleware kernel may wrap the request
14. Entry point dispatches page, API, admin, CLI, or renderer logic
```

---

[Back to Framework Index](index.md)
