## `polyglot:list`

**Purpose**: Discovers and tabulates all registered polyglot service scripts, compiled handlers, and DSL files across all installed applications and shared service directories.

### Synopsis
```bash
php spp.php polyglot:list
```

### Extended Usage
The `polyglot:list` command recursively scans the application workspace for standalone polyglot scripts that can be invoked as services or view partials. It utilizes `\SPP\Core\Polyglot\LanguageRegistry` to dynamically match all registered file extensions (e.g. `.py`, `.js`, `.go`, `.rs`, `.zig`, `.rb`, `.ex`, `.lua`, `.jl`, `.r`, `.swift`, `.kt`, `.calc`, `.rule`).

```bash
# Discover all polyglot services in the workspace
php spp.php polyglot:list
```

Sample output:
```text
Discovering polyglot services...
+----------+--------------------------------------------------+
| Language | Path                                             |
+----------+--------------------------------------------------+
| PYTHON   | /services/data_science/recommendations.py        |
| NODE     | /src/SPPDocs/services/markdown_renderer.js       |
| RUST     | /services/compute/fast_matrix.rs                 |
| FORMULA  | /src/SchoolApp/services/discount_rules.calc      |
+----------+--------------------------------------------------+
```

Services listed by this command can be rendered or executed using `ViewController::renderPolyglotPartial()`, `PolyglotBridge::call()`, or template directives like `@polyglot('services/compute/fast_matrix.rs', $data)`.

### Options Available
This command operates without parameters and automatically detects all polyglot source files across standard service directories:
- `services/`
- `src/services/`
- `src/<AppName>/services/`

### Under the Hood Activity
- Queries `LanguageRegistry::getAllExtensions()` to construct dynamic extension filters.
- Traverses target service directories using recursive iterators.
- Resolves language drivers for each matching file and normalizes relative workspace paths.
- Formats and displays discovered services in an ANSI table.
