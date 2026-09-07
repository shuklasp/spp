## `deploy:cluster`

**Description**: Deploy to a multi-server cluster sequentially

### Synopsis
```bash
php spp.php deploy:cluster [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php deploy:cluster <cluster_name>

```

### Options
- `--force` : Boolean flag. Extracted via static analysis from DeployClusterCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: DeployPushCommand.

