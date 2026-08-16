## `event:fire`

**Purpose**: Trigger a specific event manually

### Synopsis
```bash
php spp.php event:fire [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php event:fire --event=<event_name> [--payload=<json>]

```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.
- `--event=` : Expects a value. Extracted via static analysis.
- `--payload=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.

