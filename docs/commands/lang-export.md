## `lang:export`

**Description**: Export active database translation overrides into JSON language file

### Synopsis
```bash
php spp.php lang:export [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from LangExportCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Dynamically loads kernel modules: spplang.
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: translation export to JSON.
