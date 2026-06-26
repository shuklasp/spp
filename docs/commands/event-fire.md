# event:fire

## NAME
event:fire - Trigger a specific event manually

## SYNOPSIS
`php spp.php event:fire --event=<event_name> [--payload=<json>]`

## PURPOSE
Forces a synchronous invocation of any globally registered event hook attached to the target application context, bypassing standard controller lifecycles.

## OPTIONS AVAILABLE
- `--event=<event_name>`: **Required**. Target event string matching registered listeners.
- `--payload=<json>`: Optional string representing data to be forwarded. If valid JSON, it gets actively decoded into an associative PHP representation.
- `--app=<app_name>`: App context configuration.

## UNDER THE HOOD ACTIVITY
The script iterates the arguments to extract parameters. Noticeably, it feeds the payload parameter aggressively through `json_decode()`, falling back entirely to a raw string format on parse failure (`json_decode(...) ?? substr(...)`). Bounded inside the application's closure block, it verifies if `\SPP\Core\EventManager` is currently recognized by the autoloader. If verified, it forcibly activates `\SPP\SPPEvent::triggerHook($event, $payload)`, propagating the payload data systematically down the framework's internal subscriber execution path.

## EXAMPLES
```bash
php spp.php event:fire --event=cache.clear
php spp.php event:fire --event=email.send --payload='{"to":"test@example.com"}'
```
