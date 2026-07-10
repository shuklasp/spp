## `deploy:build`

**Description**: Create a local deployment artifact bundle without pushing

### Synopsis
```bash
php spp.php deploy:build [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php deploy:build <target_uri> [--key=YOUR_API_KEY] [--no-db] [--no-files]

```

### Options
- `--key=` : Expects a value. Extracted via static analysis from DeployBuildCommand.php
- `--no-db` : Boolean flag. Extracted via static analysis from DeployBuildCommand.php
- `--no-files` : Boolean flag. Extracted via static analysis from DeployBuildCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: \SPPMod\SPPDeploy\Scanner\ProjectScanner, \SPPMod\SPPDeploy\Scanner\DbScanner, \ZipArchive, \Exception, \SPPMod\SPPDB\SPPDB.

