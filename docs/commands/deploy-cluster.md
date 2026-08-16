## `deploy:cluster`

**Purpose**: Deploy to a multi-server cluster sequentially

### Synopsis
```bash
php spp.php deploy:cluster [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php deploy:cluster <cluster_name>

```

### Options Available
- `--force` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: DeployPushCommand.

