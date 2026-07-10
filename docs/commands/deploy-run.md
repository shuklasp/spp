## `deploy:run`

**Description**: Securely execute an arbitrary shell command on the remote server

### Synopsis
```bash
php spp.php deploy:run [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php deploy:run [target_uri] \
```

### Options
- `--key=` : Expects a value. Extracted via static analysis from DeployRunCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.

