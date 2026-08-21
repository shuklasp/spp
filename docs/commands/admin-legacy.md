## `admin:legacy`

**Purpose**: Manage Admin Legacy operations. Usage: admin:legacy <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php admin:legacy [OPTIONS]
```

### Options Available
- `--apps` : Boolean flag or option. Extracted via static analysis.
- `--enable_api` : Boolean flag or option. Extracted via static analysis.
- `--columns` : Boolean flag or option. Extracted via static analysis.
- `--fields` : Boolean flag or option. Extracted via static analysis.
- `--modules` : Boolean flag or option. Extracted via static analysis.
- `--name` : Boolean flag or option. Extracted via static analysis.
- `--status` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Performs direct filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates internal components: \\Exception, record, \SPPMod\SPPDB\SPPDB, RecursiveIteratorIterator, RecursiveDirectoryIterator, \ReflectionClass, \SPP\Module.
- Makes outbound HTTP requests to external APIs or services.

