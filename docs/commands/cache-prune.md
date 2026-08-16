## `cache:prune`

**Purpose**: Prune expired cache items from storage

### Synopsis
```bash
php spp.php cache:prune [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.
- Interacts with the application cache layer (Redis/Memcached).

