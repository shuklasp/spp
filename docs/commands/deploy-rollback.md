## `deploy:rollback`

**Description**: Roll back a remote target to a specific snapshot backup ID

### Synopsis
```bash
php spp.php deploy:rollback [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php deploy:rollback [target_uri] <backup_id> [--key=YOUR_API_KEY] [--force]

```

### Options
- `--key=` : Expects a value. Extracted via static analysis from DeployRollbackCommand.php
- `--force` : Boolean flag. Extracted via static analysis from DeployRollbackCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).

