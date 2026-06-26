# env:list

## NAME
env:list - List all environment and configuration variables for an app context

## SYNOPSIS
`php spp.php env:list [--app=<app_name>]`

## PURPOSE
Spits out an alphabetically sorted, flattened table representation of every single active configuration key and value known to the SPP configuration cache.

## OPTIONS AVAILABLE
- `--app=<app_name>`: Application scope (default: 'default').

## UNDER THE HOOD ACTIVITY
Operating within a scheduled app context, the command forces an active refresh of the configuration engine via `SPPConfig::compile($appname)` to guarantee no delta mismatch. In an advanced structural hack, the command instantiates a `\ReflectionClass` targeting `SPPConfig`, forces accessibility on the private static `getCompiledPath()` method, and invokes it to extract the raw PHP array cache location directly from disk. It physically requires the cache file, triggers `ksort()`, and truncates the stringified outputs into a beautifully padded 45-character width columnar representation.

## EXAMPLES
```bash
php spp.php env:list --app=admin
```
