## `view:page:remove`

**Description**: Remove a page route from an app

### Synopsis
```bash
php spp.php view:page:remove [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php view:page:remove --name=<route> [--app=default] [--source=yaml|db]

```

### Options
- `--app=` : Expects a value. Extracted via static analysis from ViewPageRemoveCommand.php
- `--name=` : Expects a value. Extracted via static analysis from ViewPageRemoveCommand.php
- `--source=` : Expects a value. Extracted via static analysis from ViewPageRemoveCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).

