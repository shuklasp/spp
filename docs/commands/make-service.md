## `make:service`

**Purpose**: Create a new service class

### Synopsis
```bash
php spp.php make:service [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:service <name> [--app=appname] [--lang=python]

```

### Options Available
- `--lang=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Instantiates internal components: service, MakePythonCommand, MakeNodeCommand, MakeGoCommand, MakeDotNetCommand, MakePerlCommand, MakeJavaCommand.

