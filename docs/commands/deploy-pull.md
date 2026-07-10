## `deploy:pull`

**Description**: 

### Synopsis
```bash
php spp.php deploy:pull [OPTIONS]
```

### Options
- `--key=` : Expects a value. Extracted via static analysis from DeployPullCommand.php
- `--force` : Boolean flag. Extracted via static analysis from DeployPullCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Performs raw filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates key components: \ZipArchive, \Exception, \SPPMod\SPPDB\SPPDB.

