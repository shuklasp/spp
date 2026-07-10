## `workflow:dump`

**Description**: Dump a workflow definition as a visual state graph (Mermaid.js or Graphviz DOT)

### Synopsis
```bash
php spp.php workflow:dump [OPTIONS]
```

### Options
- `--format=` : Expects a value. Extracted via static analysis from WorkflowDumpCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Executes native PHP logic without major side-effects or external dependencies.

