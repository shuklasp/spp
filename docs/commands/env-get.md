# env:get

## NAME
env:get - Get a specific configuration variable

## SYNOPSIS
`php spp.php env:get <key> [--app=appname]`

## PURPOSE
Fetches and displays real-time compiled framework configuration values.

## OPTIONS AVAILABLE
- `<key>`: **Required**. The configuration key notation to query (e.g., `sys:debug`).
- `--app=<app_name>`: Scope the variable read to a specific app.

## UNDER THE HOOD ACTIVITY
It parses the positional CLI argument to extract the target key. It shifts context via `Scheduler::withContext()`. The heavy lifting defers to `\SPP\SPPConfig::get($key)`. The return payload is verified against `null`. If the resolved payload is a scalar primitive, it stringifies it directly. If it yields an array block or an object, it processes the structure natively using `json_encode()` with `JSON_PRETTY_PRINT` format.

## EXAMPLES
```bash
php spp.php env:get database.host --app=storefront
```
