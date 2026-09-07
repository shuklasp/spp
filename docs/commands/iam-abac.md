## `iam:abac`

**Description**: Manage Attribute-Based Access Control (ABAC) policies

### Synopsis
```bash
php spp.php iam:abac [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php iam:abac --action=create --param1=\
```

### Options
- `--json` : Boolean flag. Extracted via static analysis from ABACPolicyCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: SPPDB.

