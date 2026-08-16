## `make:blade-project`

**Purpose**: Scaffold a new Blade-enabled SPP application

### Synopsis
```bash
php spp.php make:blade-project [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:blade-project <app_name>

```

### Options Available
- `----force` : Boolean flag or option. Extracted via static analysis.
- `--logout` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Instantiates internal components: Blade, SPP, app.

