# NAME

`storage:link` - Create symbolic links for public storage.

# SYNOPSIS

`php spp storage:link [--app=<appname>]`

# PURPOSE

The `storage:link` command generates a symbolic link from the public document root to the application's secure storage directory. This allows files securely saved in `var/storage/public` to be openly accessible via HTTP requests (e.g., uploaded avatars or documents).

# OPTIONS AVAILABLE

- `--app=<appname>` (Optional): Target a specific application context. Defaults to `default`.

# UNDER THE HOOD ACTIVITY

The command replicates common framework storage linking logic:
1. **Argument Parsing**: Parses CLI arguments for an `--app=<appname>` flag to dictate the active application.
2. **Context Activation**: Executes within a closure mapped to `\SPP\Scheduler::withContext($appname)`.
3. **Path Definition**: 
   - **Target**: Evaluates the real data location as `SPP_APP_DIR . '/var/storage/public'`.
   - **Link**: Sets the desired symlink placement at `SPP_APP_DIR . '/public/storage'`.
4. **Directory Creation**: If the target storage directory does not exist, it forcefully creates it using `mkdir()` with `0755` permissions.
5. **Validation**: It verifies if the link already exists at the target path, skipping creation if true.
6. **Symlink Generation**: Calls PHP's `symlink($target, $link)`.
   - *Note:* On Windows systems, executing this might require the terminal to be running with Administrative privileges depending on security policies.

# EXAMPLES

**Create the public storage symlink for the default app:**
```bash
php spp storage:link
```
