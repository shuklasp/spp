## `cache:warmup`

**Purpose**: Warm up common application caches

### Synopsis
```bash
php spp.php cache:warmup [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from CacheWarmupCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.
- Interacts with the application cache layer (Redis/Memcached).

