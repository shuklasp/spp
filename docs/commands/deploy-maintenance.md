## `deploy:maintenance`

**Description**: Toggle manual maintenance mode on a remote target or local environment

### Synopsis
```bash
php spp.php deploy:maintenance [OPTIONS]
```

### Options
- `--key=` : Expects a value. Extracted via static analysis from DeployMaintenanceCommand.php
- `--on` : Boolean flag. Extracted via static analysis from DeployMaintenanceCommand.php
- `--off` : Boolean flag. Extracted via static analysis from DeployMaintenanceCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).

