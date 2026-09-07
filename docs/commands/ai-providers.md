## `ai:providers`

**Description**: List all registered AI providers

### Synopsis
```bash
php spp.php ai:providers [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from AiProvidersCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Dynamically loads kernel modules: sppai.
- Bootstraps a full application execution context (Scheduler::withContext).

