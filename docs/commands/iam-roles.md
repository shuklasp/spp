## `iam:roles`

**Description**: List all Roles and Entity Role Assignments

### Synopsis
```bash
php spp.php iam:roles [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php iam:roles list

```

### Options
- `--json` : Boolean flag. Extracted via static analysis from RoleCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: SPPDB.

