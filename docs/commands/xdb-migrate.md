## `xdb:migrate`

**Purpose**: Run SPP_XDB Database Migrations

### Synopsis
```bash
php spp.php xdb:migrate [OPTIONS]
```

### Options Available
- `--steps=` : Expects a value. Extracted via static analysis.
- `--rollback` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: SPP_XDB, MigrationManager.

