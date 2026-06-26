# drishyam:clear

## NAME
drishyam:clear - Clear the Drishyam rendering cache

## SYNOPSIS
`php spp.php drishyam:clear`

## PURPOSE
Purges all temporarily compiled view artifacts from the Drishyam template engine.

## OPTIONS AVAILABLE
This command takes no arguments.

## UNDER THE HOOD ACTIVITY
The command builds the path to the localized Drishyam cache located at `var/storage/temp/views` relative to `SPP_APP_DIR`. It utilizes `glob()` to load all internal file paths into an array, and iteratively calls the native PHP `unlink()` command on every valid file it discovers, keeping a count of successful purges to output to the developer.

## EXAMPLES
```bash
php spp.php drishyam:clear
```
