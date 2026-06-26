# NAME
`make:form` - Create a new SPP form definition

# SYNOPSIS
`php spp.php make:form <name> [--app=appname]`

# PURPOSE
The `make:form` command provisions a specialized PHP Form Controller skeleton. This controller structure is specifically tailored to process front-end user interactions, manage form validation states, and implement business logic upon submission.

# OPTIONS AVAILABLE
- `<name>` (string, required): The core name of the form (e.g. `Contact`). "Form" will be appended automatically if excluded.
- `--app=<appname>` (string, optional): Determines the target execution context.

# UNDER THE HOOD ACTIVITY
The command normalizes the input class name with `ucfirst()`, validating and enforcing a `Form` suffix. It resolves the absolute file path resolving to `class.{lowercase_form_name}.php` within the `forms` directory of the targeted application context.
It calculates a specific `$formRoute` (removing the Form suffix) that serves as the logical identifier. The generation utilizes the `form` stub format mapping the namespace, class structure, and specific routing identifiers directly into the file.
Furthermore, the CLI includes a UI bridge via `renderAdminUI()`. This method outputs raw HTML strings to allow visual generation of the form directly from the SPP web-based command console, passing parameters via JS to the `window.executeCommand()` global function.

# EXAMPLES
**1. Scaffold a User Login form:**
```bash
php spp.php make:form UserLogin --app=frontend
```
