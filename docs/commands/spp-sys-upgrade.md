# NAME

`sys:upgrade` - Synchronize the database schema incrementally from all active module definitions (`db.yml`).

# SYNOPSIS

`php spp sys:upgrade`

# PURPOSE

The `sys:upgrade` command is a vital system maintenance utility. It crawls through all enabled modules and incrementally applies structural database schema changes (tables, columns, indexes) defined in their respective `db.yml` files.

# OPTIONS AVAILABLE

This command accepts no explicit arguments or options.

# UNDER THE HOOD ACTIVITY

The command systematically drives the application's schema to a desired state:
1. **Module Check**: The very first step is verifying whether the database capability is active by checking `\SPP\Module::isEnabled('sppdb')`. If it's not enabled, it aborts immediately.
2. **Database Initialization**: It establishes a connection via the `SPPMod\SPPDB\SPPDB` driver.
3. **System Tables Scaffold**: It explicitly calls `ModuleInstaller::setupSystemTables()` to verify and inject core internal framework tables.
4. **Module Discovery**: It forces a full load of all active modules via `\SPP\Module::loadAllModules()` and iterates over the modules extracted from the `__mods` registry array.
5. **Schema Synchronization**: For every module:
   - It checks the module's filesystem path for the presence of a `db.yml` file.
   - If present, it passes the module object into `ModuleInstaller::executeDbYml($module)`.
   - `executeDbYml` performs an incremental schema sync—parsing the YAML, comparing it against the live database, and issuing non-destructive `CREATE` or `ALTER` queries accordingly.
6. **Exception Handling**: The entire operation is wrapped in a `try/catch` block. Failures will dump the exception message and stack trace to the CLI.

# EXAMPLES

**Run a system-wide incremental schema upgrade:**
```bash
php spp sys:upgrade
```
