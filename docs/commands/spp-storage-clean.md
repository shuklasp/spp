# NAME

`storage:clean` - Clean up temporary files in storage.

# SYNOPSIS

`php spp storage:clean [--app=<appname>]`

# PURPOSE

The `storage:clean` command removes all temporary files stored within an application's localized temporary storage directory.

# OPTIONS AVAILABLE

- `--app=<appname>` (Optional): Target a specific application namespace context. Defaults to `default`.

# UNDER THE HOOD ACTIVITY

The command delegates the cleanup process to the localized application context:
1. **Argument Parsing**: It iterates through the CLI arguments looking for the `--app=` prefix to determine the application name.
2. **Context Setup**: It wraps the execution within `\SPP\Scheduler::withContext($appname, function() {...})` to ensure that paths and configurations behave relative to the designated application environment.
3. **Target Directory**: Inside the context, it locates the temporary storage folder at `SPP_APP_DIR . '/var/storage/temp'`.
4. **Directory Validation**: If the directory doesn't exist, it aborts cleanly and logs an informative message.
5. **Deletion**: It performs a glob scan (`*`) inside the temporary directory and deletes (`unlink()`) every file present.
6. **Summary Output**: It maintains a running count of unlinked files and echoes the total cleaned to the console.

# EXAMPLES

**Clean temporary files for the default app:**
```bash
php spp storage:clean
```

**Clean temporary files for the 'api' app:**
```bash
php spp storage:clean --app=api
```
