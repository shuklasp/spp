## `lang:import`

**Description**: Import JSON language file into active database translation overrides

### Synopsis
```bash
php spp.php lang:import [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from LangImportCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Dynamically loads kernel modules: spplang.
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: translation import from JSON.
