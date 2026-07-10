## `i18n:import`

**Description**: Import translations from a JSON file into the database.

### Synopsis
```bash
php spp.php i18n:import [OPTIONS]
```

### Options
- `--locale=` : Expects a value. Extracted via static analysis from I18nImportCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Instantiates key components: \SPPMod\SPPDB\SPPDB.

