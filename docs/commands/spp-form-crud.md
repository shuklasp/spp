# NAME

`spp form:crud` - Manage SPP forms (list, create, edit, delete)

# SYNOPSIS

`php spp.php form:crud [action] [options]`

# PURPOSE

The `form:crud` command provides a generalized CLI interface for managing form definitions in the SPP framework. Forms in SPP are stored as declarative configuration files (usually YAML or XML) within the application's configuration directory. This command allows you to view, modify, and list these form definitions programmatically.

# OPTIONS AVAILABLE

Although the command directly maps to `handleCrud` inherited from `BaseElementCommand`, standard actions generally include:
- `list`: Lists all available forms within the app's context.
- Additional actions (like `create`, `edit`, `delete`) as inherited by the base class.

# UNDER THE HOOD ACTIVITY

The `FormCrudCommand` class extends `BaseElementCommand`, hooking into a generic CRUD processing pipeline for application elements. 
When executed, it delegates directly to `$this->handleCrud('form', $args)`. To locate form files, the `getElementPath` method resolves the application context using `App::getApp($appname)` and maps the component name to `<app_conf_dir>/forms/<name>.yml`.
When listing forms via `listElements`, the command scans the `<app_conf_dir>/forms` directory using `glob()` with a brace pattern `*.{yml,yaml,xml}` to find all available declarative form schemas, returning just their base names. The generic parent class utilizes these endpoints to present the interactive or argument-driven CRUD operations.

# EXAMPLES

**List all application forms:**
```bash
php spp.php form:crud list
```
