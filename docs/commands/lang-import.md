## `lang:import`

**Purpose**: Import JSON language file into active database translation overrides

### Synopsis
```bash
php spp.php lang:import [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Dynamically loads SPP kernel modules: spplang.
- Bootstraps a full application execution context via Scheduler.

