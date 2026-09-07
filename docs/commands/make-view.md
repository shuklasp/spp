## `make:view`

**Description**: Create a new view definition (equivalent to Drupal Views).

### Synopsis
```bash
php spp.php make:view [OPTIONS]
```

### Options
- `--table=` : Expects a value. Extracted via static analysis from MakeViewCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: view, \SPPMod\SPPDB\SPPDB.

