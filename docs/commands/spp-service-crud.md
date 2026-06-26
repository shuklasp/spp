# NAME

`service:crud` - Manage SPP services (list, create, edit, delete).

# SYNOPSIS

`php spp service:crud [options]`

# PURPOSE

The `service:crud` command provides an interactive or programmatic way to scaffold, list, edit, or delete SPP service classes. Services in SPP encapsulate business logic and are kept in the `services` directory of an application's source.

# OPTIONS AVAILABLE

Options are inherited from the parent `BaseElementCommand`, allowing arguments standard to CRUD CLI utilities such as defining the action type (create, list, delete, edit), the name of the service, and the application context.

# UNDER THE HOOD ACTIVITY

This command extends `BaseElementCommand` and delegates its primary execution to `$this->handleCrud('service', $args)`. When interacting with services, the following specific behaviors apply:
1. **Path Resolution**: When looking up or creating a service, `getElementPath` maps the requested service name to the destination file. E.g., for an app `default`, a service named `Mailer` maps to `[APP_DIR]/src/services/class.mailer.php`.
2. **Listing Elements**: The `listElements` method utilizes `glob()` to scan the `services` directory for any file matching `class.*.php`. The prefix `class.` is stripped to return human-readable service names.
3. **Template Scaffolding**: When creating a new service, `createElementTemplate` is invoked. It generates a PHP class definition belonging to the simplistic namespace `App\Default\Services`. The template includes a basic `handle()` method skeleton. The directory is created automatically if it does not exist before writing the generated code via `file_put_contents`.

# EXAMPLES

**Run the interactive CRUD manager for services:**
```bash
php spp service:crud
```
*(Exact flags depend on `BaseElementCommand` implementation, commonly enabling inline creation/deletion).*
