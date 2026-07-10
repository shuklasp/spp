## `make:app`

**Description**: Create a new SPP application context

### Synopsis
```bash
php spp.php make:app [OPTIONS]
```

### Options
- `--mode=` : Expects a value. Extracted via static analysis from MakeAppCommand.php
- `--ai-blueprint=` : Expects a value. Extracted via static analysis from MakeAppCommand.php
- `--enterprise` : Boolean flag. Extracted via static analysis from MakeAppCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Performs raw filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Dynamically loads kernel modules: name, parikshak, sppqueue.
- Instantiates key components: SPP, \PDO, state, methods, Date.

