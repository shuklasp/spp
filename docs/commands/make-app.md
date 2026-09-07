## `make:app`

**Description**: Create a new SPP application context

### Synopsis
```bash
php spp.php make:app [OPTIONS]
```

### Options
- `--enterprise` : Boolean flag. Extracted via static analysis from MakeAppCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates key components: SPP.

