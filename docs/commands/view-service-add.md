## `view:service:add`

**Purpose**: Register a new AJAX service endpoint

### Synopsis
```bash
php spp.php view:service:add [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php view:service:add --name=<service> --script=<path> [--method=POST] [--app=default] [--source=yaml|db]

```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from ViewServiceAddCommand.php.
- `--name=` : Expects a value. Extracted via static analysis from ViewServiceAddCommand.php.
- `--script=` : Expects a value. Extracted via static analysis from ViewServiceAddCommand.php.
- `--method=` : Expects a value. Extracted via static analysis from ViewServiceAddCommand.php.
- `--source=` : Expects a value. Extracted via static analysis from ViewServiceAddCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.
- Instantiates internal components: AJAX.

