## `delete:app`

**Description**: Delete an SPP application context and all its data (files, config, caches, views)

### Synopsis
```bash
php spp.php delete:app [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php delete:app <AppName> [--force] [--keep-db] [--dry-run]

```

### Options
- `--force` : Boolean flag. Extracted via static analysis from DeleteAppCommand.php
- `--keep-db` : Boolean flag. Extracted via static analysis from DeleteAppCommand.php
- `--dry-run` : Boolean flag. Extracted via static analysis from DeleteAppCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates key components: \PDO, \RecursiveIteratorIterator, \RecursiveDirectoryIterator.

