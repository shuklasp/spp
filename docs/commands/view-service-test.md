## `view:service:test`

**Purpose**: Test an AJAX service endpoint from the CLI

### Synopsis
```bash
php spp.php view:service:test [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php view:service:test --name=<service> [--app=default] [--payload=
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from ViewServiceTestCommand.php.
- `--name=` : Expects a value. Extracted via static analysis from ViewServiceTestCommand.php.
- `--payload=` : Expects a value. Extracted via static analysis from ViewServiceTestCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.

