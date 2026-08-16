## `make:controller`

**Purpose**: Create a new controller class

### Synopsis
```bash
php spp.php make:controller [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:controller <name> [--app=appname] [--resource]

```

### Options Available
- `--resource` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: controller.

