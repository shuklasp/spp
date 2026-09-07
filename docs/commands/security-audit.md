## `security:audit`

**Purpose**: Audit framework and application configuration for security vulnerabilities, exposed secrets, and unhardened production settings.

### Synopsis
```bash
php spp.php security:audit [OPTIONS]
```

### Extended Usage
The `security:audit` command performs an automated, multi-vector security scan across both the SPP framework core and installed applications. It checks for common deployment misconfigurations, insecure file permissions, hardcoded or weak credentials, session cookie vulnerabilities, open directory listings, and database exposure in public document roots.

Practical examples:
```bash
# Perform a standard security posture audit for the active application
php spp.php security:audit

# Audit a specific application context (e.g., SPPDocs or Lekhak)
php spp.php security:audit --app=SPPDocs

# Strict mode: fail with a non-zero exit code on any warnings as well as failures
php spp.php security:audit --strict

# Output machine-readable JSON results for automated CI/CD security gating
php spp.php security:audit --json
```

### Options Available
- `--app=<appName>`: Target specific application context to evaluate. Defaults to `default` or active context.
- `--strict`: Strict mode. Causes the command to return exit code 1 if any warnings (`[WARN]`) are encountered.
- `--json`: Format output as a JSON object containing full diagnostic details for CI/CD pipelines.

### Under the Hood Activity
- Inspects filesystem permissions on `.env`, `spp/core`, and `spp/etc` directories.
- Analyzes environment configuration for entropy on `APP_KEY`, `APP_SECRET`, and `DB_PASSWORD`.
- Scans `public/` web document root for inadvertently exposed SQLite database files (`*.db`, `*.sqlite`).
- Validates active PHP session configuration for `cookie_httponly`, `cookie_secure`, and `cookie_samesite` compliance.
- Assesses debug mode flags against active environment mode (`APP_ENV=production`).
