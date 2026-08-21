## `dev:xdb`

**Purpose**: Manage Dev XDB operations. Usage: admin:xdb <action> [--payload=...] [--json]

### Synopsis
```bash
php spp.php dev:xdb [OPTIONS]
```

### Options Available
No static options detected for this command.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: \SPPMod\SPPXDB\SPP_XDB, \SPPMod\SPPXDB\MigrationManager, \SPPMod\SPPXDB\SeederManager.

