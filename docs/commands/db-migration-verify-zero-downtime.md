## `db:migration:verify-zero-downtime`

**Purpose**: Perform a dry-run analysis of database migration DDL statements to verify zero-downtime compliance and schema safety

### Synopsis
```bash
php spp.php db:migration:verify-zero-downtime [OPTIONS]
```

### Options Available
- `--path=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.

