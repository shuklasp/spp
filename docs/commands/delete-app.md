## `delete:app`

**Description**: Delete an SPP application context and all its data (files, config, caches, views)

### Synopsis
```bash
php spp.php delete:app <AppName> [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php delete:app <AppName> [--config-only] [--backup] [--force] [--keep-db] [--dry-run]
```

### Options
- `--config-only` : Only remove configuration, caches, and domain bindings; strictly preserve source code in `src/`.
- `--backup` : Create a full zip backup archive in `var/backups/apps/` before deleting.
- `--force` : Skip confirmation prompt.
- `--keep-db` : Preserve database tables (only remove files and config).
- `--dry-run` : Show what would be deleted without actually deleting anything.
- `--help` : Show command help and usage instructions.

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates key components: \PDO, \RecursiveIteratorIterator, \RecursiveDirectoryIterator.

