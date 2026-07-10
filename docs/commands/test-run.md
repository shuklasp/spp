## `test:run`

**Description**: Runs Parikshak evaluation for an entity or the whole suite

### Synopsis
```bash
php spp.php test:run [OPTIONS]
```

### Options
- `--coverage` : Boolean flag. Extracted via static analysis from TestRunCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Dynamically loads kernel modules: parikshak.
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: \SPPMod\Parikshak\Parikshak.

