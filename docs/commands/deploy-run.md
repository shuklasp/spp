## `deploy:run`

**Purpose**: Securely execute an arbitrary shell command on the remote server

### Synopsis
```bash
php spp.php deploy:run [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php deploy:run [target_uri] \
```

### Options Available
- `--key=` : Expects a value. Extracted via static analysis.
- `--status` : Boolean flag or option. Extracted via static analysis.
- `--exit_code` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.

