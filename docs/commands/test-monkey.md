## `test:monkey`

**Purpose**: Runs chaos monkey / fuzzing scenarios for an entity

### Synopsis
```bash
php spp.php test:monkey [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php test:monkey <EntityClass>

```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.
- `--entities` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Dynamically loads SPP kernel modules: parikshak.
- Bootstraps a full application execution context via Scheduler.
- Instantiates internal components: \SPPMod\Parikshak\Parikshak.

