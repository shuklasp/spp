## `xdb:seed`

**Description**: Run SPP_XDB Database Seeders

### Synopsis
```bash
php spp.php xdb:seed [OPTIONS]
```

### Options
- `--class=` : Expects a value. Extracted via static analysis from XdbSeedCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: SPP_XDB, SeederManager.

