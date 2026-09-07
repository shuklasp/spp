## `group:create`

**Description**: Create a new shared resource group

### Synopsis
```bash
php spp.php group:create [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php group:create <group_name> [--extends=core] [--prefix=...]

```

### Options
- `--extends=` : Expects a value. Extracted via static analysis from GroupCreateCommand.php
- `--prefix=` : Expects a value. Extracted via static analysis from GroupCreateCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: shared.

