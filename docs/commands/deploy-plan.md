## `deploy:plan`

**Description**: Perform a dry run to view file changes and raw database SQL diffs before deploying

### Synopsis
```bash
php spp.php deploy:plan [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php deploy:plan <target_uri> [--key=YOUR_API_KEY] [--no-db]

```

### Options
- `--key=` : Expects a value. Extracted via static analysis from DeployPlanCommand.php
- `--no-db` : Boolean flag. Extracted via static analysis from DeployPlanCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: \SPPMod\SPPDeploy\Scanner\FileScanner, \SPPMod\SPPDeploy\Scanner\DbScanner, \SPPMod\SPPDB\SPPDB.

