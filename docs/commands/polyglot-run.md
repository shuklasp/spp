## `polyglot:run`

**Purpose**: Executes a specific polyglot service directly

### Synopsis
```bash
php spp.php polyglot:run [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php polyglot:run --path=<relative_path_to_service> [args...]

```

### Options Available
- `--path=` : Expects a value. Extracted via static analysis.
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes external system binaries or shell commands.

