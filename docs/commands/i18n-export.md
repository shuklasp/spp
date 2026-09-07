## `i18n:export`

**Description**: Export translations for a specific locale to a JSON file.

### Synopsis
```bash
php spp.php i18n:export [OPTIONS]
```

### Options
- `--locale=` : Expects a value. Extracted via static analysis from I18nExportCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: \SPPMod\SPPDB\SPPDB.

