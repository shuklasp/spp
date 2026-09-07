## `config:export`

**Description**: Export database tables and global settings to SQL, SQLite, or XDB format

### Synopsis
```bash
php spp.php config:export [OPTIONS]
```

### Options
No static options detected.

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Performs raw filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates key components: \SPPMod\SPPDB\SPPDB, \PDO, \DOMDocument.

