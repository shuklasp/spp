## `make:ux-component`

**Description**: Scaffold a new SPP-UX reactive component

### Synopsis
```bash
php spp.php make:ux-component [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:ux-component <ComponentName> [--template=external]

```

### Options
- `--template=external` : Boolean flag. Extracted via static analysis from MakeUXComponentCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: reactive, SPP.

