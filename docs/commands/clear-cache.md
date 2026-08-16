## `clear:cache`

**Purpose**: Clear the application file/redis cache

### Synopsis
```bash
php spp.php clear:cache [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.
- Interacts with the application cache layer (Redis/Memcached).

