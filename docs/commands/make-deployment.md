## `make:deployment`

**Description**: Generate Enterprise Docker and K8s scaffolding for the application.

### Synopsis
```bash
php spp.php make:deployment [OPTIONS]
```

### Options
- `--with-redis` : Boolean flag. Extracted via static analysis from MakeDeploymentCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).

