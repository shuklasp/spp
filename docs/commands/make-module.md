## `make:module`

**Description**: Create a new SPP module (System or App level)

### Synopsis
```bash
php spp.php make:module [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:module <name> [--scope=spp|optional|contrib|app] [--app=AppName]

```

### Options
- `--scope=` : Expects a value. Extracted via static analysis from MakeModuleCommand.php
- `--app=` : Expects a value. Extracted via static analysis from MakeModuleCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: SPP, MyService.

