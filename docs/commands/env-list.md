## `env:list`

**Purpose**: List all environment and configuration variables for an app context

### Synopsis
```bash
php spp.php env:list [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.
- Instantiates internal components: \ReflectionClass.

