# delete:app

## NAME
delete:app - Delete an SPP application context and all its data

## SYNOPSIS
`php spp.php delete:app [AppNameToConfirm] [--force]`

## PURPOSE
Completely removes an existing SPP application, wiping its configuration, source code, and resource directories from the system.

## OPTIONS AVAILABLE
- `AppNameToConfirm`: The name of the application context to delete. Prompts interactively if omitted.
- `--force`: Bypass the standard "(y/N)" confirmation prompt.

## UNDER THE HOOD ACTIVITY
The command starts by validating the target app name, actively preventing deletion of protected system apps like `default` or `admin`. It requests manual confirmation via `prompt()` if `--force` is absent. It then systematically performs a recursive deletion of three specific directories using standard filesystem scanning (`scandir`, `unlink`, `rmdir`): `etc/apps/{appName}`, `src/{appName}`, and `resources/{appName}`. To complete the operation, it opens the master `spp/etc/global-settings.yml` registry with the Symfony YAML component, unsets the application entry under the `['apps']` node, and flushes the modified registry back to the disk.

## EXAMPLES
```bash
php spp.php delete:app storefront --force
```
