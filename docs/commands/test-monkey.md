## `test:monkey`

**Description**: Runs chaos monkey / fuzzing scenarios for an entity

### Synopsis
```bash
php spp.php test:monkey [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php test:monkey <EntityClass>

```

### Options
- `--app=` : Expects a value. Extracted via static analysis from TestMonkeyCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Dynamically loads kernel modules: parikshak.
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: \SPPMod\Parikshak\Parikshak.

