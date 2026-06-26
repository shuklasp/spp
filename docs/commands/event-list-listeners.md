# event:list-listeners

## NAME
event:list-listeners - List all registered global event listeners

## SYNOPSIS
`php spp.php event:list-listeners [--app=<app_name>]`

## PURPOSE
Exposes the internal mapping of every subscribed callback bound to system events inside the application context.

## OPTIONS AVAILABLE
- `--app=<app_name>`: Specifies which application's DI context to evaluate.

## UNDER THE HOOD ACTIVITY
Bootstrapped within the bounded app closure, the command validates if `\SPP\Core\EventManager` is included in the memory pool. Due to strict memory encapsulation, it establishes a `\ReflectionClass` context around the `EventManager`. It forcefully extracts the private `listeners` mapping property using `setAccessible(true)` and `getValue()`. It scans the resulting nested associative array matrix, identifying discrete string-based event names, and prints them out alongside an aggregate count of connected callbacks listening to that specific node.

## EXAMPLES
```bash
php spp.php event:list-listeners
```
