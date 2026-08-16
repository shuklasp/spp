## `migrate:make`

**Purpose**: Generate a new database migration class.

### Synopsis
```bash
php spp.php migrate:make [OPTIONS]
```

### Options Available
- `--name=` : Expects a value. Extracted via static analysis.
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: database.

