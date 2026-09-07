## `ai:make:workflow`

**Description**: Synthesize natural language business requirements into valid sppworkflow YAML definitions

### Synopsis
```bash
php spp.php ai:make:workflow [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php ai:make:workflow <workflow_name> \
```

### Options
- `--provider=` : Expects a value. Extracted via static analysis from MakeAiWorkflowCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Dynamically loads kernel modules: sppai.
- Bootstraps a full application execution context (Scheduler::withContext).

