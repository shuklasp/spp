## ai:make:workflow

**Purpose**: Synthesizes natural language business requirements into valid sppworkflow YAML definitions using AI capabilities.

### Synopsis

```bash
php spp.php ai:make:workflow <workflow_name> "<prompt/description>" [--app=AppName] [--provider=ollama]
```

### Extended Usage

The `ai:make:workflow` command integrates directly with the `sppai` module to automatically generate complete enterprise workflow definitions. Rather than manually crafting states, transitions, parallel markings, Saga compensations, and SLA timeouts, developers provide a natural language description of the business process. The AI engine synthesizes a compliant YAML structure complete with educational tutorial comments.

Example:
```bash
php spp.php ai:make:workflow order_fulfillment "Order goes from draft to paid to shipped. If cancelled, invoke refund compensation. Timeout after 48 hours." --provider=ollama
```

### Options Available

- `<workflow_name>`: The target filename for the workflow definition (e.g., `order_fulfillment`). Automatically appends `.yml` if omitted.
- `"<prompt/description>"`: A detailed natural language description of the workflow lifecycle, states, rules, and SLA expectations.
- `--app=<AppName>`: Specifies the application context. Defaults to `default`.
- `--provider=<provider>`: Overrides the AI provider used for generation. Defaults to `ollama` (or the application config `ai_workflow_provider`).

### Under the Hood Activity

1. **Strict CLI Guarding**: Evaluates `isCLIOnly()` to guarantee the command is executing exclusively within the CLI SAPI.
2. **Context Resolution**: Enters the specified application context using `\SPP\Scheduler::withContext()`.
3. **AI Provider Query**: Loads the `sppai` module and issues an LLM prompt requesting structured YAML generation adhering to SPP Workflow standards.
4. **Filesystem Write**: Appends standard tutorial documentation header and writes the generated YAML definition to `etc/apps/<AppName>/workflows/<workflow_name>.yml` (or `etc/workflows/` for `default`).
