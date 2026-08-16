## `test`

**Purpose**: Run Parikshak Unit and Feature Tests

### Synopsis
```bash
php spp.php test [OPTIONS]
```

### Options Available
- `--coverage` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Instantiates internal components: \SPPMod\SPPDB\SPPDB, SPPTestRunner.

