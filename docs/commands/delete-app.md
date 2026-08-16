## `delete:app`

**Purpose**: Delete an SPP application context and all its data (files, config, caches, views)

### Synopsis
```bash
php spp.php delete:app [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php delete:app <AppName> [--force] [--keep-db] [--dry-run]

```

### Options Available
- `--force` : Boolean flag or option. Extracted via static analysis.
- `--keep-db` : Boolean flag or option. Extracted via static analysis.
- `--dry-run` : Boolean flag or option. Extracted via static analysis.
- `----force` : Boolean flag or option. Extracted via static analysis.
- `----keep-db` : Boolean flag or option. Extracted via static analysis.
- `----dry-run` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates internal components: \PDO, \RecursiveIteratorIterator, \RecursiveDirectoryIterator.

