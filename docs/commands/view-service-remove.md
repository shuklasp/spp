## `view:service:remove`

**Purpose**: Remove an AJAX service endpoint from an app

### Synopsis
```bash
php spp.php view:service:remove [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php view:service:remove --name=<service> [--app=default] [--source=yaml|db]

```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from ViewServiceRemoveCommand.php.
- `--name=` : Expects a value. Extracted via static analysis from ViewServiceRemoveCommand.php.
- `--source=` : Expects a value. Extracted via static analysis from ViewServiceRemoveCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.

