## `site:install`

**Purpose**: Initialize the database and load default configurations for a specific profile.

### Synopsis
```bash
php spp.php site:install [OPTIONS]
```

### Options Available
- `--profile=` : Expects a value. Extracted via static analysis from SiteInstallCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: \SPPMod\SPPDB\SPPDB.

