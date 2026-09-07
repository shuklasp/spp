## `test:blueprint`

**Description**: Generate a structural blueprint for an entity

### Synopsis
```bash
php spp.php test:blueprint [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php test:blueprint <EntityClass>

```

### Options
- `--app=` : Expects a value. Extracted via static analysis from TestBlueprintCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Dynamically loads kernel modules: parikshak.
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: \SPPMod\Parikshak\Parikshak.

