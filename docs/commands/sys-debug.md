## `sys:debug`

**Purpose**: Toggle global framework debug mode (on|off)

### Synopsis
```bash
php spp.php sys:debug [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php sys:debug on|off

```

### Options Available
- `--settings` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).

