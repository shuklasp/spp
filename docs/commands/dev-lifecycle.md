## `dev:lifecycle`

**Purpose**: Manage Dev Lifecycle operations. Usage: admin:lifecycle <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php dev:lifecycle [OPTIONS]
```

### Options Available
- `--environments` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates internal components: \SPP\Module.
- Makes outbound HTTP requests to external APIs or services.

