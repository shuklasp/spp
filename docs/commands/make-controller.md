# NAME
`make:controller` - Create a new controller class

# SYNOPSIS
`php spp.php make:controller <name> [--app=appname] [--resource]`

# PURPOSE
The `make:controller` command provisions a new PHP controller class utilized to handle HTTP routing and request processing logic. This creates a standardized skeleton that connects URLs to specific PHP methods seamlessly within the SPP MVC lifecycle.

# OPTIONS AVAILABLE
- `<name>` (string, required): The core name of the controller. "Controller" will be automatically appended if missing (e.g. `User` becomes `UserController`).
- `--app=<appname>` (string, optional): Specify the application context namespace (resolving to `src/{app_name}/controllers/`).
- `--resource` (flag, optional): Indication flag. Note: The current execution stub parses this argument but currently delegates the actual implementation entirely to the base `controller` stub.

# UNDER THE HOOD ACTIVITY
Upon execution, it retrieves the current application context and transforms the provided name via `ucfirst()`, ensuring `Controller` is suffixed to the string. The file creation explicitly generates a prefix style filename: `class.{lowercase_controller_name}.php`. It extracts a `$routeName` variable by stripping the "Controller" suffix from the name and converting it to lowercase, which is injected into the stub to provide a default route path. The actual code generation utilizes `buildFromStub('controller', ...)` mapping the `namespace`, `className`, and `routeName`. The CLI output also includes a unique `renderAdminUI()` function, which dynamically renders an HTML-based interactive form bridging the web-based Administrator Console to the CLI utility using JavaScript `window.executeCommand`.

# EXAMPLES
**1. Scaffold an Auth controller:**
```bash
php spp.php make:controller Auth --app=admin
```
