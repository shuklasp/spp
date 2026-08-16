## `ai:providers`

**Purpose**: List all registered AI providers

### Synopsis
```bash
php spp.php ai:providers [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Dynamically loads SPP kernel modules: sppai.
- Bootstraps a full application execution context via Scheduler.

