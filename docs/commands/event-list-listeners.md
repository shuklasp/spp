## `event:list-listeners`

**Description**: List all registered global event listeners

### Synopsis
```bash
php spp.php event:list-listeners [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from EventListListenersCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: \ReflectionClass.

