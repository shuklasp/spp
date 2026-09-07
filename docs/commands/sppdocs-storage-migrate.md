## `sppdocs:storage:migrate`

**Description**: Migrate SPPDocs project issues between Flat-File JSON and SQLite storage

### Synopsis
```bash
php spp.php sppdocs:storage:migrate [OPTIONS]
```

### Options
- `--project=` : Expects a value. Extracted via static analysis from SPPDocsStorageMigrateCommand.php
- `--to=` : Expects a value. Extracted via static analysis from SPPDocsStorageMigrateCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: JsonStorageDriver, SqliteStorageDriver.

