## `group:create`

**Purpose**: Create a new shared resource group

### Synopsis
```bash
php spp.php group:create [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php group:create <group_name> [--extends=core] [--prefix=...]

```

### Options Available
- `--extends=` : Expects a value. Extracted via static analysis from GroupCreateCommand.php.
- `--prefix=` : Expects a value. Extracted via static analysis from GroupCreateCommand.php.
- `--shared_groups` : Boolean flag or option. Extracted via static analysis from GroupCreateCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: shared.

