## `middleware:list`

**Purpose**: List the middleware pipeline for an app

### Synopsis
```bash
php spp.php middleware:list [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.
- Instantiates internal components: \ReflectionClass.

