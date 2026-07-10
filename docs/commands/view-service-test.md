## `view:service:test`

**Description**: Test an AJAX service endpoint from the CLI

### Synopsis
```bash
php spp.php view:service:test [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php view:service:test --name=<service> [--app=default] [--payload=
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from ViewServiceTestCommand.php
- `--name=` : Expects a value. Extracted via static analysis from ViewServiceTestCommand.php
- `--payload=` : Expects a value. Extracted via static analysis from ViewServiceTestCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).

