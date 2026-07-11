## mesh:add

**Purpose**: Mounts a legacy application as a passthrough route in the WebOS Mesh.

### Synopsis

```bash
php spp.php mesh:add <uri> <target> [--integration=partial|none] [--features=feature1,feature2]
```

### Extended Usage

The `mesh:add` command allows you to safely mount massive legacy applications (like WordPress or Magento) directly into the SPP routing tree without requiring you to rewrite any of their code.

When you mount a legacy app via the Mesh, SPP acts as a smart reverse-proxy. It intercepts the HTTP request, optionally injects "A La Carte" features (like SSO or UI components), and then directly includes the legacy application's entry script. This guarantees 100% legacy compliance while still delivering modern WebOS capabilities.

### Options Available

- `<uri>`: (Required) The URL route that SPP should listen to (e.g. `/blog`).
- `<target>`: (Required) The absolute filesystem path to the legacy application's entry file (e.g. `/var/www/wordpress/index.php`).
- `--integration`: (Optional) The integration level. Defaults to `none`. Set to `partial` to enable feature injection.
- `--features`: (Optional) A comma-separated list of features to inject into the legacy app.
  - `sso_auth`: Hydrates the PHP session state before inclusion.
  - `ui_mesh`: Injects the global SPP WebOS navigation header.
  - `hardware_quota`: Enforces strict RAM limits on the legacy app via `ResourceManager`.
  - `security_headers`: Injects strict HTTP headers (X-Frame-Options, XSS-Protection) into the response.
  - `telemetry`: Starts a W3C Trace Context span to log legacy app performance into SPP's monitoring suite.

### Under the Hood Activity

1. The command parses the arguments and reads the centralized `etc/mesh.yml` configuration file.
2. It injects the new routing definition and writes the file safely to disk.
3. It instantly triggers `KernelCompiler::compile()` to flatten the YAML into a native PHP array, ensuring 0ms boot overhead for FastCGI environments.
