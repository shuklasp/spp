## `event:fire`

**Description**: Trigger a specific event manually

### Synopsis
```bash
php spp.php event:fire [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php event:fire --event=<event_name> [--payload=<json>]

```

### Options
- `--app=` : Expects a value. Extracted via static analysis from EventFireCommand.php
- `--event=` : Expects a value. Extracted via static analysis from EventFireCommand.php
- `--payload=` : Expects a value. Extracted via static analysis from EventFireCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).

