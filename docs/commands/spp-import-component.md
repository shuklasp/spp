# NAME

`spp import:component` - Imports pristine air-gapped sovereign UI components

# SYNOPSIS

`php spp.php import:component <component_identifier> [--target=<app_context>]`

# PURPOSE

The `import:component` command mocks the extraction and installation of "Sovereign Exchange Bundles"—air-gapped, production-certified layout fragments. It generates a declarative, zero-JS reactivity bound Javascript module structure within the specified application context.

# OPTIONS AVAILABLE

- `<component_identifier>`: The unique namespace/name of the component to import (e.g., `UI/DataGrid`).
- `--target=<app_context>`: (Optional) The specific application context directory to install the component into. Defaults to `default`.

# UNDER THE HOOD ACTIVITY

When executed, `ImportComponentCommand` parses the command arguments to isolate the component name and the target application context. It sanitizes the target context using regex (`/[^a-zA-Z0-9_\-]/`) and forces it to lowercase. It also heavily sanitizes the component identifier (`/[^a-zA-Z0-9_\-\/]/`).
It constructs a destination directory path within the target app's source tree (`SPP_APP_DIR/src/<target>/components/<dirname>`). If the directory does not exist, it forcefully creates it using `@mkdir` recursively.
Next, it synthesizes a javascript file containing a dummy ES module default export. This javascript file is formatted as an inline template literal simulating an air-gapped component structure with standard SPP Sovereign Exchange CSS variables. The content is written to a `.sppux.js` file at the calculated path using `file_put_contents`.

# EXAMPLES

**Import a basic UI component:**
```bash
php spp.php import:component Form/Button
```

**Import a component into a specific app context:**
```bash
php spp.php import:component Layout/Sidebar --target=admin_panel
```
