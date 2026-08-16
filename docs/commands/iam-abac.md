## `iam:abac`

**Purpose**: Manage Attribute-Based Access Control (ABAC) policies

### Synopsis
```bash
php spp.php iam:abac [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php iam:abac --action=create --param1=\
```

### Options Available
- `--json` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: SPPDB.

