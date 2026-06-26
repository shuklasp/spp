# sys:debug

## NAME
sys:debug - Toggle global framework debug mode (on|off)

## SYNOPSIS
`php spp.php sys:debug <on|off>`

## PURPOSE
Enables or disables verbose diagnostics and the API Flight Recorder globally across the SPP framework.

## OPTIONS AVAILABLE
- `on`: Enable debug mode.
- `off`: Disable debug mode.

## UNDER THE HOOD ACTIVITY
The command validates the desired state argument (`on` or `off`). It locates the `global-settings.yml` configuration file inside `SPP_ETC_DIR`. It utilizes the `Symfony\Component\Yaml\Yaml` component to parse the YAML document into a PHP associative array. It navigates to the `['settings']['debug']` key, forcefully creating the `['settings']` array block if absent, and assigns a boolean derived from the command input. It then re-dumps the array structure into YAML format and performs a `file_put_contents` flush to disk to solidify the change.

## EXAMPLES
```bash
php spp.php sys:debug on
```
