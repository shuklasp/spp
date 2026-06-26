# NAME
`make:live-component` - Create a new Live Component class

# SYNOPSIS
`php spp.php make:live-component <name> [--app=appname]`

# PURPOSE
The `make:live-component` command provisions a PHP class for the SPPLive subsystem (conceptually similar to Livewire). These components enable complex, interactive, and reactive server-side state mutations transmitted dynamically to the frontend without requiring custom JavaScript implementations.

# OPTIONS AVAILABLE
- `<name>` (string, required): The target name of the component (e.g. `UserSearch`, `InteractiveTable`).
- `--app=<appname>` (string, optional): Determines the target execution context.

# UNDER THE HOOD ACTIVITY
Upon execution, it validates the name, normalizes it using `ucfirst()`, and retrieves the relevant application context via `getContext()`.
It targets the `live` directory within the application context, naming the file explicitly as `class.{lowercase_component_name}.php`.
The `buildFromStub()` compiler is executed using the `livecomponent` stub format, writing the properly namespaced class to disk.
Furthermore, this command includes the internal `renderAdminUI()` function, empowering administrators to invoke this command through a visual HTML interface via the SPP Admin Panel utilizing the `window.executeCommand` JavaScript bridge.

# EXAMPLES
**1. Create a reactive search component:**
```bash
php spp.php make:live-component SearchFilter --app=frontend
```
