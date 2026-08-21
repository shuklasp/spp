## `lint`

**Purpose**: Run SPP native linter on a file

### Synopsis
```bash
php spp.php lint [OPTIONS]
```

### Options Available
- `--file=` : Expects a value. Extracted via static analysis.
- `--json` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.

