## env:mode

**Purpose**: Instantly switch the global environment error reporting mode between developer ignition debug view (`dev`) and secure production error pages (`prod`).

### Synopsis
```bash
php spp/spp.php env:mode <dev|prod>
```

### Extended Usage
The `env:mode` command provides developers and system administrators with a seamless, fast mechanism to toggle the active error reporting behavior of the SPP framework. 

When running an application locally, developers need complete transparency into fatal exceptions, stack traces, and variable states. However, in staging or production environments, exposing stack traces introduces severe security and information disclosure risks. `env:mode` acts as a high-speed toggle between these two distinct operational paradigms.

### Options Available
- `dev`: Sets `debug: true` in the global configuration. Activates the highly expressive, premium Ignition-style developer dashboard (`error_template.php`) featuring interactive stack traces, dynamic code snippets, request context inspection, and AI-powered actionable solutions.
- `prod`: Sets `debug: false` in the global configuration. Deactivates debug view and activates clean, secure, user-friendly 500 Internal Server Error pages (`500_template.php` / `500.php`).

### Under the Hood Activity
1. **File Verification**: Inspects `spp/etc/global-settings.yml` to confirm existence and write permissions.
2. **SAPI Guarding**: Enforces strict CLI-only execution via `isCLIOnly()` to guarantee the command cannot be executed or exploited via web requests.
3. **Regex Substitution**: Uses robust regular expressions to dynamically update or append the `debug: true|false` key-value directive inside the YAML configuration file.
4. **Immediate Activation**: Because `sppinit.php` parses `global-settings.yml` during the early bootstrap phase, changes take effect immediately across all active applications without requiring web server restarts.
