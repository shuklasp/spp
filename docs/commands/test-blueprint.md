## `test:blueprint`

**Purpose**: Generate a structural blueprint for an entity

### Synopsis
```bash
php spp.php test:blueprint [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php test:blueprint <EntityClass>

```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Dynamically loads SPP kernel modules: parikshak.
- Bootstraps a full application execution context via Scheduler.
- Instantiates internal components: \SPPMod\Parikshak\Parikshak.

