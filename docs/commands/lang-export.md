## `lang:export`

**Purpose**: Export active database translation overrides into JSON language file

### Synopsis
```bash
php spp.php lang:export [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Dynamically loads SPP kernel modules: spplang.
- Bootstraps a full application execution context via Scheduler.

