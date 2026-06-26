# env:set

## NAME
env:set - Set a specific configuration variable

## SYNOPSIS
`php spp.php env:set <key> <value> [--app=appname]`

## PURPOSE
Updates a live configuration key with a new value directly from the CLI.

## OPTIONS AVAILABLE
- `<key>`: **Required**. Target setting index (e.g., `app:key`).
- `<value>`: **Required**. The literal value to enforce.
- `--app=<app_name>`: Target context scope.

## UNDER THE HOOD ACTIVITY
It captures positional strings off the CLI string stream. Inside the bounded application context, it runs a rudimentary scalar normalization block—it explicitly intercepts the text string representations of "true", "false", and "null", type-casting them to native PHP boolean/null variants. It further passes numeric inputs through a mathematical identity translation (`$value + 0`) to derive native integers/floats over strings. The polished payload is forwarded to `\SPP\SPPConfig::set($key, $value)`, assuming the configuration layer possesses write-back persistence logic.

## EXAMPLES
```bash
php spp.php env:set system.maintenance true
```
