# ext:list

## NAME
ext:list - List all available and installed extensions

## SYNOPSIS
`php spp.php ext:list`

## PURPOSE
Displays all discrete module packages physically present inside the system's extension ecosystem.

## OPTIONS AVAILABLE
This command requires no additional options.

## UNDER THE HOOD ACTIVITY
It defines the path to the physical system module footprint located at `SPP_BASE_DIR . '/modules'`. It verifies active directory presence before using a specialized `glob($extDir . '/*', GLOB_ONLYDIR)` search to locate discrete folders exclusively, completely ignoring loose files. It iterates over the results, formatting the raw directory basename strings, appending a static "(Enabled)" indicator suffix for terminal output. 

## EXAMPLES
```bash
php spp.php ext:list
```
