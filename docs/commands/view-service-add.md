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
- `--app=` : Expects a value. Extracted via static analysis.
- `--name=` : Expects a value. Extracted via static analysis.
- `--script=` : Expects a value. Extracted via static analysis.
- `--method=` : Expects a value. Extracted via static analysis.
- `--source=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.
- Instantiates internal components: AJAX.

