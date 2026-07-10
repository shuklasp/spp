## ai:refactor:enterprise

**Purpose**: AI-powered automated refactoring daemon to modernize legacy code into strict SPP enterprise compliance.

### Synopsis

```bash
php spp.php ai:refactor:enterprise [--path=<path/to/scan>]
```

### Extended Usage

The `ai:refactor:enterprise` command acts as an automated software architect. It scans legacy PHP controllers and services, identifying non-compliant patterns such as raw inline HTML strings or missing telemetry spans. It then invokes `SPPAI` (Ollama) to automatically rewrite the file using standalone external partials (`renderPartial()`) and injecting `W3CTraceContext::startSpan()` calls.

Example:
```bash
php spp.php ai:refactor:enterprise --path=src/App/Controllers
```

### Options Available

- `--path=<path>`: Absolute or relative path to the directory or file to refactor. Defaults to `src/App/Controllers`.

### Under the Hood Activity

1. **Strict SAPI Guarding**: Validates secure CLI execution via `isCLIOnly(): bool`.
2. **File & Pattern Matching**: Recursively traverses the target path to inspect PHP files for inline HTML literals (e.g., `<div>`, `<span>`) and missing telemetry markers.
3. **AI Prompting & Tool Calling**: Constructs a specialized expert system prompt and passes the file content to `SPPAI::callTool()`.
4. **Automated Refactoring**: Validates the AI response for correct PHP syntax and overwrites the original file with the modernized, fully compliant code.
