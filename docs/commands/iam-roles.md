## `iam:roles`

**Purpose**: List all Roles and Entity Role Assignments

### Synopsis
```bash
php spp.php iam:roles [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php iam:roles list

```

### Options Available
- `--json` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: SPPDB.

