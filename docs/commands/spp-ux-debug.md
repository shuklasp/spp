# NAME

`ux:debug` - Toggle SPP-UX verbose logging (on|off)

# SYNOPSIS

`php spp.php ux:debug [on|off]`

# PURPOSE

Serves as a bugfixing pillar for the frontend components of the SPP Framework. It seamlessly toggles diagnostic console logging natively within the browser by modifying the core `sppux.js` payload.

# OPTIONS AVAILABLE

- `[on|off]`: Specifies the desired state of debugging. Defaults to `on` if omitted.

# UNDER THE HOOD ACTIVITY

The command resolves the target JavaScript file located at `SPP_APP_DIR . '/spp/modules/spp/sppux/js/sppux.js'`. It first checks if the file exists on disk to prevent catastrophic read failures.

It loads the entire file contents into memory using `file_get_contents()`. If the requested state is `on`, it searches the content for the literal string `window.SPP_UX_DEBUG = true;`. If absent, it prepends this configuration flag directly to the top of the script. Conversely, if the state is `off`, it executes a simple string replacement using `str_replace()` to strip the `window.SPP_UX_DEBUG = true;\n` flag from the content. Finally, it persists the modified payload back to the filesystem using `file_put_contents()`, thereby enforcing the debugging state across all connected browser sessions.

# EXAMPLES

**Enable UX debugging:**
```bash
php spp.php ux:debug on
```

**Disable UX debugging:**
```bash
php spp.php ux:debug off
```
