## `make:event`

**Purpose**: Create a new event entry and scaffold its handler

### Synopsis
```bash
php spp.php make:event [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php make:event <EventName> <HandlerClassName> [--app=appname] [--overridable] [--default-handler]

```

### Options Available
- `--overridable` : Boolean flag or option. Extracted via static analysis.
- `--default-handler` : Boolean flag or option. Extracted via static analysis.
- `--events` : Boolean flag or option. Extracted via static analysis.
- `--listeners` : Boolean flag or option. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Executes external system binaries or shell commands.
- Instantiates internal components: event.

