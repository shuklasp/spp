## `deploy:push`

**Description**: Push the local project state to a remote SPP target server

### Synopsis
```bash
php spp.php deploy:push [OPTIONS]
```

### Options
- `--key=` : Expects a value. Extracted via static analysis from DeployPushCommand.php
- `--artifact=` : Expects a value. Extracted via static analysis from DeployPushCommand.php
- `--dry-run` : Boolean flag. Extracted via static analysis from DeployPushCommand.php
- `--no-db` : Boolean flag. Extracted via static analysis from DeployPushCommand.php
- `--no-files` : Boolean flag. Extracted via static analysis from DeployPushCommand.php
- `--force` : Boolean flag. Extracted via static analysis from DeployPushCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Performs raw filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates key components: \SPPMod\SPPDeploy\Scanner\ProjectScanner, \SPPMod\SPPDeploy\Scanner\DbScanner, \ZipArchive, \Exception, \SPPMod\SPPDB\SPPDB.

