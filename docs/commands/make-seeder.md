## `make:seeder`

**Description**: Create a new Database Seeder class

### Synopsis
```bash
php spp.php make:seeder [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from MakeSeederCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Interacts with the SPP database layer directly.
- Performs raw filesystem modifications (create/write/delete).
- Instantiates key components: Database.

