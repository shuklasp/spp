## `di:list`

**Description**: List the Dependency Injection container bindings

### Synopsis
```bash
php spp.php di:list [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from DiListCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: \ReflectionClass.

