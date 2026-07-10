## workflow:dump

**Purpose**: Dump a registered workflow definition as a visual state graph in Mermaid.js or Graphviz DOT format.

### Synopsis
```bash
php spp.php workflow:dump <entity_type.bundle> [--format=mermaid|dot]
```

### Extended Usage
The `workflow:dump` command parses the active YAML or database workflow definitions and generates visual state machine diagrams. This allows developers, product managers, and systems architects to inspect transition graphs, verify dead-ends, and embed visual documentation directly into markdown or graph viewers.

### Options Available
- `<entity_type.bundle>`: The workflow key to dump (e.g., `node.article`, `wizard.onboarding`, `expense`).
- `--format=mermaid`: Outputs a Mermaid.js state diagram (default).
- `--format=dot`: Outputs a Graphviz DOT format definition.

### Under the Hood Activity
- **SAPI Guarding**: Strictly guarded by `isCLIOnly()` to ensure execution is blocked from web server contexts.
- **Workflow Discovery**: Calls `WorkflowManager::getWorkflow()` to pull the active workflow configuration from `APP_ETC_DIR/workflows`, `SPP_ETC_DIR/workflows`, Cache, or Database registries.
- **Console Output**: Formats and prints the structural graph definition directly to `stdout`.
