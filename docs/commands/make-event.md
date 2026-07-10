## `make:event`

**Description**: Create a new event entry and scaffold its handler

### Synopsis
```bash
php spp.php make:event [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:event <EventName> <HandlerClassName> [--app=appname] [--overridable] [--default-handler]

```

### Options
- `--overridable` : Boolean flag. Extracted via static analysis from MakeEventCommand.php
- `--default-handler` : Boolean flag. Extracted via static analysis from MakeEventCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates key components: event.

