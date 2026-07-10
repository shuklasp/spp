## `test`

**Description**: Run Parikshak Unit and Feature Tests

### Synopsis
```bash
php spp.php test [OPTIONS]
```

### Options
- `--coverage` : Boolean flag. Extracted via static analysis from TestCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: \SPPMod\SPPDB\SPPDB, SPPTestRunner.

