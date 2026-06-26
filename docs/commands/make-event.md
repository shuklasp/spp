# NAME
`make:event` - Create a new event entry and scaffold its handler

# SYNOPSIS
`php spp.php make:event <EventName> <HandlerClassName> [--app=appname] [--overridable] [--default-handler]`

# PURPOSE
The `make:event` command provisions event-driven architectural components. It physically creates a new Event Handler PHP class and registers the execution mapping within the application's `events.yml` configuration map, bridging the custom handler logic to the system event bus.

# OPTIONS AVAILABLE
- `<EventName>` (string, required): The target string representing the event trigger (e.g. `user.registered`).
- `<HandlerClassName>` (string, required): The target handler class name (e.g. `SendWelcomeEmail`).
- `--app=<appname>` (string, optional): The application context. (Requires a specific app namespace; errors on `default`).
- `--overridable` (flag, optional): Modifies `events.yml` to set the event as overridable.
- `--default-handler` (flag, optional): Registers the HandlerClassName as the `default_handler` inside the config instead of pushing it to the `listeners` array.

# UNDER THE HOOD ACTIVITY
The command invokes `buildFromStub('eventhandler', ...)` to generate the skeleton class `{HandlerClassName}.php` inside the designated app's `events` directory. 
After class generation, it targets `SPP_APP_DIR/src/{app}/etc/events.yml`. It parses the file using the `Symfony\Component\Yaml\Yaml` component. It intricately handles schema upgrades: if the existing event is a simple array but flags like `--overridable` or `--default-handler` are utilized, it dynamically refactors the array into a complex object containing a `listeners` array.
The fully qualified class name (e.g., `\App\Admin\Events\SendWelcomeEmail`) is subsequently injected into the `events.yml` structure.
Finally, to ensure the SPP event bus recognizes the modification immediately, it utilizes `shell_exec()` to trigger the `cache:clear` CLI command, wiping the framework's internal routing/event cache.

# EXAMPLES
**1. Scaffold a user registration event:**
```bash
php spp.php make:event user.created UserCreatedHandler --app=portal
```
