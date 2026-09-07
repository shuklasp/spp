## `db:migration:verify-zero-downtime`

**Description**: Perform a dry-run analysis of database migration DDL statements to verify zero-downtime compliance and schema safety

### Synopsis
```bash
php spp.php db:migration:verify-zero-downtime [OPTIONS]
```

### Options
- `--path=` : Expects a value. Extracted via static analysis from VerifyZeroDowntimeCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.

