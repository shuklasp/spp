## `xdb:migrate`

**Description**: Run SPP_XDB Database Migrations

### Synopsis
```bash
php spp.php xdb:migrate [OPTIONS]
```

### Options
- `--steps=` : Expects a value. Extracted via static analysis from XdbMigrateCommand.php
- `--rollback` : Boolean flag. Extracted via static analysis from XdbMigrateCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: SPP_XDB, MigrationManager.

