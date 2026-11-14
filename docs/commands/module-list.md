## `module:list`

**Purpose**: Discovers, inspects, and tabulates all available and active modules across framework kernel, optional modules, contrib extensions, and application-level modules.

### Synopsis
```bash
php spp.php module:list [--app=AppName] [--filter=keyword] [--status=active|inactive] [--json]
```

### Extended Usage
The `module:list` command inspects the complete module ecosystem for a given application context, rendering details on versioning, scope, categories, active status, and compulsory constraints:
```bash
# List all modules for the current application
php spp.php module:list

# List all modules for a specific application context
php spp.php module:list --app=school1

# Filter modules by active status
php spp.php module:list --status=active

# Filter modules by keyword (matches name, public name, or category)
php spp.php module:list --filter=db

# Export module catalog as structured JSON for CI/CD or admin tooling
php spp.php module:list --json
```

### Options Available
- `--app=AppName`: Application context to evaluate. Defaults to the active application directory (`default`).
- `--filter=keyword`: Case-insensitive search filter matching module name, title, or category.
- `--status=active|inactive`: Filters output by module operational state.
- `--json`: Formats output as indented JSON without ASCII tables.

### Under the Hood Activity
- Uses `\SPP\Module::listAvailableModules()` to discover all manifests in `spp/modules/spp`, `spp/modules/optional`, `spp/modules/contrib`, and `src/<app>/modules`.
- Cross-references operational status against `modules.yml` and the `spp_modules` table.
- Evaluates compulsory invariants via `Module::isCompulsory()`.
- Read-only execution with zero database mutations or filesystem side effects.
