# drishyam:theme:check

## NAME
drishyam:theme:check - Validate Drishyam theme assets and structure

## SYNOPSIS
`php spp.php drishyam:theme:check [--app=<app_name>]`

## PURPOSE
Analyzes registered themes loaded in the active context, verifying configuration presence and structural directory integrity.

## OPTIONS AVAILABLE
- `--app=<app_name>`: Application context binding (default: 'default').

## UNDER THE HOOD ACTIVITY
Inside the bounded execution context, the script summons the `Drishyam` core instance and invokes `boot()`. It extracts an array of active themes via `getThemes()`. It iterates over the collection, isolating the `getPath()` of each theme. It performs discrete file system checks using `file_exists()` and `glob()` to verify the presence of `theme.yml`, `style.css`, or `*.info.yml` for validity. It further probes for an active `views/` directory inside the theme root. Output is formatted into a structural terminal block per theme.

## EXAMPLES
```bash
php spp.php drishyam:theme:check
```
