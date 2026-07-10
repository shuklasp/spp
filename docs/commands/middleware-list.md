## `middleware:list`

**Description**: List the middleware pipeline for an app

### Synopsis
```bash
php spp.php middleware:list [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from MiddlewareListCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: \ReflectionClass.

