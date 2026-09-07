## `cache:stats`

**Description**: Display cache driver statistics

### Synopsis
```bash
php spp.php cache:stats [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from CacheStatsCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).

