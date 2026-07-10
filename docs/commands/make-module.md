## `make:module`

**Description**: Create a new SPP module

### Synopsis
```bash
php spp.php make:module [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:module <name> [--scope=spp|contrib|app]

```

### Options
- `--scope=` : Expects a value. Extracted via static analysis from MakeModuleCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: SPP.

