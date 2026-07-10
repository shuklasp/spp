## `audit:lineage`

**Description**: Traverses and verifies cryptographic Merkle-DAG trace logs

### Synopsis
```bash
php spp.php audit:lineage [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from AuditLineageCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.

