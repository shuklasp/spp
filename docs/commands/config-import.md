## `config:import`

**Purpose**: Import database tables and settings from an exported SQL, SQLite, or XDB file

### Synopsis
```bash
php spp.php config:import [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Performs direct filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates internal components: \SPPMod\SPPDB\SPPDB, \PDO, \DOMDocument.

