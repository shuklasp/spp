## `polyglot:run`

**Description**: Executes a specific polyglot service directly

### Synopsis
```bash
php spp.php polyglot:run [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php polyglot:run --path=<relative_path_to_service> [args...]

```

### Options
- `--path=` : Expects a value. Extracted via static analysis from PolyglotRunCommand.php
- `--app=` : Expects a value. Extracted via static analysis from PolyglotRunCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes external system binaries or shell commands.

