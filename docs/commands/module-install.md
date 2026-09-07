## `module:install`

**Purpose**: Install, activate, and configure framework-level modules or application-level extensions.

### Synopsis
```bash
php spp.php module:install <modulename> [OPTIONS]
```

### Extended Usage
The `module:install` command handles the lifecycle activation and schema installation for SPP modules. It supports both core system modules (`spp/modules/spp`, `spp/modules/contrib`) and project-scoped application modules (such as `src/SPPDocs/modules/` or `src/Lekhak/modules/`). When an application context is specified, it leverages the application's native `ModuleManager` to activate features per project.

Practical examples:
```bash
# Install and activate a core framework module
php spp.php module:install sppauth

# Install all active modules recorded in system configuration
php spp.php module:install --all

# Enable an application-level module for SPPDocs (e.g. reading_time or slack_webhook)
php spp.php module:install reading_time --app=SPPDocs --project=spp

# Enable all available modules for an application
php spp.php module:install --app=SPPDocs --all
```

### Options Available
- `<modulename>`: The unique identifier or directory name of the module to install.
- `--app=<appName>`: Target application context for app-level modules (e.g. `SPPDocs`, `Lekhak`).
- `--project=<projectId>`: Target project identifier when managing app modules (default: `spp`).
- `--all`: Install and activate all detected or registered modules in bulk.

### Under the Hood Activity
- Updates module status registries in `spp/etc/modules.yml` or project-level `modules.yml`.
- Runs module schema migrations and initializers (`modinit.php` / `module.php`).
- Re-indexes class paths in the native autoloader and refreshes kernel cache.

