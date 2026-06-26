# NAME
`make:eventhand` - Create a new Event Handler class

# SYNOPSIS
`php spp.php make:eventhand <HandlerClassName> [--app=appname]`

# PURPOSE
The `make:eventhand` command specifically scaffolds an unlinked Event Handler PHP class. Unlike `make:event`, it *does not* register the class in the `events.yml` configuration map, allowing developers to manually link complex event topologies.

# OPTIONS AVAILABLE
- `<HandlerClassName>` (string, required): The target name for the PHP Class.
- `--app=<appname>` (string, optional): The application context namespace.

# UNDER THE HOOD ACTIVITY
It resolves the target `Events` namespace based on the requested application context. If context is explicitly `default`, the namespace degrades to `EventHandlers`. It invokes the `buildFromStub()` compiler passing the `eventhandler` stub format, generating `{HandlerClassName}.php`.
Once generation concludes, it programmatically triggers a system cache flush via an asynchronous `shell_exec("php spp.php cache:clear")` invocation to guarantee namespace auto-discovery.

# EXAMPLES
**1. Generate an isolated handler:**
```bash
php spp.php make:eventhand AuditLogger --app=api
```
