## `i18n:export`

**Purpose**: Export translations for a specific locale to a JSON file.

### Synopsis
```bash
php spp.php i18n:export [OPTIONS]
```

### Options Available
- `--locale=` : Expects a value. Extracted via static analysis from I18nExportCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Interacts with the SPP relational database layer.
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: \SPPMod\SPPDB\SPPDB.

