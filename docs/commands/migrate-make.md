## `migrate:make`

**Description**: Generate a new database migration class.

### Synopsis
```bash
php spp.php migrate:make [OPTIONS]
```

### Options
- `--name=` : Expects a value. Extracted via static analysis from MakeCommand.php
- `--app=` : Expects a value. Extracted via static analysis from MakeCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: database.

