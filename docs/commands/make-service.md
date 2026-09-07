## `make:service`

**Description**: Create a new service class

### Synopsis
```bash
php spp.php make:service [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:service <name> [--app=appname] [--lang=python]

```

### Options
- `--lang=` : Expects a value. Extracted via static analysis from MakeServiceCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Instantiates key components: service, MakePythonCommand, MakeNodeCommand, MakeGoCommand, MakeDotNetCommand.

