# SPP Framework Full API Reference

This reference is the high-level API map for SPP. It covers the framework runtime, the application lifecycle, core utilities, and the installed module catalog. Use it with the focused guides in this folder:

- [Booting & App Loading](booting-and-app-loading.md)
- [Architecture & App Structure](architecture-and-app-structure.md)
- [Application Development](application-development.md)
- [SPPUX Application Development](sppux-application-development.md)
- [Core Modules](modules/index.md)

SPP has three API layers:

1. **Core runtime APIs** in `spp/core`. These boot the framework, detect the active app, manage modules, route middleware, store state, resolve services, and expose utilities.
2. **Core module APIs** in `spp/modules/spp`. These provide database, entity, view, auth, queue, UX, API, cache, migration, PWA, language, audit, and integration capabilities.
3. **Application/contrib module APIs** in `src/{app}/modules` and `spp/modules/contrib`. These extend the framework for specific products such as Lekhak.

## Runtime Architecture API

### Bootstrap Flow

The runtime starts through `spp/sppinit.php`. That file defines base constants, registers autoloaders, loads core settings, initializes sessions, detects context, and lets `Scheduler` create the active `App`.

Typical request path:

```php
require_once __DIR__ . '/spp/sppinit.php';

$app = \SPP\App::getApp('autodemo');
$app->loadModules();

\SPP\Core\MiddlewareKernel::boot();
\SPP\Core\MiddlewareKernel::run(function () use ($app) {
    // route, controller, page rendering, API handler, or app front controller
});
```

The important runtime handoff is:

```text
sppinit.php
  -> Scheduler::detectAndEnforceContext()
  -> App::__construct()
  -> App::initializeDirs()
  -> App::loadModules()
  -> Module::scanModules()
  -> Module::loadModule()
  -> MiddlewareKernel::boot()
  -> MiddlewareKernel::run()
  -> app route/controller/view/api response
```

### Runtime Constants

Common constants used throughout SPP:

| Constant | Purpose |
| --- | --- |
| `SPP_BASE_DIR` | Project root / framework root anchor. |
| `SPP_CORE_DIR` | Core PHP runtime directory. |
| `SPP_MODULE_DIR` | Core system module root, normally `spp/modules/spp`. |
| `SPP_APP_DIR` | Application source root, normally `src`. |
| `SPP_ETC_DIR` | Global configuration root, normally `etc`. |
| `SPP_VAR_DIR` | Runtime data root, normally `var`. |
| `SPP_DS` | Directory separator abstraction. |
| `SPP_CONTEXT` | Current runtime context when defined. |

Use `App::resolvePath()` when application config contains relative paths. It normalizes paths across Windows, Linux, and WSL-style environments.

## Core Runtime APIs

### `SPP\App`

File: `spp/core/class.app.php`

`App` is the application process object. It owns app settings, directories, module loading, session initialization, and the per-app service container.

Public API:

| Method | Usage |
| --- | --- |
| `__construct(string $appname = '', bool $handleerror = true, int $init_level = 4)` | Creates and initializes an app process. Most code should use `getApp()` instead. |
| `getApp(string $appname = ''): App` | Returns an app instance and registers it with the scheduler. |
| `getGlobalSettings(string $key = ''): mixed` | Reads global SPP settings, optionally a single key. |
| `getActiveApp(): string` | Returns the scheduler's active app name. |
| `getAppConf(string $key, string $appname = ''): mixed` | Reads a value from app configuration. |
| `isModsLoaded(): bool` | Checks whether modules have been loaded for this app. |
| `setStatus(int $status): void` / `getStatus(): int` | Tracks app status. |
| `getLogDir()`, `getCacheDir()`, `getTmpDir()`, `getConfDir()`, `getModDir()`, `getDataDir()` | Returns resolved app directories. |
| `resolvePath(?string $path, string $baseDir = ''): string` | Resolves absolute, relative, Windows, Linux, and WSL-friendly paths. |
| `getAppConfDir(): string` | Returns `etc/apps/{app}` or the configured app config directory. |
| `getModsConfDir(): string` | Returns the module config directory for the active app. |
| `getAppSrcDir(): string` | Returns `src/{app}` or the configured app source directory. |
| `getErrorObj(): ?SPPError` | Returns the app error object. |
| `getName(): string` | Returns app name. |
| `initSession(): void` / `killSession(): void` | Starts or destroys SPP session state. |
| `getSessionName(): string` | Returns the active session name. |
| `loadModules(): void` | Discovers and loads enabled modules for this app. |
| `make(string $abstract, array $parameters = [])` | Resolves a class/service through the app container. |
| `bind(string $abstract, $concrete = null, bool $shared = false)` | Registers a service binding. |
| `singleton(string $abstract, $concrete = null)` | Registers a shared service binding. |
| `call($callable, array $parameters = [])` | Calls a function/method with dependency resolution. |
| `getBaseUrl(?string $appName = null): string` | Returns the configured app base URL. |
| `getContainer(): \SPP\Core\Container` | Returns the app's service container. |

Example:

```php
use SPP\App;

$app = App::getApp('autodemo');
$confDir = $app->getAppConfDir();
$logger = $app->make(\SPP\AppLogger::class);

$app->singleton(\App\Services\ReportService::class);
$report = $app->make(\App\Services\ReportService::class);
```

### `SPP\Scheduler`

File: `spp/core/class.scheduler.php`

`Scheduler` tracks the runtime context and active app process.

| Method | Usage |
| --- | --- |
| `setContext(string $context): void` | Sets the active context/app name. |
| `regProc(App $proc): void` | Registers an app process object. |
| `getContext(): string` | Returns the current context. |
| `hasContext(): bool` | Checks whether a context exists. |
| `getModsConfDir(): string` | Returns module config directory for active context. |
| `getProcObj(string $pname): App` | Returns a registered app process. |
| `getActiveProc(): App` | Returns the current app object. |
| `getActiveErrorObj(): ?SPPError` | Returns current app error object. |
| `detectAndEnforceContext(): void` | Determines context from runtime/request state. |
| `withContext(string $context, callable $callback)` | Temporarily runs code under another context. |

Example:

```php
\SPP\Scheduler::withContext('lekhak', function () {
    $app = \SPP\Scheduler::getActiveProc();
    $app->loadModules();
});
```

### `SPP\Core\Container`

File: `spp/core/class.container.php`

The container provides lightweight dependency injection. It supports explicit bindings, singletons, and constructor autowiring.

| Method | Usage |
| --- | --- |
| `bind(string $abstract, $concrete = null, bool $shared = false): void` | Registers a binding. |
| `singleton(string $abstract, $concrete = null): void` | Registers a shared binding. |
| `get(string $id): mixed` | Resolves an object/service. |
| `has(string $id): bool` | Checks whether a service can be resolved. |

Example:

```php
$container = \SPP\Registry::container();

$container->bind(App\Contracts\Mailer::class, App\Mail\SmtpMailer::class);
$container->singleton(App\Services\DashboardService::class);

$service = $container->get(App\Services\DashboardService::class);
```

### `SPP\Registry`

File: `spp/core/class.registry.php`

`Registry` is the shared framework registry. It stores runtime values, directories, classes, functions, and the global DI container.

| Method | Usage |
| --- | --- |
| `container(): Container` | Returns the global container. |
| `bind()`, `singleton()`, `make()` | Proxy helpers for the global container. |
| `register(string $entity, mixed $value): void` | Stores a registry value. |
| `loadShared(): void` | Loads shared registry state. |
| `registerDir(string $category, string|array $dir): void` | Registers directories under a category. |
| `registerClass(string $category, string $class): void` | Registers a class name under a category. |
| `registerFunction(string $category, string $function): void` | Registers a function under a category. |
| `getDirs(string $category): array|false` | Returns registered directories. |
| `getValue(string $entity): mixed` / `get(string $entity): mixed` | Reads a registry value. |
| `isRegistered(string $entity): bool` | Checks whether a registry key exists. |

Example:

```php
\SPP\Registry::register('feature.flags', ['new_dashboard' => true]);

if (\SPP\Registry::isRegistered('feature.flags')) {
    $flags = \SPP\Registry::get('feature.flags');
}
```

### `SPP\Module`

File: `spp/core/class.module.php`

`Module` discovers module manifests, loads include files, registers services/events, manages config, and runs installation/update routines.

| Method | Usage |
| --- | --- |
| `addModuleRoot(string $path): void` | Adds another module search root. |
| `disableModule(string $name): void` | Disables a module for the runtime. |
| `__construct(string $file)` | Creates a module from a manifest file. |
| `getSettingsDefinition(): array` | Reads module settings schema. |
| `getGlobalConfig(string $root, string $key, mixed $default = null): mixed` | Reads global module config. |
| `isCompulsory(string $modname): bool` | Checks whether a module is required. |
| `getConfig(string $varname, string $modname, ?string $appname = null): mixed` | Reads effective module config. |
| `getAppConfig(string $modname, ?string $appname = null): array` | Reads app-level module config. |
| `saveAppConfig(string $modname, string $appname, array $config): bool` | Saves module config for an app. |
| `setConfig(string $varname, mixed $value, string $modname, ?string $appname = null): void` | Writes a module config value. |
| `getConfDir(string $modname, ?string $appname = null): string` | Returns a module config directory. |
| `getExpectedConfigPath(string $modname, ?string $appname = null): string` | Returns expected module YAML path. |
| `getConfFile(string $modname, string $filename): array` | Loads an extra module config file. |
| `getModule(string $modname): Module` | Returns a loaded module object. |
| `scanModules(): array` | Discovers available module manifests. |
| `includeFiles(): void` | Includes PHP files declared in the manifest. |
| `register(): void` | Registers a module's services/events/config. |
| `findManifestPath(string $modname, string $type = 'system', ?string $appname = null): ?string` | Locates a manifest. |
| `isRegistered(): bool` | Checks current module registration. |
| `loadAllModules(): void` | Loads all enabled modules. |
| `isEnabled(string $mod): bool` | Checks module status. |
| `loadModule(string $modname, string $type = 'system', ?string $appname = null): ?Module` | Loads one module. |
| `toggleModuleStatus(string $modname, string $status, ?string $appname = null): array` | Enables/disables a module. |
| `ensureConfigForApp(string $modname, string $appname): ?string` | Creates app module config when missing. |
| `migrateXmlToYaml(string $xmlFile, string $ymlFile): bool` | Converts legacy module XML to YAML. |
| `getAllConfig()`, `getRawConfig()`, `getAllConfigForApp()`, `getRawConfigForApp()` | Reads merged/raw module config. |
| `setConfigForApp()` / `setAllConfigForApp()` | Writes app-scoped module config. |
| `getInstallationDeltas(): array` | Returns install changes declared by module. |
| `install(?string $appname = null): bool` | Installs a module for an app. |
| `runInstallation(): array` | Runs module installation tasks. |
| `getSystemUpdateDeltas(): array` / `runSystemUpdate(): array` | Handles system-level updates. |
| `getEffectiveModsConfDir(string $modname, string $appname): string` | Returns effective config directory. |
| `getRegistryFiles(string $appname): array` | Returns app module registry files. |
| `getAppModuleDirs(string $appname): array` | Returns app module directories. |
| `listAvailableModules(string $appname): array` | Lists app-visible modules. |
| `getModuleStatus(string $modname, string $appname): string` | Returns enabled/disabled status. |

Example:

```php
use SPP\Module;

$available = Module::listAvailableModules('autodemo');
$dbConfig = Module::getAllConfigForApp('sppdb', 'autodemo');

Module::loadModule('sppview');
Module::setConfigForApp('auto_js_injection', true, 'sppview', 'autodemo');
```

### Module Manifest API

Core modules use `module.yml`:

```yaml
module:
  name: sppux
  version: '1.0'
  pubname: 'SPP UX'
  category: 'Core Optional'
  includes:
    - class.sppux.php
  deps:
    - sppview
    - sppajax
  services:
    sppux:
      class: \SPPMod\SPPUX\SPPUX
      shared: true
  config_variables:
    runtime_path: 'spp/modules/spp/sppux/js/sppux.js'
  settings:
    auto_mount:
      type: boolean
      label: 'Auto Mount Components'
      default: true
```

Common keys:

| Key | Purpose |
| --- | --- |
| `name` | Internal module name. |
| `version` | Manifest version. |
| `pubname` / `description` / `pubdesc` | Human-readable module details. |
| `category` / `modgroup` | Classification. |
| `namespace` | PHP namespace for autoloaded classes. |
| `includes` | PHP files loaded during module registration. |
| `autoload` | Class-to-file mapping. |
| `deps` | Required modules. |
| `services` | Container services exposed by the module. |
| `config_variables` | Simple config defaults. |
| `settings` | Admin/config UI schema. |
| `installation.tables` | Tables created during install. |
| `installation.entities` | Entity classes installed by the module. |
| `installation.seeds` | Seed data. |

### `SPP\Core\MiddlewareKernel`

File: `spp/core/class.middlewarekernel.php`

Loads global/app middleware config and runs the request through the pipeline.

| Method | Usage |
| --- | --- |
| `boot()` | Reads middleware definitions from global and app config. |
| `run(\Closure $destination)` | Executes middleware and final destination. |

Config files:

- Global: `etc/middleware.yml`
- App: `etc/apps/{app}/middleware.yml`

Example:

```yaml
global:
  - \SPP\Core\Middleware\CSRFMiddleware

routes:
  admin:
    pattern: '^/admin'
    middleware:
      - \App\Middleware\AdminOnlyMiddleware
```

Middleware class:

```php
namespace App\Middleware;

class AdminOnlyMiddleware implements \SPP\Core\MiddlewareInterface
{
    public function handle($request, \Closure $next)
    {
        if (!\SPPMod\SPPAuth\SPPAuth::can('admin.access')) {
            return \SPP\Response::redirect('/login');
        }

        return $next($request);
    }
}
```

### `SPP\Core\Pipeline`

File: `spp/core/class.pipeline.php`

Generic onion-style pipeline used by middleware and other chainable processing.

| Method | Usage |
| --- | --- |
| `send($passable): self` | Sets the value/request moving through the pipeline. |
| `through(array $pipes): self` | Sets pipe classes/callables. |
| `then(callable $destination)` | Executes the chain and destination. |

Example:

```php
$result = (new \SPP\Core\Pipeline())
    ->send($request)
    ->through([
        \App\Pipeline\NormalizeInput::class,
        \App\Pipeline\ValidatePayload::class,
    ])
    ->then(fn ($request) => $controller->store($request));
```

### Event APIs

#### `SPP\SPPEvent`

File: `spp/core/class.sppevent.php`

The full lifecycle event system. It supports event registration, default handlers, overriders, handler scanning, dispatching, and trace persistence.

| Method | Usage |
| --- | --- |
| `registerEventHandler(string $event_name, callable $callback)` | Registers an inline callback. |
| `registerEvent(string $event_name, ?string $default_handler = null)` | Declares an event and optional default handler. |
| `getEvents()` | Returns registered event definitions. |
| `markOverrider($event_name)` | Marks event as overridden. |
| `getDefaultHandler($event_name)` / `hasDefaultHandler($event_name)` | Reads default handler metadata. |
| `registerEvents(array $events)` | Bulk event registration. |
| `registerHandler(string $event_name, string $handler_name, bool $default = false, ?string $method = null, ?int $priority = null)` | Registers a named handler. |
| `registerHandlers(string $event_name, array $handlers, bool $default = false)` | Registers multiple handlers. |
| `startEvent()` / `endEvent()` / `overrideEvent()` | Lifecycle event phase hooks. |
| `hasOverrider($handler_name)` | Checks override registration. |
| `dispatch(SPPEventObject $event)` | Dispatches event object. |
| `fireEvent($event_name, mixed &$params = array(), mixed $inline_handler = null)` | Fires an event by name. |
| `scanHandlers()` / `registerDirs()` / `scanAndRegisterDirs()` | Discovers handler files. |
| `getCollectedTrace()` / `clearTrace()` / `persistTrace()` | Event trace diagnostics. |

Example:

```php
\SPP\SPPEvent::registerEvent('dashboard.loaded');

\SPP\SPPEvent::registerHandler(
    'dashboard.loaded',
    \App\Events\DashboardLoadedHandler::class,
    false,
    'handle',
    10
);

$params = ['user_id' => 12];
\SPP\SPPEvent::fireEvent('dashboard.loaded', $params);
```

#### `SPP\EventManager`

File: `spp/core/class.eventmanager.php`

Small callback event bus for simple application events.

| Method | Usage |
| --- | --- |
| `listen(string $event, callable $callback): void` | Adds listener. |
| `trigger(string $event, &$data = null): void` | Runs listeners. |
| `clear(?string $event = null): void` | Clears one or all events. |

### Config APIs

#### `SPP\SPPConfig`

File: `spp/core/class.sppconfig.php`

Global typed config facade.

| Method | Usage |
| --- | --- |
| `registerSchema(string $namespace, array $schema): void` | Registers validation schema. |
| `validate(string $key, mixed $value, string $namespace = 'app'): void` | Validates a value. |
| `get(string $key, mixed $default = null): mixed` | Reads config. |
| `set(string $key, mixed $value): void` | Writes config. |
| `compile(string $appname): void` | Compiles app config. |
| `clearCompiled(string $appname): void` | Clears compiled config. |

Use `Module::getConfig()` for module-scoped config and `App::getAppConf()` for app-level config.

### Session APIs

#### `SPP\SPPSession`

File: `spp/core/class.sppsession.php`

SPP's session wrapper. It supports static access, object access, invalidation markers, CSRF tokens, and optional bridge sync.

| Method | Usage |
| --- | --- |
| `sessionExists()` | Checks active session. |
| `validSessionVarExists($varname)` | Checks existence and valid state. |
| `sessionVarExists($varname)` | Checks raw existence. |
| `getSessionVar($varname)` | Reads static session var. |
| `setSessionVar($varname, $varval, $bridged = false)` | Writes static session var. |
| `unsetSessionVar($varname)` | Removes static session var. |
| `invalidateSessionVar($varname)` | Marks static var invalid. |
| `setVar()`, `getVar()`, `unsetVar()`, `varExists()`, `validVarExists()`, `invalidateVar()` | Instance equivalents. |
| `getCsrfToken(): string` | Returns current CSRF token. |
| `generateCsrfToken(): string` | Generates a new CSRF token. |

Example:

```php
\SPP\SPPSession::setSessionVar('active_profile', 42, true);

if (\SPP\SPPSession::validSessionVarExists('active_profile')) {
    $profileId = \SPP\SPPSession::getSessionVar('active_profile');
}

$token = \SPP\SPPSession::getCsrfToken();
```

### Response and Storage APIs

#### `SPP\Response`

File: `spp/core/class.response.php`

| Method | Usage |
| --- | --- |
| `json($data, int $status = 200): void` | Sends JSON response and exits. |
| `redirect(string $url, int $status = 302): void` | Sends redirect response and exits. |

#### `SPP\Storage`

File: `spp/core/class.storage.php`

| Method | Usage |
| --- | --- |
| `disk(string $name = 'local'): DiskInterface` | Returns a disk adapter. |
| `__callStatic($method, $args)` | Proxies static calls to default disk. |

`DiskInterface`:

| Method | Usage |
| --- | --- |
| `get(string $path): ?string` | Reads a file. |
| `put(string $path, string $contents): bool` | Writes a file. |
| `exists(string $path): bool` | Checks existence. |
| `delete(string $path): bool` | Deletes a file. |

Example:

```php
\SPP\Storage::put('reports/latest.json', json_encode($payload));
$json = \SPP\Storage::get('reports/latest.json');
```

### Utility APIs

| Class/File | Purpose | Key APIs |
| --- | --- | --- |
| `SPPObject` | Base object with dynamic property behavior. | `__get`, `__set`, `__isset`, `__unset`, `__toString`. |
| `SPPError` | Framework error handling. | App error object and error template integration. |
| `SPPException` and `sppsystemexceptions.php` | Base and named framework exceptions. | Authentication, config, session, profile, event, wizard, module exceptions. |
| `SPPUtils` | General utility helpers. | `valueIn`, `valueNotIn`, `strleft`, `selfURL`, `xml2phpArray`, `str_replace_count`. |
| `SPPString` | String helper object. | `matchFileName`, `__toString`. |
| `SPPXml` | XML helper object. | `xml2phpArray`. |
| `SPPFS` | Filesystem helpers. | Use for framework filesystem operations where available. |
| `SPPGlobal` | Global singleton placeholder. | Prevents direct construction. |
| `Stack` | Simple stack structure. | `push`, `pop`, `peek`, `isEmpty`, `size`, `clear`. |
| `Translation` | Locale catalog loading and translation lookup. | `load`, `translate`, `getLocale`, `__`. |
| `WorkflowManager` | Entity workflow/state transition registry. | `getWorkflow`, `validateTransition`, `registerWorkflow`, `getWorkflows`, `getNextStates`. |
| `VersionManager` | Module version registry. | `getInstalledVersion`, `updateVersion`, `needsUpgrade`, `syncAll`, `getRegistry`. |
| `Command` / `CommandManager` | CLI command abstractions. | Use for SPP console-style tasks. |
| `Migration` | Migration base layer. | Used by modules declaring migrations. |
| `Queue` | Core queue primitive. | Prefer `sppqueue` for module-level usage. |
| `Cache`, `FileCache`, `RedisCache`, `CacheInterface` | Cache adapters. | `get`, `set`, `delete`, `clear`, `has`, tag APIs. |
| `PolyglotBridge` | Shared state bridge for other runtimes. | Used by session/registry/event trace bridge workflows. |
| `ResourceController`, `ExternalHandler` | Resource/external request helpers. | Used by app/module endpoints. |
| `sppfuncs.php` | Procedural date/IP helpers. | `tsToD`, `tsToHHMM`, `getVisitorIP`, `datediff`, `sql_date_shift`, `date_shift`. |

## Core Module APIs

Core modules live in `spp/modules/spp`. A module can provide PHP classes, YAML settings, install tables, service bindings, client assets, migrations, and event handlers.

### `sppdb`

Path: `spp/modules/spp/sppdb`

Purpose: database abstraction, table prefixing, query execution, transactions, incremental schema creation, and SQL query builder.

Main classes:

- `SPPMod\SPPDB\SPPDB`
- `SPPMod\SPPDB\QueryBuilder`
- `SPPMod\SPPDB\SPPSequence`

Key `SPPDB` API:

| Method | Usage |
| --- | --- |
| `__construct($dburl = null, $dbuser = null, $dbpasswd = null, $options = null, bool $shared = true)` | Opens database connection. |
| `getDriver(): ?string` | Returns DB driver. |
| `sppTable(string $tname): string` | Returns prefixed SPP table name. |
| `table(string $table): QueryBuilder` | Starts query builder. |
| `getRouteEntities(): array` | Returns routeable entities. |
| `getAdapter()` / `getPDO()` | Returns adapter/PDO connection. |
| `getConnectionSummary(): string` | Diagnostics-friendly connection description. |
| `prepare()`, `query()`, `exec()` | PDO-style operations. |
| `execute_query($sql, $values = array())` | Prepared execution helper. |
| `exec_squery($sql, $tabname, $values = array())` | Table-prefixed query helper. |
| `tableExists($table)` / `columnExists($table, $col)` | Schema checks. |
| `insertValues()` / `updateValues()` | Simple insert/update helpers. |
| `add_columns()` | Adds columns. |
| `createTableIncremental()` | Creates or updates a table from column spec. |
| `safeInsert()` | Inserts only if identity does not already exist. |
| `beginTransaction()` / `commit()` / `rollBack()` | Transaction control. |

Query builder API:

| Method | Usage |
| --- | --- |
| `select($columns = ['*'])` | Sets selected columns. |
| `selectRaw(string $sql, array $bindings = [])` | Adds raw select. |
| `where($column, $operator = null, $value = null, $boolean = 'AND')` | Adds where clause. |
| `orWhere($column, $operator = null, $value = null)` | Adds OR where. |
| `whereRaw(string $sql, array $bindings = [], $boolean = 'AND')` | Adds raw where. |
| `join(string $table, string $first, string $operator, string $second, string $type = 'INNER')` | Adds join. |
| `orderBy(string $column, string $direction = 'ASC')` | Adds order. |
| `limit(int $value)` / `offset(int $value)` | Pagination. |
| `get(): array` | Fetches rows. |
| `first(): ?array` | Fetches first row. |
| `count(): int` | Counts rows. |
| `insert(array $values): bool` | Inserts row. |
| `update(array $values): bool` | Updates rows. |
| `delete(): bool` | Deletes rows. |
| `toSql(): string` / `getBindings(): array` | Debugs generated SQL. |

Example:

```php
$db = new \SPPMod\SPPDB\SPPDB();

$rows = $db->table('users')
    ->select(['id', 'username', 'email'])
    ->where('status', '=', 'active')
    ->orderBy('username')
    ->limit(20)
    ->get();

$db->beginTransaction();
try {
    $db->table('audit_log')->insert([
        'event' => 'profile.updated',
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    $db->commit();
} catch (\Throwable $e) {
    $db->rollBack();
    throw $e;
}
```

Configuration:

- `dbtype`: `mysql`, `sqlite`, `pgsql`, `mssql`, `oracledb`, `msaccess`, or `xdb`
- `dbhost`, `dbport`, `dbuser`, `dbpasswd`, `dbname`
- `sqlite_path`
- `table_prefix`

### `sppentity`

Path: `spp/modules/spp/sppentity`

Purpose: entity model layer, persistence, validation, dynamic fields, revisions, entity definitions, login identity support, and natural search.

Main classes:

- `SPPMod\SPPEntity\SPPEntity`
- `SPPMod\SPPEntity\SPPEntityQuery`
- `SPPMod\SPPEntity\SPPEntityRelations`
- `SPPMod\SPPEntity\SPPDynamicFieldHandler`

Key API:

| Method | Usage |
| --- | --- |
| `__construct($id = null)` | Creates or loads an entity instance. |
| `setLanguage(string $langCode)` | Sets content language. |
| `getMetadata(string $key, $default = null)` / `setMetadata(string $key, $value)` | Static metadata access. |
| `define_attributes()` | Declares attributes in subclasses. |
| `after_creation()`, `after_load()`, `before_save()`, `after_save()` | Lifecycle hooks. |
| `jsonSerialize()` | JSON output. |
| `getId()` / `setId($id)` | Entity identity. |
| `getAttributes()` / `getValues()` | Schema and value inspection. |
| `getEntityConfigFile(string $entity_name)` | Resolves entity definition file. |
| `entityExists(mixed $entity_name)` | Checks entity definition. |
| `listAvailableEntities(): array` | Lists configured entities. |
| `saveEntityDefinition(string $name, string $appname, array $config): bool` | Writes entity definition. |
| `find_all(array $conditions = [], string $sort = null, int $limit = null)` | Finds multiple entities. |
| `find_one(array $conditions = [])` | Finds one entity. |
| `count()` | Counts entities. |
| `getEntityName($entity)` | Resolves entity name. |
| `getTable()` / `setTable($table)` | Table mapping. |
| `set($attribute, $value)` / `get($attribute)` | Value access. |
| `setAttributes($attributes)` / `setValues($values)` | Bulk setup. |
| `attributeExists($attribute)` | Attribute check. |
| `addAttributes($attributes)` | Adds dynamic attributes. |
| `install()` | Installs entity storage. |
| `save()` / `insert()` / `update()` / `delete()` | Persistence. |
| `validate(): ValidationResult` | Validates entity values. |
| `load($id)` / `loadBy($attribute, $value)` / `loadAll()` / `loadMultiple()` | Loading APIs. |
| `enableLogin(string $username, string $password)` / `disableLogin()` / `getLoginIdentity()` | Login identity support. |
| `searchNatural(string $query)` | Natural search. |
| `getRevisions()` / `restoreRevision($rev_id)` | Revision support. |

Example:

```php
class Article extends \SPPMod\SPPEntity\SPPEntity
{
    public function define_attributes()
    {
        $this->addAttributes([
            'title' => ['type' => 'text', 'required' => true],
            'body' => ['type' => 'textarea'],
            'status' => ['type' => 'text', 'default' => 'draft'],
        ]);
    }
}

$article = new Article();
$article->title = 'SPP API Reference';
$article->body = 'Long-form documentation';
$article->save();

$published = Article::find_all(['status' => 'published'], 'created_at DESC', 10);
```

### `sppview`

Path: `spp/modules/spp/sppview`

Purpose: view rendering, pages, forms, form elements, validation, HTML tags, assets, AJAX response objects, and page augmentation.

Main classes:

- `SPPMod\SPPView\ViewPage`
- `SPPMod\SPPView\ViewForm`
- `SPPMod\SPPView\ViewFormBuilder`
- `SPPMod\SPPView\ViewTag`
- `SPPMod\SPPView\SPPRequest`
- `SPPMod\SPPView\SPPResponse`
- `SPPMod\SPPView\Forms`
- `SPPMod\SPPView\AssetOrchestrator`
- `SPPMod\SPPView\DataTransformer`
- `SPPMod\SPPView\ValidationResult`

Key `ViewPage` API:

| Method | Usage |
| --- | --- |
| `showPage($page = null, array $options = [])` / `render($page = null)` | Renders a page. |
| `setPageId()` / `getPageId()` | Page identity. |
| `setPageTitle()` / `getPageTitle()` | Title metadata. |
| `setPageDescription()`, `setPageKeywords()`, `setPageAuthor()` | SEO metadata. |
| `setPageContent()`, `setPageHeader()`, `setPageFooter()`, `setPageHead()`, `setPageBody()`, `setPageMeta()` | Page sections. |
| `addJsIncludeFile($fpath, array $options = [])` / `addCssIncludeFile($fpath)` | Adds assets. |
| `addJsContent($content)` / `addCssContent($content)` | Adds inline assets. |
| `getJsIncludeList()` / `getCssIncludeList()` | Reads asset list. |
| `includeCSSFilesDynamic()` / `includeJSFilesDynamic()` | Emits assets. |
| `resolveTieredJS(string $appName, string $compName): void` | Resolves component JS tiers. |
| `addValidator($validator)` / `getValidators()` | Registers validators. |
| `addForm(ViewForm $form)` / `processForms()` | Form processing. |
| `readXMLFile($fl)` / `readFormFile($fl)` / `processXMLForm()` / `processFormArray(array $arr)` | Definition-based rendering. |
| `addElement(ViewTag $ename)` | Adds an HTML tag object. |

`Forms` API:

| Method | Usage |
| --- | --- |
| `getYaml(): array` | Loads form YAML. |
| `getFormConfig(string $name): ?array` | Reads one form config. |
| `listForms(): array` | Lists configured forms. |
| `clearCache(): void` | Clears form cache. |

`SPPRequest` API:

| Method | Usage |
| --- | --- |
| `getData()` | Returns request data. |
| `getRequestUrl()` | Current URL. |
| `getRequestMethod()` | HTTP method. |
| `generateFromJson($jason_data)` | Builds request data from JSON. |

`SPPResponse` API:

| Method | Usage |
| --- | --- |
| `getStatus()`, `setStatus($status)` | Response status. |
| `getData()`, `setData($data)` | Payload. |
| `getMessage()`, `setMessage($message)` | Message. |
| `json()` | Emits JSON. |
| `redirect(string $url)` | Redirects. |

Example:

```php
use SPPMod\SPPView\ViewPage;

ViewPage::setPageTitle('Dashboard');
ViewPage::addCssIncludeFile('/assets/dashboard.css');
ViewPage::addJsIncludeFile('/assets/dashboard.js', ['defer' => true]);
ViewPage::setPageContent('<main id="dashboard"></main>');
ViewPage::render();
```

Configuration:

- `page_source_primary`: `yaml`, `db`, or `file`
- `page_source_fallback`: `yaml`, `db`, `file`, or `none`
- `auto_page_augmentation`
- `auto_js_injection`

### `sppux`

Path: `spp/modules/spp/sppux`

Purpose: zero-build reactive component runtime for SPP applications, including component placeholders, runtime asset paths, bridge scripts, and BaseComponent JS.

Main class:

- `SPPMod\SPPUX\SPPUX`

Key API:

| Method | Usage |
| --- | --- |
| `runtimePath(?string $appname = null): string` | Returns SPPUX runtime JS path. |
| `uiPath(?string $appname = null): string` | Returns SPPUX UI JS path. |
| `cssPath(?string $appname = null): string` | Returns SPPUX CSS path. |
| `gridPath(?string $appname = null): string` | Returns grid runtime path. |
| `bridgePath(?string $appname = null): string` | Returns bridge runtime path. |
| `loaderPath(?string $appname = null): string` | Returns loader script path. |
| `componentBase(?string $appname = null): string` | Returns component base directory. |
| `componentPath(string $name, ?string $appname = null): string` | Returns a component file path. |
| `registerAssets(?string $appname = null): void` | Registers SPPUX assets with `ViewPage`. |
| `registerBridge(?string $appname = null): void` | Registers bridge script. |
| `boot(?string $appname = null): void` | Registers all required runtime assets. |
| `component(string $name, array $props = [], ?string $appname = null): string` | Returns component mount markup. |
| `render(string $name, array $props = [], ?string $appname = null): void` | Echoes component mount markup. |

Example:

```php
use SPPMod\SPPUX\SPPUX;

SPPUX::boot('autodemo');

echo SPPUX::component('TaskBoard', [
    'status' => 'open',
    'limit' => 20,
]);
```

Component:

```js
import BaseComponent from '/spp/modules/spp/sppux/js/BaseComponent.js';

export default class TaskBoard extends BaseComponent {
  state = { tasks: [] };

  async onMount() {
    this.setState({ tasks: await this.api('/api/tasks') });
  }

  render() {
    return `<section>${this.state.tasks.map(t => `<p>${t.title}</p>`).join('')}</section>`;
  }
}
```

Configuration:

- `runtime_path`
- `loader_path`
- `component_base`
- `auto_mount`
- `expose_bridge`

See [SPPUX Application Development](sppux-application-development.md) for step-by-step usage, including single component pages and Lekhak-style separate HTML/PHP shell files.

### `sppajax`

Path: `spp/modules/spp/sppajax`

Purpose: standardized asynchronous request handling, live actions, and JSON interactions.

Main classes:

- `SPPMod\SPPAjax\SPPAjax`
- `SPPMod\SPPAjax\LiveAction`

Usage pattern:

```php
// Controller or endpoint.
$response = new \SPPMod\SPPView\SPPResponse();
$response->setStatus(200);
$response->setData(['ok' => true]);
$response->json();
```

SPPAjax is commonly paired with SPPUX bridge calls, LiveService-style requests, and `sppapi` endpoints.

### `sppapi`

Path: `spp/modules/spp/sppapi`

Purpose: headless REST entry point and zero-code CRUD routing based on configuration.

Main class:

- `SPPMod\SPPAPI\SPPAPI`

Key API:

| Method | Usage |
| --- | --- |
| `handle(): void` | Handles the current API request and emits a response. |
| `isApiRequest(): bool` | Detects whether the current request targets the API layer. |
| `respond(string $status, array $data, int $code = 200): never` | Sends standardized API JSON response and exits. |

Example:

```php
if (\SPPMod\SPPAPI\SPPAPI::isApiRequest()) {
    \SPPMod\SPPAPI\SPPAPI::handle();
}
```

Custom endpoint response:

```php
\SPPMod\SPPAPI\SPPAPI::respond('success', [
    'items' => $items,
], 200);
```

### `sppauth`

Path: `spp/modules/spp/sppauth`

Purpose: authentication, guards, users, roles, rights, RBAC, user sessions, and anonymous users.

Main classes/interfaces:

- `SPPMod\SPPAuth\SPPAuth`
- `SPPMod\SPPAuth\GuardInterface`
- `SPPMod\SPPAuth\UserProviderInterface`
- `SPPMod\SPPAuth\WebGuard`
- `SPPMod\SPPAuth\RBAC`
- `SPPMod\SPPAuth\SPPUser`
- `SPPMod\SPPAuth\SPPRole`
- `SPPMod\SPPAuth\SPPRight`
- `SPPMod\SPPAuth\SPPUserSession`
- `SPPMod\SPPAuth\AnonymousUser`

Key API:

| Method | Usage |
| --- | --- |
| `guard(string $name = null): GuardInterface` | Returns a configured guard. |
| `login($uname, $passwd)` | Authenticates by username/password. |
| `logout()` | Ends auth session. |
| `authSessionExists($consider_timeout = false)` | Checks login state. |
| `hasRight($rt)` | Checks legacy right. |
| `user()` | Returns current user object/value. |
| `getCurrentUser(): ?array` | Returns current user as array. |
| `check(): bool` | Returns true when logged in. |
| `can(string $permission): bool` | Checks permission through guard/RBAC. |

Example:

```php
use SPPMod\SPPAuth\SPPAuth;

if (!SPPAuth::check()) {
    \SPP\Response::redirect('/login');
}

if (SPPAuth::can('content.publish')) {
    // Show publishing controls.
}

$user = SPPAuth::getCurrentUser();
```

Installation tables:

- `users`
- `roles`
- `rights`
- `userroles`
- `roleright`
- `entity_roles`

Default seed roles:

- `SuperAdmin`
- `Member`

### `spplogger`

Path: `spp/modules/spp/spplogger`

Purpose: file/database logging, levels, metadata, diagnostics, and audit-friendly log calls.

Main class:

- `SPPMod\SPPLogger\SPPLogger`

Key API:

| Method | Usage |
| --- | --- |
| `write_to_log($message, $level = self::INFO, array $context = [])` | Primary logging entry point. |
| `write_to_db($message, $level, array $metadata, array $context = [])` | Writes DB log. |
| `write_to_file($message, $level, array $metadata, array $context = [])` | Writes file log. |
| `log($message, $level = self::INFO, array $context = [])` | Alias for generic log. |
| `error($message, array $context = [])` | Error log. |
| `debug($message, array $context = [])` | Debug log. |
| `info($message, array $context = [])` | Info log. |

Example:

```php
\SPPMod\SPPLogger\SPPLogger::info('User opened dashboard', [
    'user_id' => $userId,
    'app' => \SPP\App::getActiveApp(),
]);

\SPPMod\SPPLogger\SPPLogger::error('Payment failed', [
    'order_id' => $orderId,
]);
```

### `sppqueue`

Path: `spp/modules/spp/sppqueue`

Purpose: background tasks and distributed job management.

Main class:

- `SPPMod\SPPQueue\SPPQueue`

Usage pattern:

```php
// Prefer a queue/job wrapper in your app service.
$job = [
    'handler' => \App\Jobs\SendDigestMail::class,
    'payload' => ['user_id' => 12],
];

// Use SPPQueue once app-level queue configuration is enabled.
```

Use cases:

- Long-running report generation
- Email digests
- Webhook retries
- Data sync
- Scheduled cleanup

### `sppconfig` and `sppsetting`

Paths:

- `spp/modules/spp/sppconfig`
- `spp/modules/spp/sppsetting`

Purpose: persistent app/module settings, settings UI schemas, and runtime configuration.

Use `SPPConfig` for framework-level config and `Module::getConfig()` for module config:

```php
$theme = \SPP\Module::getConfig('theme', 'sppsetting', 'lekhak');
\SPP\Module::setConfigForApp('theme', 'default', 'sppsetting', 'lekhak');
```

### `sppgroup`, `sppprofile`, and `sppuserprofile`

Paths:

- `spp/modules/spp/sppgroup`
- `spp/modules/spp/sppprofile`
- `spp/modules/spp/sppuserprofile`

Purpose: identity grouping, profile metadata, and user-profile links.

Main classes:

- `SPPMod\SPPGroup\SPPGroup`
- `SPPMod\SPPGroup\SPPGroupLoader`
- `SPPMod\SPPGroup\SPPGroupMember`
- `SPPMod\SPPProfile\SPPProfile`
- `SPPMod\SPPUserProfile\SPPUserProfile`

Use cases:

- Organization/team membership
- Multi-profile accounts
- Role assignment by group/profile
- App-specific profile fields

### `sppxdb`

Path: `spp/modules/spp/sppxdb`

Purpose: native XML database engine, migrations, controller, and query builder.

Main classes:

- `SPPMod\SPPXDB\SPPXDB`
- `SPPMod\SPPXDB\QueryBuilder`
- `SPPMod\SPPXDB\MigrationManager`
- `SPPMod\SPPXDB\XDBController`

Use cases:

- XML-native content storage
- Lightweight embedded app data
- Content/version/audit graph storage
- Adapter target for `sppinterdb`

### `sppinterdb`

Path: `spp/modules/spp/sppinterdb`

Purpose: federated database adapter layer and gateway between PDO-style data and XDB data.

Main classes/interfaces:

- `SPPMod\SPPInterDB\DBAdapter`
- `SPPMod\SPPInterDB\PDOAdapter`
- `SPPMod\SPPInterDB\XDBAdapter`
- `SPPMod\SPPInterDB\SPPInterDB`

Use cases:

- Switching database engines behind a common adapter
- Federating relational and XML data
- GraphQL-style aggregation gateways

### `sppblade`

Path: `spp/modules/spp/sppblade`

Purpose: Blade-style template rendering integration.

Main class:

- `SPPMod\SPPBlade\SPPBlade`

Use cases:

- Template-driven app shells
- Partial layouts
- Server-rendered HTML pages
- Lekhak-style PHP/HTML shell files

### `spppwa`

Path: `spp/modules/spp/spppwa`

Purpose: progressive web app support.

Main class:

- `SPPMod\SPPPWA\SPPPWA`

Use cases:

- Service worker registration
- Manifest generation
- Offline app shell support
- Push/install experience

### `sppai`

Path: `spp/modules/spp/sppai`

Purpose: AI driver abstraction.

Main classes/interfaces:

- `SPPMod\SPPAI\SPPAI`
- `SPPMod\SPPAI\AIDriver`

Use cases:

- Text generation adapters
- AI-assisted content tools
- Chat or analysis services
- App-specific AI providers

### `sppaudit`

Path: `spp/modules/spp/sppaudit`

Purpose: audit trail and action logging.

Main class:

- `SPPMod\SPPAudit\SPPAudit`

Use cases:

- Security-sensitive change history
- Admin action logs
- Compliance/event review
- Entity mutation auditing

### `sppmigrate`

Path: `spp/modules/spp/sppmigrate`

Purpose: migration runner and module/app schema evolution.

Main class/module:

- `SPPMod\SPPMigrate\SPPMigrate`
- `module.php`

Use cases:

- Apply module migrations
- Track installed versions
- Upgrade app/module schemas
- Coordinate with `VersionManager`

### `sppdiff`

Path: `spp/modules/spp/sppdiff`

Purpose: revision and delta engine.

Main classes:

- `SPPMod\SPPDiff\DeltaEngine`
- `SPPMod\SPPDiff\RevisionManager`

Use cases:

- Content revision comparison
- Entity history
- Document diffs
- Version restore workflows

### `sppsync`

Path: `spp/modules/spp/sppsync`

Purpose: synchronization workflows.

Main class:

- `SPPMod\SPPSync\SPPSync`

Use cases:

- Pull/push external data
- Cross-app sync
- Import/export jobs
- Scheduled reconciliation

### `spplive`

Path: `spp/modules/spp/spplive`

Purpose: live/reactive service layer.

Main class:

- `SPPMod\SPPLive\SPPLive`

Use cases:

- Live service endpoints
- Reactive UI data sources
- SAJAX-style service calls
- SPPUX bridge integration

### `spppjax`

Path: `spp/modules/spp/spppjax`

Purpose: PJAX navigation support.

Main class:

- `SPPMod\SPPPJAX\SPPPJAX`

Use cases:

- Partial page navigation
- Faster server-rendered shells
- Preserving app layout while replacing content regions

### `spplang`

Path: `spp/modules/spp/spplang`

Purpose: language/localization support.

Main class:

- `SPPMod\SPPLang\SPPLang`

Use with `SPP\Translation` for catalog loading and translation lookup:

```php
\SPP\Translation::load('hi_IN');
echo \SPP\Translation::translate('dashboard.title');
```

### `sppwizard`

Path: `spp/modules/spp/sppwizard`

Purpose: multi-step wizard workflows.

Main class:

- `SPPMod\SPPWizard\SPPWizard`

Use cases:

- Setup flows
- Multi-step forms
- Profile completion
- Admin guided configuration

### `sppdrupal`

Path: `spp/modules/spp/sppdrupal`

Purpose: Drupal bridge and migration support.

Main classes:

- `SPPMod\SPPDrupal\SPPDrupal`
- `SPPMod\SPPDrupal\SPPDrupalBridge`

Use cases:

- Legacy Drupal content access
- Drupal-to-SPP migration
- Entity bridge
- Hybrid Lekhak/Drupal app stacks

### `sppext`

Path: `spp/modules/spp/sppext`

Purpose: extension integration.

Main class:

- `SPPMod\SPPExt\SPPExt`

Use cases:

- Runtime extension points
- External module bridges
- Optional integration packages

### `sppcache`

Path: `spp/modules/spp/sppcache`

Purpose: cache manager and cache orchestration.

Main class:

- `SPPMod\SPPCache\SPPCacheManager`

Use with core cache adapters:

```php
$cache = new \SPP\FileCache();
$cache->set('dashboard.stats', $stats, 300);
```

### `dbsettings`

Path: `spp/modules/spp/dbsettings`

Purpose: database-backed settings.

Main class:

- `SPPMod\DBSettings\DBSettings`

Use cases:

- Runtime settings stored in DB
- Admin-editable configuration
- Per-app persistent preferences

### `drishyam`

Path: `spp/modules/spp/drishyam`

Purpose: visual/front-end helper module. Current module entry point is `modinit.php`.

Use cases:

- Visual components
- Presentation helpers
- App UI integrations

### `marketing`

Path: `spp/modules/spp/marketing`

Purpose: marketing-related app helpers.

Main class:

- `SPPMod\Marketing\Marketing`

Use cases:

- Campaign pages
- Public content utilities
- Lead/landing page integrations

### `parikshak`

Path: `spp/modules/spp/parikshak`

Purpose: automated testing, system scanning, and quality checks.

Main class:

- `SPPMod\Parikshak\Parikshak`

Use cases:

- App diagnostics
- Evolutionary tests
- Regression checks
- Framework health scans

### `sppvalidator`

Path: `spp/modules/spp/sppview/sppvalidator`

Purpose: validation primitives used by views/forms/entities.

Main classes:

- `SPPMod\SPPView\ValidationResult`
- `SPPMod\SPPView\SPPSingleValidator`
- `SPPMod\SPPView\SPPMultipleValidator`
- Validator collections in `classes.sppvalidators.php`

Use cases:

- Form validation
- Entity validation
- Server-side validation feedback

### Additional Core Modules

These modules are available in `spp/modules/spp` and should be treated as extension points or specialized services:

| Module | Primary file(s) | Purpose |
| --- | --- | --- |
| `sppconfig` | `class.sppconfig.php` | Module-level config integration. |
| `sppsetting` | `class.sppsetting.php` | Runtime settings. |
| `sppuserprofile` | `class.sppuserprofile.php`, `install.php` | User profile storage/installation. |
| `sppgroup` | `class.sppgroup.php`, `class.sppgrouploader.php`, `class.sppgroupmember.php` | Groups and group membership. |
| `sppprofile` | `class.sppprofile.php` | Profile metadata. |
| `sppdiff` | `class.deltaengine.php`, `class.revisionmanager.php` | Diffs and revisions. |
| `sppinterdb` | `class.pdoadapter.php`, `class.xdbadapter.php`, `int.dbadapter.php` | Database adapter federation. |
| `sppxdb` | `class.sppxdb.php`, `class.querybuilder.php`, `class.migrationmanager.php` | XML database. |
| `sppwizard` | `class.sppwizard.php` | Wizard workflows. |
| `sppdrupal` | `class.sppdrupal.php`, `class.sppdrupalbridge.php` | Drupal bridge. |
| `spppwa` | `class.spppwa.php` | PWA helpers. |
| `spplang` | `class.spplang.php` | Language support. |
| `spplive` | `class.spplive.php` | Live services. |
| `spppjax` | `class.spppjax.php` | PJAX page updates. |
| `sppsync` | `class.sppsync.php` | Sync workflows. |
| `sppmigrate` | `class.sppmigrate.php`, `module.php` | Migration execution. |
| `sppext` | `class.sppext.php` | Extension hooks. |
| `sppcache` | `class.sppcachemanager.php` | Cache management. |
| `sppai` | `class.sppai.php`, `int.aidriver.php` | AI provider abstraction. |
| `sppaudit` | `class.sppaudit.php` | Audit trail. |
| `sppblade` | `class.sppblade.php` | Blade-style rendering. |
| `dbsettings` | `class.dbsettings.php` | Database settings. |
| `marketing` | `class.marketing.php` | Marketing helpers. |
| `drishyam` | `modinit.php` | Visual helpers. |

## Contrib Module APIs

Contrib modules live in `spp/modules/contrib`. The current contrib module is:

| Module | Path | Purpose |
| --- | --- | --- |
| `lekhni` | `spp/modules/contrib/lekhni` | Rich editor / IDE / document workspace. See [Lekhni Editor](modules/lekhni.md). |

Contrib modules follow the same manifest, include, dependency, config, and service rules as core modules, but should be treated as optional packages.

## Lekhak Application Module Catalog

Lekhak modules live in `src/lekhak/modules`. Most are app-specific feature modules. Some have `module.yml` manifests, while many are Drupal/Lekhah compatibility modules, feature modules, or integration folders.

Manifest-backed Lekhak modules discovered:

| Module | Purpose |
| --- | --- |
| `lekhak` | Main Lekhak app module. |
| `lekhak_commerce` | Commerce features. |
| `lekhak_drupal_bridge` | Drupal bridge for Lekhak. |
| `lekhak_peeche` | Admin/back-office integration. |
| `sankhyaki` | Analytics/statistics module. |

Other Lekhak module directories currently present:

```text
akeeba_backup, automated_logout, blazy, cdn, crop, dropzonejs,
drupal_test_module, entity_browser, entity_reference_revisions,
falang_translation, fast_404, features, field_group, focal_point,
google_analytics, honeypot, imageapi_optimize, inline_entity_form,
lazy, lekhak_ab_testing, lekhak_academy, lekhak_affiliates,
lekhak_antibot, lekhak_audit_trail, lekhak_authors, lekhak_automation,
lekhak_backend_shield, lekhak_blocks_nested, lekhak_classifieds,
lekhak_community, lekhak_core_entities, lekhak_documents,
lekhak_donations, lekhak_drupal_api, lekhak_events, lekhak_forms,
lekhak_forum, lekhak_gallery, lekhak_gdpr, lekhak_glossary,
lekhak_healthcare, lekhak_helpdesk, lekhak_journal, lekhak_layouts,
lekhak_lightbox, lekhak_logger, lekhak_memberships, lekhak_migrations,
lekhak_newsletter, lekhak_optimizer, lekhak_page_builder, lekhak_pdf,
lekhak_popups, lekhak_portfolio, lekhak_pwa, lekhak_qa,
lekhak_query_builder, lekhak_reading_time, lekhak_realestate,
lekhak_redirects, lekhak_reviews, lekhak_routing, lekhak_search_pro,
lekhak_security, lekhak_seo, lekhak_seo_analyzer, lekhak_seo_sitemap,
lekhak_sitemap, lekhak_store, lekhak_subscriptions, lekhak_toolbar,
lekhak_tools, lekhak_watermark, lekhak_webhooks, lekhak_widgets,
libraries, login_security, media_library, memcache, paranoia,
password_policy, rabbit_hole, redis, schema_metatag, search_api,
seo_checklist, shield, social_connect, spptheme, tfa, token, varnish
```

Treat these as application modules unless they are promoted into `spp/modules/spp` or `spp/modules/contrib`.

Recommended app module structure:

```text
src/lekhak/modules/lekhak_example/
  module.yml
  modinit.php
  class.lekhakexample.php
  services/
  controllers/
  events/
  migrations/
  templates/
  assets/
```

Example app module manifest:

```yaml
module:
  name: lekhak_example
  version: '1.0.0'
  pubname: 'Lekhak Example'
  category: 'App Optional'
  includes:
    - class.lekhakexample.php
  deps:
    - sppdb
    - sppview
  services:
    lekhak.example:
      class: \Lekhak\Modules\Example\ExampleService
      shared: true
  config_variables:
    enabled: true
```

## API Usage Patterns

### Build a Service-Oriented App Feature

```php
namespace App\Services;

class InvoiceService
{
    public function __construct(
        private \SPPMod\SPPDB\SPPDB $db
    ) {}

    public function recentForUser(int $userId): array
    {
        return $this->db->table('invoices')
            ->where('user_id', '=', $userId)
            ->orderBy('created_at', 'DESC')
            ->limit(10)
            ->get();
    }
}
```

Registration:

```php
$app = \SPP\App::getApp('autodemo');
$app->singleton(\App\Services\InvoiceService::class);
```

Controller usage:

```php
$service = $app->make(\App\Services\InvoiceService::class);
$invoices = $service->recentForUser($userId);

\SPP\Response::json([
    'status' => 'success',
    'data' => $invoices,
]);
```

### Add a Page with SPPView and SPPUX

```php
use SPPMod\SPPView\ViewPage;
use SPPMod\SPPUX\SPPUX;

SPPUX::boot('autodemo');

ViewPage::setPageTitle('Tasks');
ViewPage::setPageContent(
    SPPUX::component('TaskBoard', ['filter' => 'open'], 'autodemo')
);
ViewPage::render();
```

### Add an API Endpoint

```php
use SPPMod\SPPAPI\SPPAPI;

try {
    $rows = (new \SPPMod\SPPDB\SPPDB())
        ->table('tasks')
        ->where('status', '=', $_GET['status'] ?? 'open')
        ->get();

    SPPAPI::respond('success', ['tasks' => $rows]);
} catch (\Throwable $e) {
    SPPAPI::respond('error', ['message' => $e->getMessage()], 500);
}
```

### Add Middleware

```php
namespace App\Middleware;

class RequireLogin implements \SPP\Core\MiddlewareInterface
{
    public function handle($request, \Closure $next)
    {
        if (!\SPPMod\SPPAuth\SPPAuth::check()) {
            return \SPP\Response::redirect('/login');
        }

        return $next($request);
    }
}
```

`etc/apps/autodemo/middleware.yml`:

```yaml
global:
  - \App\Middleware\RequireLogin
```

### Add a Workflow

```php
\SPP\WorkflowManager::registerWorkflow('article', [
    'states' => ['draft', 'review', 'published'],
    'transitions' => [
        'draft' => ['review'],
        'review' => ['draft', 'published'],
        'published' => [],
    ],
]);

if (\SPP\WorkflowManager::validateTransition($article, 'review', 'published', $user)) {
    $article->status = 'published';
    $article->save();
}
```

### Read and Write Module Config

```php
$autoMount = \SPP\Module::getConfig('auto_mount', 'sppux', 'autodemo');

\SPP\Module::setConfigForApp(
    'component_base',
    'src/{app}/comp',
    'sppux',
    'autodemo'
);
```

### Discover Modules Programmatically

```php
$modules = \SPP\Module::scanModules();
$available = \SPP\Module::listAvailableModules('autodemo');

foreach ($available as $name => $meta) {
    $status = \SPP\Module::getModuleStatus($name, 'autodemo');
}
```

## Extension Rules

### Where to Put Code

| Code type | Recommended location |
| --- | --- |
| App controllers/services | `src/{app}/controllers`, `src/{app}/services` |
| App components | `src/{app}/comp` |
| App modules | `src/{app}/modules/{module}` |
| App config | `etc/apps/{app}` |
| App module config | `etc/apps/{app}/modules/{module}.yml` |
| Shared/core modules | `spp/modules/spp/{module}` |
| Optional packages | `spp/modules/contrib/{module}` |
| Runtime logs/cache/tmp/data | `var/` |

### Module Dependency Rules

- Put reusable framework services in `spp/modules/spp`.
- Put optional framework extensions in `spp/modules/contrib`.
- Put product-specific features in `src/{app}/modules`.
- Declare dependencies in `module.yml` using `deps`.
- Keep module config in YAML rather than hard-coding environment values.
- Use services for reusable objects and `includes` only for required class/function files.

### Stability Notes

Stable/public APIs:

- `App`
- `Scheduler`
- `Registry`
- `Container`
- `Module`
- `MiddlewareKernel`
- `SPPEvent`
- `SPPConfig`
- `SPPSession`
- `Response`
- `Storage`
- `SPPDB`
- `SPPEntity`
- `ViewPage`
- `SPPUX`
- `SPPAPI`
- `SPPAuth`
- `SPPLogger`

Specialized or evolving APIs:

- `sppai`
- `sppaudit`
- `sppdrupal`
- `sppinterdb`
- `sppxdb`
- `sppsync`
- `spplive`
- `spppwa`
- app-specific Lekhak modules

When developing application features, prefer stable APIs and isolate evolving modules behind app services.

## Troubleshooting API Usage

### Module Not Loading

Check:

1. The module has `module.yml`.
2. The module is enabled in app module registry/config.
3. Dependencies in `deps` are available and enabled.
4. Files listed under `includes` exist.
5. The app's module config path resolves under `etc/apps/{app}`.

Helpful calls:

```php
var_dump(\SPP\Module::findManifestPath('sppux'));
var_dump(\SPP\Module::getModuleStatus('sppux', 'autodemo'));
var_dump(\SPP\Module::listAvailableModules('autodemo'));
```

### Service Not Resolving

Check:

1. The class exists and autoload path is active.
2. Constructor dependencies are type-hinted with concrete classes or registered interfaces.
3. Union types include a resolvable class or a default value.
4. Required scalar constructor parameters are passed to `App::make()`.

Example:

```php
$service = $app->make(\App\Services\Mailer::class, [
    'transport' => 'smtp',
]);
```

### App Paths Are Wrong

Check:

```php
$app = \SPP\App::getApp('autodemo');

echo $app->getAppSrcDir();
echo $app->getAppConfDir();
echo $app->getModsConfDir();
```

Use app config paths relative to the project root unless an absolute path is intentional.

### Middleware Not Running

Check:

1. `MiddlewareKernel::boot()` is called.
2. Global config exists at `etc/middleware.yml`.
3. App config exists at `etc/apps/{app}/middleware.yml`.
4. Middleware implements `\SPP\Core\MiddlewareInterface`.
5. `handle($request, \Closure $next)` returns `$next($request)` or a valid response.

### SPPUX Component Not Mounting

Check:

1. `SPPUX::boot($app)` is called.
2. The component file exists under `src/{app}/comp`.
3. The component exports a default class.
4. The page includes the runtime and loader scripts.
5. The placeholder was generated with `SPPUX::component()` or manually follows the expected `data-spp-component` convention.

### API Response Is Not JSON

Check:

1. Use `SPPAPI::respond()` or `SPP\Response::json()`.
2. Avoid echoing HTML before JSON headers.
3. Ensure middleware does not redirect the request.
4. Confirm the request path is detected by `SPPAPI::isApiRequest()`.

## Quick API Index

| Need | Use |
| --- | --- |
| Current app | `App::getApp()`, `Scheduler::getActiveProc()` |
| App config | `App::getAppConf()` |
| Module config | `Module::getConfig()`, `Module::getAllConfigForApp()` |
| DI/service resolution | `App::make()`, `Registry::make()`, `Container::get()` |
| Register service | `App::bind()`, `App::singleton()`, `Registry::bind()` |
| Load modules | `App::loadModules()`, `Module::loadModule()` |
| List modules | `Module::scanModules()`, `Module::listAvailableModules()` |
| Middleware | `MiddlewareKernel::boot()`, `MiddlewareKernel::run()` |
| Events | `SPPEvent::fireEvent()`, `EventManager::trigger()` |
| Sessions | `SPPSession::*` |
| CSRF | `SPPSession::getCsrfToken()` |
| JSON response | `Response::json()`, `SPPAPI::respond()` |
| Redirect | `Response::redirect()` |
| Database | `SPPDB`, `QueryBuilder` |
| Entities | `SPPEntity` |
| Pages | `ViewPage` |
| Forms | `Forms`, `ViewForm`, validators |
| Components | `SPPUX::boot()`, `SPPUX::component()` |
| Auth | `SPPAuth::check()`, `SPPAuth::can()`, `SPPAuth::user()` |
| Logging | `SPPLogger::info()`, `debug()`, `error()` |
| Storage | `Storage::get()`, `put()`, `exists()`, `delete()` |
| Translation | `Translation::load()`, `Translation::translate()` |
| Workflows | `WorkflowManager` |
| Versions/migrations | `VersionManager`, `sppmigrate` |

