## `lint`

**Description**: Run SPP native linter on a file

### Synopsis
```bash
php spp.php lint [OPTIONS]
```

### Options
- `--file=` : Expects a value. Extracted via static analysis from LintCommand.php
- `--json` : Boolean flag. Extracted via static analysis from LintCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.

