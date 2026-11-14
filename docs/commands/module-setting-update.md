## `module:setting:update`

**Purpose**: Update a persistent configuration setting for a specific module with validation.

### Synopsis
```bash
php spp.php module:setting:update <modulename> <key> <value>
```

### Extended Usage
The `module:setting:update` command validates and persists a new setting value into the active application's module configuration file (`etc/apps/<app>/modsconf/<modulename>/config.yml` or global `modsconf/`). The value is cast according to the setting's schema definition in `module.yml`.

#### Examples:
```bash
# Update the database driver for sppdb
php spp.php module:setting:update sppdb dbtype sqlite

# Update cache TTL setting
php spp.php module:setting:update sppcache default_ttl 3600
```

### Options Available
- `<modulename>` (Argument, required): Internal name or directory basename of the module.
- `<key>` (Argument, required): Configuration parameter key name.
- `<value>` (Argument, required): New value to assign and persist.

### Under the Hood Activity
1. **Schema Validation**: Validates the proposed value against type constraints defined in `module.yml` settings.
2. **Persistence**: Writes the updated setting through `\SPP\Core\ModuleConfigManager::setConfig($key, $val, $modname)`.
3. **Cache Invalidation**: Invalidates the in-memory and file-based module configuration caches to immediately reflect the change.
