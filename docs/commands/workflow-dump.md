## `workflow:dump`

**Purpose**: Dump a workflow definition as a visual state graph (Mermaid.js or Graphviz DOT)

### Synopsis
```bash
php spp.php workflow:dump [OPTIONS]
```

### Options Available
- `--format=` : Expects a value. Extracted via static analysis.
- `--file=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Executes native PHP logic without major side-effects.

