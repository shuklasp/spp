# ext:install

## NAME
ext:install - Install an extension from a zip or directory

## SYNOPSIS
`php spp.php ext:install --source=<path_or_url>`

## PURPOSE
Provision an external extension archive or directory onto the SPP extension registry.

## OPTIONS AVAILABLE
- `--source=<path_or_url>`: **Required**. Defines the raw target URI or discrete system path to the extension payload.

## UNDER THE HOOD ACTIVITY
It evaluates CLI arguments for the bounded `--source` input flag. Validates its active presence. The script represents an unfinished stub interface intended to ultimately deploy archives or git source packages to the `modules/` system domain. 

## EXAMPLES
```bash
php spp.php ext:install --source=https://example.com/ext.zip
```
