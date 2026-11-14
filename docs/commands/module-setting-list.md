## `module:setting:list`

**Purpose**: Inspect and tabulate configuration settings, data types, and default values for an active module.

### Synopsis
```bash
php spp.php module:setting:list <modulename>
```

### Extended Usage
The `module:setting:list` command displays all configurable settings registered in a module's manifest (`settings:` block). It renders a formatted ASCII table showing the setting key, data type (`text`, `select`, `password`, `boolean`), current effective value (resolved via `ModuleConfigManager`), and the manifest default.

#### Examples:
```bash
# List all settings for the sppdb database module
php spp.php module:setting:list sppdb

# Inspect settings for auth or session modules
php spp.php module:setting:list sppauth
```

### Options Available
- `<modulename>` (Argument, required): Internal name or folder name of the target module.

### Under the Hood Activity
1. **Module Introspection**: Retrieves the module instance via `\SPP\Module::getModule($modname)`.
2. **Schema Resolution**: Calls `$mod->getSettingsDefinition()` to extract keys and validation constraints.
3. **Layered Resolution**: Queries `\SPP\Core\ModuleConfigManager::getConfig($key, $modname)` to resolve effective configuration across all 7 resolution layers.
4. **Tabular Rendering**: Formats and outputs the records via `$this->table()`.
