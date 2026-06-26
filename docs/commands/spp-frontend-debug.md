# NAME

`spp frontend:debug` - Toggle Frontend CDN development mode (on|off)

# SYNOPSIS

`php spp.php frontend:debug [on|off]`

# PURPOSE

The `frontend:debug` command acts as a bugfixing pillar for React/Vue integrations. It manipulates the central SPP frontend loader to toggle between development and production builds retrieved from CDNs (such as esm.sh), enabling developers to utilize full React/Vue devtools and robust error messaging during local debugging.

# OPTIONS AVAILABLE

- `on` (default): Enables development mode, utilizing development builds from the CDN.
- `off`: Disables development mode, utilizing minified production builds from the CDN.

# UNDER THE HOOD ACTIVITY

When executed, `FrontendDebugCommand` determines the target state (`on` or `off`). It retrieves the contents of the central loader script located at `SPP_APP_DIR/spp/admin/js/spp-loader.js`. 
If switching `on`, it performs a literal string replacement of `'https://esm.sh/'` to `'https://esm.sh/?dev='`. This signals the esm.sh CDN to deliver the unminified, development-oriented versions of the ES modules.
If switching `off`, it reverses this operation, replacing `'https://esm.sh/?dev='` back to `'https://esm.sh/'` so that production-optimized, minified modules are requested. 
The updated content is immediately flushed back to disk using `file_put_contents`.

# EXAMPLES

**Enable frontend development mode:**
```bash
php spp.php frontend:debug on
```

**Disable frontend development mode (production):**
```bash
php spp.php frontend:debug off
```
