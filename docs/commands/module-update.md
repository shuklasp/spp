## `module:update`

**Purpose**: Orchestrates database schema synchronizations (`db.yml`), version tracking in the `spp_modules` table, and execution of lifecycle upgrade hooks (`preUpgrade`, `update`, `postUpgrade`) for individual or all active modules.

### Synopsis
```bash
php spp.php module:update <modulename> [--from=version] [--to=version]
php spp.php module:update --all
```

### Extended Usage
The `module:update` command upgrades an existing installed module to its latest manifest version or triggers batch synchronization across all active modules:
```bash
# Update a specific module and synchronize its db.yml tables
php spp.php module:update spprouter

# Update all active modules across the system
php spp.php module:update --all

# Manually invoke upgrade hooks between explicit versions
php spp.php module:update custom_crm --from=1.0.0 --to=1.1.0
```

During execution, `module:update`:
1. Inspects the module's declared version and checks the `spp_modules` tracking table.
2. If not previously installed, routes to `ModuleInstaller::install()`.
3. Invokes `preUpgrade($oldVersion, $newVersion)` on the module's `ServiceProvider` if implemented (allowing the upgrade to be aborted safely).
4. Executes declarative database schema updates from `db.yml` (creating tables or adding missing columns incrementally without data loss).
5. Triggers `update($oldVersion, $newVersion)` and `postUpgrade($oldVersion, $newVersion)` on the `ServiceProvider`.
6. Updates the version number and `last_updated_at` timestamp in `spp_modules`.

### Options Available
- `<modulename>`: The internal name of the module to update.
- `--all`: Iterates through and synchronizes all currently active modules.
- `--from=version`: Specifies the baseline version (passed to `update()` hooks).
- `--to=version`: Specifies the target version (passed to `update()` hooks).

### Under the Hood Activity
- Loads module manifest and instantiates the declared `ServiceProvider`.
- Interacts with the active database adapter to execute incremental schema migrations.
- Executes `UPDATE` statements on the `spp_modules` framework table.
