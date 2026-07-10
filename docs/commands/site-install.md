## `site:install`

**Description**: Initialize the database and load default configurations for a specific profile.

### Synopsis
```bash
php spp.php site:install [OPTIONS]
```

### Options
- `--profile=` : Expects a value. Extracted via static analysis from SiteInstallCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: \SPPMod\SPPDB\SPPDB.

