## `cache:warmup`

**Description**: Warm up common application caches

### Synopsis
```bash
php spp.php cache:warmup [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from CacheWarmupCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: \RecursiveIteratorIterator, \RecursiveDirectoryIterator.

