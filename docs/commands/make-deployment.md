## `make:deployment`

**Purpose**: Generate Enterprise Docker and K8s scaffolding for the application.

### Synopsis
```bash
php spp.php make:deployment [OPTIONS]
```

### Options Available
- `--with-redis` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).

