# Volume IX — Building Applications

## Chapter 14 — Configuration and Runtime Settings

**Evidence:** `spp/core/class.app.php`, `spp/core/class.registry.php`, application-development documentation, `spp/etc/global-settings.yml`, application `etc/*.yml` conventions.

This chapter explains configuration to a developer who is new to SPP. The key idea is simple: **SPP has several configuration scopes, and each scope has a different job.**

## 14.1 Configuration is not one giant file

A common beginner mistake is to look for one equivalent of `config.php` and put everything there. SPP does not work that way.

Think in scopes:

| Scope | Typical location | Job |
|---|---|---|
| Framework/global | `spp/etc/` | Framework-wide defaults and infrastructure |
| Application | `src/myapp/etc/` or `etc/apps/myapp/` | Application identity and behavior |
| Module | Module manifest/config + app `modsconf` | Feature-specific configuration |
| Runtime | Registry and app runtime state | Values discovered or produced while running |
| Shared | Registry `__shared` namespace | Explicitly shared runtime state |

The exact files available depend on the modules enabled by the application.

## 14.2 Start with `app.yml`

For a self-contained application, `app.yml` is the most important configuration file to understand first.

```yaml
base_url: /myapp
etc_path: etc
src_path: src/myapp
modules_path: modules
var_path: var
app_init: init.php
```

This file answers questions such as:

- What URL belongs to this application?
- Where is the application's source tree?
- Where are its configuration files?
- Where are application-local modules?
- Where does its runtime directory live?
- What initialization file should be loaded?

## 14.3 Global settings

Framework bootstrap code reads `spp/etc/global-settings.yml` early in startup. It can contain global settings, application registrations, framework switches, and defaults used while applications are discovered.

A simplified example is:

```yaml
apps:
  myapp:
    base_url: /myapp
    etc_path: src/myapp/etc
    src_path: src/myapp
    app_init: init.php
```

Global registration and dynamic discovery are complementary mechanisms. A self-contained application can also be discovered from `src/myapp/etc/app.yml`.

## 14.4 Path resolution

One of the most important SPP concepts for beginners is that configuration paths are interpreted by the application runtime rather than copied blindly into PHP code.

For example:

```yaml
src_path: src/myapp
```

is resolved to the application's filesystem location under the project root.

The `App` object exposes methods such as:

```php
$app->getAppSrcDir();
$app->getAppConfDir();
$app->getModDir();
$app->getModsConfDir();
$app->getDataDir();
$app->getLogDir();
$app->getCacheDir();
$app->getTmpDir();
```

Prefer these APIs when application code needs a resolved path.

## 14.5 Settings versus Registry values

Configuration and runtime state are related but not identical.

A YAML setting is normally an input to startup or subsystem initialization. A Registry value is runtime data accessible through `Registry::register()` and `Registry::get()`.

For example:

```php
\SPP\Registry::register('__myapp=>feature_enabled', true);
```

That does not mean the value came from YAML. It means application/runtime code placed it into the Registry.

This distinction matters when debugging: changing a YAML file does not necessarily mean that an already-booted runtime Registry value will change immediately.

## 14.6 Configuration locking

The Registry supports locking of runtime configuration paths.

The intended use is to establish critical configuration, then prevent accidental mutation later in the runtime lifecycle.

The public APIs include:

```php
\SPP\Registry::lock($entity);
\SPP\Registry::checkLock($entity);
```

The implementation throws a runtime exception when a later modification violates a lock.

## 14.7 Shared configuration is explicit

SPP's shared Registry namespace is deliberately opt-in.

Values under the `__shared` namespace use the configured shared-storage mechanism. Ordinary Registry values do not automatically become distributed state.

This is an important enterprise rule:

> **Local runtime state stays local unless the application explicitly places it on the shared path.**

## 14.8 Environment-specific configuration

An enterprise deployment will normally have at least three configuration environments:

```text
development
staging
production
```

The handbook does not assume a single built-in `.env` mechanism because the supplied source uses several configuration files and runtime settings mechanisms instead. Environment strategy should therefore be built around the actual application configuration and deployment tooling used by the project.

Do not invent a configuration file that the runtime does not read.

## 14.9 Module configuration

Module metadata and application-specific module configuration have different responsibilities.

The manifest describes the module itself, including items such as dependencies and configuration variables. Application `modsconf` data supplies application-specific values.

This separation allows the same module to be reused by multiple applications without hard-coding one application's settings into the module package.

## 14.10 Middleware configuration

Middleware is configured separately from ordinary application settings.

The framework supports:

- global framework middleware;
- app-level `middleware.yml`; and
- route-level middleware attributes where the routing subsystem supports them.

See the middleware chapter for the exact assembly order.

## 14.11 Configuration precedence: do not guess

SPP has multiple configuration sources, but precedence is subsystem-specific.

For example, application discovery combines global application settings with dynamically discovered `app.yml` data. Module compilation has its own normalization logic.

Therefore the handbook will document precedence **per subsystem**, using the actual implementation, instead of claiming one universal rule such as "environment overrides application overrides module".

## 14.12 Configuration mistakes beginners commonly make

### Mistake 1: putting runtime state in YAML

If a value is calculated while the application runs, it probably belongs in runtime state rather than static configuration.

### Mistake 2: hard-coding filesystem paths

Use `App` path helpers rather than assuming the project is installed in one directory.

### Mistake 3: assuming every module reads `settings.yml`

A module defines its own configuration model. Follow the module's manifest and configuration reader.

### Mistake 4: assuming Registry state is automatically global

Only the explicitly shared Registry path is designed for shared storage.

## 14.13 A practical configuration workflow

For a new application:

1. Define the application in `app.yml`.
2. Verify the application is discoverable.
3. Confirm the active Scheduler context.
4. Confirm resolved application directories.
5. Add middleware configuration only when needed.
6. Enable modules through the module configuration system.
7. Put application-specific options in the application's configuration scope.
8. Use Registry values for runtime metadata rather than pretending they are static configuration.

## 14.14 The mental model

A useful way to think about SPP configuration is:

**Configuration files describe the runtime. The runtime constructs the application. The application and modules then create additional runtime state.**

That distinction makes the rest of SPP easier to understand.

## Kernel Hacker note

The configuration architecture is intentionally distributed because SPP is itself modular. A single monolithic configuration file would force unrelated modules, applications, and framework subsystems into one namespace. SPP instead keeps identity, activation, feature configuration, and runtime state separate and lets each subsystem own its interpretation.

### Source map

- `spp/core/class.app.php`
- `spp/core/class.registry.php`
- `spp/etc/global-settings.yml`
- `documentation/framework/application-development.md`
- `documentation/framework/booting-and-app-loading.md`
