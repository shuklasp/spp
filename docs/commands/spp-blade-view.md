# NAME
spp blade:view - Manage Blade views within an SPP application

# SYNOPSIS
`php spp.php blade:view <action> [name] [-a <app>]`

# PURPOSE
Provides a streamlined CLI interface to list, scaffold, or delete Blade `.blade.php` view templates within a specified application's resource directory.

# OPTIONS AVAILABLE
- `<action>` : The view operation to run. Valid options are `list`, `create`, `delete`. Defaults to `list`.
- `[name]` : The dot-notated or slashed name of the Blade view (e.g., `pages.home` or `pages/home`). Required for `create` and `delete`.
- `-a <app>` : Implicitly required. The application context must be set (via `\SPP\Scheduler::getContext()`) or an error is thrown. The `default` context is explicitly blocked.

# UNDER THE HOOD ACTIVITY
1. **Context Validation:** Fetches the app name from the scheduler context. If the context is missing or is `default`, it violently aborts, enforcing that Blade views belong to localized apps.
2. **Path Construction:** Defines the view root path as `SPP_APP_DIR/resources/{appName}/views`.
3. **List Action:** Scans the root view directory non-recursively using `glob()` for `*.blade.php` files, stripping the extension to print human-readable names.
4. **Create Action:** Translates dot-notation view names (e.g., `admin.dashboard`) to directory separators (`admin/dashboard.blade.php`). It verifies absence to prevent overwriting, creates any missing parent directory structures with `mkdir(..., 0777, true)`, and seeds the new file with boilerplate HTML referencing the view's name.
5. **Delete Action:** Resolves the path similarly, verifies existence, and permanently drops the file using `unlink()`.

# EXAMPLES
List all views in the dashboard app:
`php spp.php blade:view list -a dashboard`

Create a nested Blade view template:
`php spp.php blade:view create users.profile -a dashboard`

Delete a Blade view:
`php spp.php blade:view delete users.profile -a dashboard`
