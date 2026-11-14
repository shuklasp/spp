## workflow:dump

**Purpose**: Export and visualize an SPP workflow lifecycle definition as a graphical state diagram in Mermaid.js or Graphviz DOT format.

### Synopsis

```bash
php spp.php workflow:dump <workflow_name> [--format=mermaid|dot] [--file=<output_file>]
```

### Extended Usage

The `workflow:dump` command enables developers and auditors to visualize state machines, parallel markings, and valid state transitions defined across YAML configurations (`etc/apps/<AppName>/workflows/`) or the `spp_workflows` database table. It supports both associative dictionaries and sequential lists of states, automatically highlighting initial states, terminal states, and transition labels with guard annotations.

Examples:
```bash
# Print Mermaid flowchart directly to terminal standard output
php spp.php workflow:dump admission_workflow --format=mermaid

# Export Graphviz DOT graph to a visual file
php spp.php workflow:dump article_workflow --format=dot --file=var/reports/article_graph.dot
```

### Options Available

- `<workflow_name>`: The name of the workflow or entity bundle to render (e.g., `article`, `admission_workflow`).
- `--format=<mermaid|dot>`: Graph output format. Supported options are `mermaid` (default) and `dot`.
- `--file=<output_file>`: Optional destination path to write the generated graph file. If omitted, the diagram is rendered directly to stdout.

### Under the Hood Activity

1. **Workflow Resolution**: Ingests the workflow definition via `\SPPMod\SPPWorkflow\WorkflowManager::getWorkflow()`, automatically resolving entity aliases and `_workflow` suffixes.
2. **Schema Normalization**: Normalizes both associative dictionaries (`states: { draft: { label: ... } }`) and sequential state lists (`states: ['draft', 'review']`).
3. **Graph Synthesis**: Iterates over all declared transitions, identifying source and target states, guard conditions, and labels.
4. **File or Terminal Output**: Writes the formatted diagram to disk using atomic filesystem operations if `--file` is supplied, or outputs standard Markdown Mermaid / Graphviz code blocks to terminal stdout.
