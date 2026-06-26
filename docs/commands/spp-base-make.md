# NAME
spp base-make - Abstract base command for scaffolding file generation

# SYNOPSIS
*This is an abstract class, not a direct command.*

# PURPOSE
Provides underlying file generation primitives (`BaseMakeCommand`) for 'make:' style scaffolding commands in the SPP CLI. It encapsulates stub loading, placeholder substitution, namespace resolution, and context resolution, allowing child commands to easily generate classes, configurations, or views from standard templates.

# OPTIONS AVAILABLE
- `--app=<appname>` : Standard application context override flag.

# UNDER THE HOOD ACTIVITY
Child scaffolding classes use `BaseMakeCommand` to resolve directories and namespaces cleanly.
1. **Directory Resolution:** `getTargetDir()` constructs absolute filesystem paths. If the app context is `default`, it points directly to `SPP_APP_DIR/spp/{subDir}`. Otherwise, it points to `SPP_APP_DIR/src/{app}/{subDir}`, while enforcing a special rule that `comp` (components) always stay within the `src/` directory instead of `resources/`.
2. **Namespace Resolution:** `getNamespace()` automatically derives PSR-4 compliant PHP namespaces based on the application context. `default` yields `SPP\SubNamespace` whereas a custom app yields `App\Appname\SubNamespace`.
3. **Stub Compilation:** `buildFromStub()` locates a `.stub` template file inside the CLI `stubs` directory. It reads the raw contents and loops through an associative array of replacements, substituting `{{key}}` tokens with provided string values. It ensures parent directories exist via recursive `mkdir()`, protects against overwriting existing files, and writes the compiled string via `file_put_contents()`.

# EXAMPLES
*Abstract class, no direct execution.*
