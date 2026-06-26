# env:backup

## NAME
env:backup - Backup all environment configurations

## SYNOPSIS
`php spp.php env:backup`

## PURPOSE
Locates, archives, and stores system-wide and application-specific `.yml`, `.yaml`, and `.json` configuration files into a time-stamped ZIP package.

## OPTIONS AVAILABLE
No additional options are accepted.

## UNDER THE HOOD ACTIVITY
The command verifies or provisions the `var/backups` directory in `SPP_BASE_DIR`. It utilizes PHP's native `\ZipArchive` extension to open a newly minted archive appended with a `Ymd_His` date format timestamp. It sweeps the global `etc/` folder for configurations, buffering their paths. It then uses array filtering and `glob()` to recursively traverse the `SPP_APP_DIR`, entering each valid context to map internal `etc/` directories. It loops through the aggregated file array, passing each source path into `ZipArchive::addFile()`, recreating the logical tree locally within the zip structure, and flushes the archive buffer.

## EXAMPLES
```bash
php spp.php env:backup
```
