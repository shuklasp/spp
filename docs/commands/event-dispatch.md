# event:dispatch

## NAME
event:dispatch - Alias for event:fire

## SYNOPSIS
`php spp.php event:dispatch --event=<event_name> [--payload=<json>]`

## PURPOSE
Triggers a programmatic global event hook across the entire framework scope. Functions identically as an alias namespace for `EventFireCommand`.

## OPTIONS AVAILABLE
- `--event=<event_name>`: Target event name.
- `--payload=<json>`: JSON formatted payload data.

## UNDER THE HOOD ACTIVITY
Inherits all class methodologies, logic, and state behavior exclusively from `EventFireCommand` by explicitly requiring its parent script location dynamically (`require_once __DIR__ . '/EventFireCommand.php'`) and extending it. It merely overrides the class-protected `$name` to `event:dispatch`.

## EXAMPLES
```bash
php spp.php event:dispatch --event=user.login
```
