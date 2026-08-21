## `ai:make:workflow`

**Purpose**: Synthesize natural language business requirements into valid sppworkflow YAML definitions

### Synopsis
```bash
php spp.php ai:make:workflow [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php ai:make:workflow <workflow_name> \
```

### Options Available
- `--provider=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Dynamically loads SPP kernel modules: sppai.
- Bootstraps a full application execution context via Scheduler.

