## `import:component`

**Description**: Imports pristine air-gapped sovereign UI components

### Synopsis
```bash
php spp.php import:component [OPTIONS]
```

### Options
- `--target=` : Expects a value. Extracted via static analysis from ImportComponentCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).

