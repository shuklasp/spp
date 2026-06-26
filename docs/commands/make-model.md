# NAME
`make:model` - Create a new model class (Fluent-ready)

# SYNOPSIS
`php spp.php make:model <name> [--app=appname] [--table=tablename]`

# PURPOSE
The `make:model` command provisions a new standard PHP Model class pre-configured to utilize the SPP Fluent Query Builder ecosystem. This provides a clean, object-oriented abstraction to interact with database tables natively.

# OPTIONS AVAILABLE
- `<name>` (string, required): The root name of the model (e.g. `User`).
- `--app=<appname>` (string, optional): Dictates the application context namespace (resolving to `src/{app_name}/models/`).
- `--table=<tablename>` (string, optional): Binds the model to a specific database table. If omitted, the table name defaults to the pluralized lowercase version of the model name (e.g. `users`).

# UNDER THE HOOD ACTIVITY
Upon execution, it normalizes the class name via `ucfirst()`. It scans the CLI arguments specifically for the `--table=` parameter; if missing, it computes the fallback string by appending an `s` to the lowercase class name.
It targets the `models` directory relative to the resolved application context and structures the output file as `class.{lowercase_name}.php`. 
Using the `buildFromStub()` mechanism against the `model` stub template, it injects the `namespace`, `className`, and `tableName` values directly into the static properties of the newly written PHP file.

# EXAMPLES
**1. Scaffold a fluent-ready User model:**
```bash
php spp.php make:model User --table=spp_users
```
