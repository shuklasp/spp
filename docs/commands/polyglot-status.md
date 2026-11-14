## `polyglot:status`

**Purpose**: Inspects and audits the host runtime environment, discovering installed binaries, versions, and execution modes across all registered Universal Polyglot 2.0 language drivers.

### Synopsis
```bash
php spp.php polyglot:status
```

### Extended Usage
The `polyglot:status` command queries `\SPP\Core\Polyglot\LanguageRegistry` and uses `\SPP\Core\Polyglot\Platform` to dynamically locate compilers, interpreters, and runtime binaries across Windows, Linux, macOS, BSD, and containerized operating systems.

```bash
# Check status of all registered language runtimes
php spp.php polyglot:status
```

Sample output:
```text
Checking Polyglot runtime environment across active LanguageRegistry drivers...

+-------------------+---------+--------------------------------------------------+
| Language          | Status  | Discovered Path / Version                        |
+-------------------+---------+--------------------------------------------------+
| Python 3          | OK      | Python 3.12.1 (python.exe)                       |
| Node.js           | OK      | v20.10.0 (node.exe)                              |
| Go                | OK      | go version go1.21.5 windows/amd64 (go.exe)       |
| Rust              | OK      | rustc 1.75.0 (rustc.exe)                         |
| Ruby              | MISSING | Not found in PATH                                |
| Formula / Rules   | OK      | In-Process Sandboxed Engine                      |
+-------------------+---------+--------------------------------------------------+
```

Languages marked as `OK` can immediately be invoked via `ViewController::renderPolyglotPartial()`, `@polyglot()` Blade/Twig macros, or the `PolyglotBridge` API. In-process DSL engines (such as Formula) run directly within PHP's runtime without requiring external binaries.

### Options Available
This command requires no additional options or flags. It automatically inspects all YAML drivers registered in `etc/polyglot/` and active application directories (`etc/apps/<AppName>/polyglot/`).

### Under the Hood Activity
- Loads language manifests from `etc/polyglot/*.yml` and `etc/apps/*/polyglot/*.yml` via `LanguageRegistry::loadDrivers()`.
- Executes platform-aware binary discovery routines (`where.exe` on Windows, `which` on POSIX systems) via `Platform::findBinary()`.
- Executes version probes with standard flags (`--version`, `-v`) to capture active release details.
- Identifies in-process DSL grammar evaluators and flags them as zero-overhead in-process engines.
