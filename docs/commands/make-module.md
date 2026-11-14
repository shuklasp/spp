## `make:module`

**Purpose**: Scaffolds a complete modern SPP module with declarative configuration (`module.yml`), declarative database schema definitions (`db.yml`), lifecycle hooks (`modinit.php`, `install.php`, `uninstall.php`), zero-boot event registration (`events.yml`), and PSR-compliant dependency injection service provider.

### Synopsis
```bash
php spp.php make:module <name> [--scope=spp|optional|contrib|app] [--app=AppName]
```

### Extended Usage
The `make:module` command generates a production-ready module skeleton aligned with the Modularity 2.0 architecture:
```bash
# Scaffold an application-level module for the current app
php spp.php make:module blog

# Scaffold an application module for a specific application context
php spp.php make:module forum --app=school1

# Scaffold an optional framework module
php spp.php make:module telemetry --scope=optional

# Scaffold a community contribution module
php spp.php make:module payment_gateway --scope=contrib
```

The generated module structure includes:
- `module.yml`: Central YAML manifest declaring dependencies, namespace, assets, and service provider.
- `db.yml`: Declarative database schema definitions synced automatically during module installation and upgrade.
- `modinit.php`: Bootstrap logic executed when the module is active.
- `install.php`: One-time initialization script run during `php spp.php module:install`.
- `uninstall.php`: Clean-up script run during `php spp.php module:uninstall`.
- `events.yml`: Zero-boot event listener mapping for optimal performance.
- `src/<Name>ServiceProvider.php`: Container service provider implementing `\SPP\Core\Interfaces\ServiceProviderInterface`.
- `public/`: Web asset directory routed via `/sppasset/<name>/main/`.

### Options Available
- `<name>`: The internal alphanumeric name of the module to scaffold (required).
- `--scope=app|optional|contrib|spp`: Target placement directory. Defaults to `app`.
  - `app`: Placed in `src/<AppName>/modules/<name>`
  - `optional`: Placed in `spp/modules/optional/<name>`
  - `contrib`: Placed in `spp/modules/contrib/<name>`
  - `spp`: Placed in `spp/modules/spp/<name>`
- `--app=AppName`: Target application when using `--scope=app`. Defaults to active application context.

### Under the Hood Activity
- Creates directory structure under the resolved module root.
- Writes boilerplate YAML manifests (`module.yml`, `db.yml`, `events.yml`).
- Generates executable lifecycle and service provider PHP templates with PSR-4 namespacing.
- Does not modify database state or compile caches until enabled or installed.
